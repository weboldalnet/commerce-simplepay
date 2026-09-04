<?php

namespace Weboldalnet\CommerceSimplepay\Services;

use Weboldalnet\CommerceCore\Data\PaymentRequestData;
use Weboldalnet\CommerceCore\Status\PaymentStatus;

/**
 * SimplePay fizetési folyamat.
 *
 * A fizetés menete: start → SimplePay fizetőoldal → back (böngésző) + IPN (szerver).
 * A tranzakció VÉGSŐ állapotát mindig az IPN adja; a back csak tájékoztatásra való,
 * mert a vásárló bezárhatja a böngészőt fizetés után.
 */
class SimplepayPaymentService
{
    /** @var SimplepayClient */
    protected $client;

    public function __construct(SimplepayClient $client = null)
    {
        $this->client = $client ?: app(SimplepayClient::class);
    }

    /**
     * Fizetési tranzakció indítása.
     *
     * @return array{success: bool, payment_url: string|null, transaction_id: mixed, order_ref: string|null, message: string|null, raw: array|null}
     */
    public function startPayment(PaymentRequestData $data): array
    {
        $orderRef = self::orderRef($data);
        $payload = $this->buildStartPayload($data, $orderRef);

        $result = $this->client->start($payload, $data->orderId);

        if (!$result['success']) {
            return [
                'success' => false,
                'payment_url' => null,
                'transaction_id' => null,
                'order_ref' => $orderRef,
                'message' => $result['message'],
                'raw' => $result['data'],
            ];
        }

        $response = (array) $result['data'];

        return [
            'success' => true,
            'payment_url' => $response['paymentUrl'] ?? null,
            'transaction_id' => $response['transactionId'] ?? null,
            'order_ref' => $response['orderRef'] ?? $orderRef,
            'message' => null,
            'raw' => $response,
        ];
    }

    /**
     * A start hívás payloadja.
     *
     * FIGYELEM: szándékosan NEM küldünk "items" tömböt. A dokumentáció szerint ha
     * az "items" is küldve van, a "total" figyelmen kívül marad, és az összeget a
     * tételekből számolja a SimplePay – a kerekítések miatt így eltérhetne attól,
     * amit a rendelés végösszegeként mutattunk a vásárlónak.
     */
    protected function buildStartPayload(PaymentRequestData $data, string $orderRef): array
    {
        $timeoutMinutes = (int) SimplepaySettingsService::get('timeout_minutes', 30) ?: 30;

        $payload = [
            'salt' => SimplepayClient::salt(),
            'merchant' => (string) SimplepaySettingsService::get('merchant'),
            'orderRef' => $orderRef,
            'currency' => strtoupper((string) ($data->currency ?: SimplepaySettingsService::get('currency', 'HUF'))),
            'customerEmail' => (string) $data->customerEmail,
            'language' => strtoupper((string) ($data->language ?: SimplepaySettingsService::get('language', 'HU'))),
            'sdkVersion' => (string) config('commerce-simplepay.sdk_version'),
            'methods' => ['CARD'],
            'total' => (string) round((float) $data->amount, 2),
            'timeout' => date('c', time() + $timeoutMinutes * 60),
            'url' => $data->returnUrl ?: route('commerce.simplepay.return'),
        ];

        if ($data->customerName) {
            $payload['customer'] = (string) $data->customerName;
        }

        $invoice = self::invoiceData($data);
        if ($invoice) {
            $payload['invoice'] = $invoice;
        }

        return $payload;
    }

    /**
     * Számlázási adatok a fizetőoldalhoz.
     * Csak akkor küldjük, ha a lényeges mezők megvannak – a hiányos blokk hibát okozna.
     */
    protected static function invoiceData(PaymentRequestData $data): ?array
    {
        $billing = is_array($data->billingData) ? $data->billingData : [];

        $name = $billing['name'] ?? $data->customerName;
        $city = $billing['city'] ?? null;
        $zip = $billing['zip'] ?? null;
        $address = $billing['address'] ?? null;

        if (!$name || !$city || !$zip || !$address) {
            return null;
        }

        return array_filter([
            'name' => (string) $name,
            'company' => (string) ($billing['company'] ?? ''),
            'country' => strtolower((string) ($billing['country'] ?? 'hu')),
            'state' => (string) ($billing['state'] ?? $city),
            'city' => (string) $city,
            'zip' => (string) $zip,
            'address' => (string) $address,
            'phone' => (string) ($data->customerPhone ?? ''),
        ], function ($value) {
            return $value !== '';
        });
    }

