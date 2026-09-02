<?php

namespace Weboldalnet\CommerceSimplepay\Services;

use Illuminate\Support\Facades\Log;
use Weboldalnet\CommerceCore\Services\ProviderLogger;

/**
 * SimplePay v2 API kliens.
 *
 * A SimplePay üzenetei egyszerű JSON POST-ok; a hitelesítés a body HMAC-SHA384
 * aláírása Base64-ben, a "Signature" fejlécben. Ezért nincs szükség külső SDK-ra.
 *
 * FIGYELEM – két buktató, ami némán rossz aláírást eredményez:
 *  1) Az aláírás a NYERS body stringre számítódik. Amit aláírtunk, pontosan azt
 *     kell kiküldeni – nem szabad újra json_encode-olni.
 *  2) A json_encode alapból escape-eli a per-jeleket (https:\/\/). Ezt így KELL
 *     hagyni: JSON_UNESCAPED_SLASHES-szel más aláírás jön ki, és a SimplePay
 *     elutasítja. (A dokumentáció mintapéldáján ellenőrizve.)
 */
class SimplepayClient
{
    /** @var ProviderLogger|null */
    protected $logger;

    public function __construct(ProviderLogger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Üzenet aláírása: Base64( HMAC-SHA384( body, SECRET_KEY ) ).
     */
    public static function sign(string $body, string $secretKey): string
    {
        return base64_encode(hash_hmac('sha384', $body, $secretKey, true));
    }

    /**
     * Aláírás ellenőrzése időzítés-független összehasonlítással.
     */
    public static function verify(string $body, ?string $signature, string $secretKey): bool
    {
        if (!$signature) {
            return false;
        }

        return hash_equals(self::sign($body, $secretKey), trim($signature));
    }

    /**
     * 32 karakteres véletlen salt, ahogy a SimplePay kéri.
     */
    public static function salt(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * API hívás egy SimplePay végpontra.
     *
     * @return array{success: bool, data: array|null, message: string|null}
     */
    public function call(string $method, array $payload, $orderId = null): array
    {
        if (!SimplepaySettingsService::hasCredentials()) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'Hiányzó SimplePay hozzáférési adatok (kereskedő azonosító, SECRET_KEY).',
            ];
        }

        $secretKey = (string) SimplepaySettingsService::get('secret_key');
        $url = rtrim(SimplepaySettingsService::apiBaseUrl(), '/') . '/' . $method;

        // A body-t EGYSZER állítjuk elő, és pontosan ezt írjuk alá és küldjük ki.
        $body = json_encode($payload);
        $signature = self::sign($body, $secretKey);

        $this->log($method, $payload, null, true, null, $orderId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Signature: ' . $signature,
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            $this->log($method, $payload, null, false, $curlError, $orderId);

            return ['success' => false, 'data' => null, 'message' => 'Hálózati hiba: ' . $curlError];
        }

        $rawHeaders = substr($raw, 0, $headerSize);
        $responseBody = substr($raw, $headerSize);
        $responseSignature = self::headerValue($rawHeaders, 'Signature');

        $json = json_decode($responseBody, true);
        if (!is_array($json)) {
            $this->log($method, $payload, ['raw' => mb_substr($responseBody, 0, 500)], false, 'Nem JSON válasz', $orderId);

            return ['success' => false, 'data' => null, 'message' => 'A SimplePay válasza nem értelmezhető (HTTP ' . $httpCode . ').'];
        }

        // A válasz aláírását is ellenőrizzük – enélkül nem lenne bizonyíték arra,
        // hogy a válasz tényleg a SimplePay-től jött.
        if (!self::verify($responseBody, $responseSignature, $secretKey)) {
            $this->log($method, $payload, $json, false, 'Érvénytelen válasz-aláírás', $orderId);

            return ['success' => false, 'data' => $json, 'message' => 'A SimplePay válaszának aláírása érvénytelen.'];
        }

        if (!empty($json['errorCodes'])) {
            $message = self::errorMessage((array) $json['errorCodes']);
            $this->log($method, $payload, $json, false, $message, $orderId);

            return ['success' => false, 'data' => $json, 'message' => $message];
        }

        $this->log($method, $payload, $json, true, null, $orderId);

        return ['success' => true, 'data' => $json, 'message' => null];
    }

    /**
     * Fizetési tranzakció indítása.
     */
    public function start(array $data, $orderId = null): array
    {
        return $this->call('start', $data, $orderId);
    }

