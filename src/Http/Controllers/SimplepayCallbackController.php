<?php

namespace Weboldalnet\CommerceSimplepay\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Weboldalnet\CommerceCore\Services\PaymentCallbackProcessor;
use Weboldalnet\CommerceSimplepay\Services\SimplepayClient;
use Weboldalnet\CommerceSimplepay\Services\SimplepaySettingsService;

class SimplepayCallbackController extends Controller
{
    protected $callbackProcessor;
    protected $client;

    public function __construct(PaymentCallbackProcessor $callbackProcessor, SimplepayClient $client)
    {
        $this->callbackProcessor = $callbackProcessor;
        $this->client = $client;
    }

    /**
     * A vásárló visszatérése a SimplePay fizetőoldalról (böngésző, GET).
     *
     * Csak tájékoztat: a tranzakció végállapotát az IPN rögzíti. Ha a vásárló
     * ide sem jut vissza (bezárja a böngészőt), a rendelés az IPN alapján akkor is
     * rendben lezárul.
     */
    public function handleReturn(Request $request)
    {
        $provider = config('commerce-simplepay.provider_code', 'simplepay');

        try {
            $processResult = $this->callbackProcessor->process($provider, $request->all());
        } catch (\Throwable $e) {
            Log::error('SimplePay visszatérés feldolgozási hiba: ' . $e->getMessage());
            $processResult = null;
        }

        $orderId = $processResult['transaction']->order_id ?? null;
        if ($orderId) {
            return redirect()->route('site.webshop.payment.result', ['order' => $orderId]);
        }

        return redirect()->route('site.webshop.checkout.index')
            ->with('error', 'Sikertelen fizetési visszatérés.');
    }

    /**
     * IPN – a SimplePay szerver-szerver értesítése a tranzakció végállapotáról.
     *
     * A SimplePay 20 másodpercen belül vár HTTP 200-as választ, amiben vissza kell
     * küldeni a kapott adatokat a fogadás időpontjával (receiveDate) kiegészítve,
     * a válaszra számított aláírással. Ha a válasz nem megfelelő, a rendszer
     * 3 napon át újraküldi az üzenetet.
     */
    public function handleIpn(Request $request)
    {
        $rawBody = $request->getContent();
        $signature = $request->header('Signature');

        // 1) Az üzenet hitelesítése. Aláírás nélkül nem dolgozunk fel semmit.
        if (!$this->client->verifyIpn($rawBody, $signature)) {
            Log::warning('SimplePay IPN: érvénytelen aláírás.', [
                'ip' => $request->ip(),
                'body' => mb_substr($rawBody, 0, 500),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // 2) Opcionális forrás-ellenőrzés. Alapból kikapcsolva: proxy/CDN mögött
        //    téves elutasításhoz vezetne, az aláírás pedig önmagában is elég.
        if (SimplepaySettingsService::getBool('ipn_ip_check', false) && !self::isAllowedIp($request->ip())) {
            Log::warning('SimplePay IPN: nem engedélyezett forrás-IP.', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Forbidden'], 403);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $provider = config('commerce-simplepay.provider_code', 'simplepay');

        try {
            $this->callbackProcessor->process($provider, $payload);
        } catch (\Throwable $e) {
            Log::error('SimplePay IPN feldolgozási hiba: ' . $e->getMessage(), [
                'order_ref' => $payload['orderRef'] ?? null,
            ]);

            // Szándékosan nem 200: így a SimplePay újrapróbálja a kézbesítést,
            // és nem vész el a státuszváltozás.
            return response()->json(['error' => 'Processing failed'], 500);
        }

        // 3) A kötelező visszaigazolás: ugyanaz a tartalom + receiveDate, aláírva.
        $response = $this->client->buildIpnResponse($payload);

        return response($response['body'], 200)
            ->header('Content-Type', 'application/json')
            ->header('Signature', $response['signature']);
    }

    /**
     * A hívó IP a SimplePay közölt tartományaiban van-e.
     */
    protected static function isAllowedIp(?string $ip): bool
    {
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        foreach ((array) config('commerce-simplepay.ipn_allowed_ranges', []) as $range) {
            [$subnet, $bits] = array_pad(explode('/', $range, 2), 2, '32');
            $mask = -1 << (32 - (int) $bits);

            if ((ip2long($ip) & $mask) === (ip2long($subnet) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