    /**
     * A kereskedői tranzakcióazonosító.
     *
     * A rendelésszámot használjuk, ahogy a Barionnál is – így a SimplePay admin
     * felületén is azonnal beazonosítható, melyik rendeléshez tartozik.
     *
     * FONTOS: a SimplePay egy orderRef-et csak EGYSZER fogad el elindított
     * tranzakcióhoz. Újrapróbálásnál ugyanazzal az azonosítóval 5013-as hibát
     * ad ("Érvénytelen kereskedő vagy jogosultság"), ami félrevezető: valójában
     * a foglalt orderRef a baj. Ezért a második indítástól sorszámot fűzünk
     * hozzá (RENDELESSZAM-2, -3, …).
     *
     * A sorszám a MÁR ELINDÍTOTT tranzakciók számából jön (amelyeknek van
     * transaction_id-juk). A sikertelen indítás nem foglal orderRef-et, ezért
     * azokat nem számoljuk – így egy elbukott -2 után újra -2 megy ki,
     * nem ugrik feleslegesen.
     */
    public static function orderRef(PaymentRequestData $data): string
    {
        $base = (string) ($data->orderNumber ?: $data->orderId);
        $attempt = self::startedAttempts($data->orderId);

        return $attempt > 0 ? $base . '-' . ($attempt + 1) : $base;
    }

    /**
     * Hány SimplePay-tranzakció indult már el ehhez a rendeléshez.
     *
     * Bármilyen hiba esetén 0-t adunk vissza: ilyenkor a sima rendelésszám megy
     * ki, ami az első fizetésnél mindig helyes – a fizetés indítását nem
     * buktathatja el egy nyilvántartási lekérdezés.
     */
    protected static function startedAttempts($orderId): int
    {
        $model = \Weboldalnet\CommerceCore\Models\PaymentTransaction::class;

        if (!$orderId || !class_exists($model)) {
            return 0;
        }

        try {
            return (int) $model::query()
                ->where('order_id', $orderId)
                ->where('provider', 'simplepay')
                ->whereNotNull('transaction_id')
                ->where('transaction_id', '!=', '')
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Tranzakció valódi állapotának lekérdezése.
     */
    public function queryTransaction(string $orderRef, $orderId = null): ?array
    {
        $result = $this->client->query([$orderRef], [], $orderId);

        if (!$result['success']) {
            return null;
        }

        $transactions = $result['data']['transactions'] ?? [];

        return $transactions[0] ?? null;
    }

    /**
     * A böngészőből érkező "back" esemény leképezése fizetési státuszra.
     *
     * Ez csak az authorizáció eredménye – a végleges állapotot az IPN adja.
     */
    public static function mapBackEvent(?string $event): string
    {
        switch (strtoupper((string) $event)) {
            case 'SUCCESS':
                return PaymentStatus::PAID;
            case 'FAIL':
                return PaymentStatus::FAILED;
            case 'CANCEL':
                return PaymentStatus::CANCELLED;
            case 'TIMEOUT':
                // Az időtúllépés azt jelenti, hogy a fizetés el sem indult.
                return PaymentStatus::CANCELLED;
            default:
                return PaymentStatus::PENDING;
        }
    }

    /**
     * Az IPN státuszának leképezése fizetési státuszra.
     */
    public static function mapIpnStatus(?string $status): string
    {
        switch (strtoupper((string) $status)) {
            case 'FINISHED':
                return PaymentStatus::PAID;
            case 'AUTHORIZED':
                // Kétlépcsős fizetésnél az összeg befoglalva, de még nem terhelve.
                return PaymentStatus::PENDING;
            case 'NOTAUTHORIZED':
                return PaymentStatus::FAILED;
            case 'CANCELLED':
                return PaymentStatus::CANCELLED;
            case 'TIMEOUT':
                return PaymentStatus::CANCELLED;
            case 'REFUND':
            case 'REVERSED':
                return PaymentStatus::REFUNDED;
            default:
                return PaymentStatus::PENDING;
        }
    }

    public function client(): SimplepayClient
    {
        return $this->client;
    }
}