    /**
     * Tranzakció adatainak lekérdezése.
     *
     * A visszatéréskor ezzel derítjük ki a tranzakció valódi állapotát, mert a
     * böngészőből érkező "back" csak az authorizáció eredményét jelzi.
     */
    public function query(array $orderRefs = [], array $transactionIds = [], $orderId = null): array
    {
        $payload = [
            'salt' => self::salt(),
            'merchant' => (string) SimplepaySettingsService::get('merchant'),
            'sdkVersion' => (string) config('commerce-simplepay.sdk_version'),
        ];

        if ($orderRefs) {
            $payload['orderRefs'] = array_values($orderRefs);
        }

        if ($transactionIds) {
            $payload['transactionIds'] = array_values($transactionIds);
        }

        return $this->call('query', $payload, $orderId);
    }

    /**
     * A böngészőből érkező "back" adat ellenőrzése és dekódolása.
     *
     * @return array|null a dekódolt válasz (r, t, e, m, o), vagy null ha az aláírás hibás
     */
    public function decodeBack(?string $r, ?string $s): ?array
    {
        if (!$r || !$s) {
            return null;
        }

        $secretKey = (string) SimplepaySettingsService::get('secret_key');

        // Az aláírás a Base64 stringre számítódik, NEM a dekódolt tartalomra.
        if (!self::verify($r, $s, $secretKey)) {
            return null;
        }

        $decoded = json_decode(base64_decode($r), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * IPN üzenet ellenőrzése.
     */
    public function verifyIpn(string $rawBody, ?string $signature): bool
    {
        return self::verify($rawBody, $signature, (string) SimplepaySettingsService::get('secret_key'));
    }

    /**
     * Az IPN-re adandó válasz: ugyanaz a tartalom, a fogadás időpontjával
     * kiegészítve, és az így kapott JSON-ra számított aláírással.
     *
     * @return array{body: string, signature: string}
     */
    public function buildIpnResponse(array $ipnData): array
    {
        $ipnData['receiveDate'] = date('c');

        $body = json_encode($ipnData);

        return [
            'body' => $body,
            'signature' => self::sign($body, (string) SimplepaySettingsService::get('secret_key')),
        ];
    }

    /**
     * Egy fejléc értékének kiolvasása a nyers válasz-fejlécekből.
     */
    protected static function headerValue(string $rawHeaders, string $name): ?string
    {
        foreach (preg_split('/\r?\n/', $rawHeaders) as $line) {
            if (stripos($line, $name . ':') === 0) {
                return trim(substr($line, strlen($name) + 1));
            }
        }

        return null;
    }

    /**
     * Beszédes üzenet a SimplePay hibakódjaiból.
     */
    protected static function errorMessage(array $errorCodes): string
    {
        $known = [
            '2' => 'Érvénytelen kereskedői fiók (merchant).',
            '3' => 'Érvénytelen orderRef.',
            '5' => 'Érvénytelen összeg.',
            '1001' => 'Hiányzó vagy érvénytelen aláírás.',
            '5000' => 'Általános hiba a SimplePay oldalán.',
            '5010' => 'Hiányzó kötelező mező.',
            '5011' => 'Érvénytelen mezőérték.',
            '5013' => 'Érvénytelen kereskedő vagy jogosultság.',
            '5026' => 'Az orderRef már használatban van egy sikeres fizetéshez.',
            '5321' => 'Érvénytelen kereskedői fiók vagy hiányzó jogosultság.',
        ];

        $parts = [];
        foreach ($errorCodes as $code) {
            $code = (string) $code;
            $parts[] = $code . (isset($known[$code]) ? ' – ' . $known[$code] : '');
        }

        return 'SimplePay hiba: ' . implode(', ', $parts);
    }

    /**
     * Naplózás a commerce_provider_logs táblába. Sosem buktatja el a hívást.
     * A SECRET_KEY szándékosan nem része a naplózott payloadnak.
     */
    protected function log($endpoint, $request, $response, $isSuccess, $errorMessage = null, $orderId = null): void
    {
        if (!$this->logger || !SimplepaySettingsService::getBool('log_payloads', true)) {
            return;
        }

        try {
            $this->logger->logResponse(
                'payment',
                config('commerce-simplepay.provider_code', 'simplepay'),
                $endpoint,
                is_array($request) ? $request : ['raw' => $request],
                is_array($response) ? $response : null,
                $isSuccess ? 200 : 400,
                (bool) $isSuccess,
                $errorMessage,
                is_numeric($orderId) ? (int) $orderId : null
            );
        } catch (\Throwable $e) {
            Log::warning('SimplePay provider log hiba: ' . $e->getMessage());
        }
    }
}
