<?php

namespace Weboldalnet\CommerceSimplepay\Providers;

use Weboldalnet\CommerceCore\Contracts\PaymentProviderInterface;
use Weboldalnet\CommerceCore\Data\PaymentCallbackResult;
use Weboldalnet\CommerceCore\Data\PaymentCreateResult;
use Weboldalnet\CommerceCore\Data\PaymentRefundData;
use Weboldalnet\CommerceCore\Data\PaymentRefundResult;
use Weboldalnet\CommerceCore\Data\PaymentRequestData;
use Weboldalnet\CommerceCore\Status\PaymentStatus;
use Weboldalnet\CommerceSimplepay\Services\SimplepayPaymentService;
use Weboldalnet\CommerceSimplepay\Services\SimplepaySettingsService;

class SimplepayPaymentProvider implements PaymentProviderInterface
{
    /** @var SimplepayPaymentService */
    protected $service;

    public function __construct(SimplepayPaymentService $service = null)
    {
        $this->service = $service ?: app(SimplepayPaymentService::class);
    }

    public function getCode()
    {
        return config('commerce-simplepay.provider_code', 'simplepay');
    }

    public function getName()
    {
        // A pénztárban megjelenő elnevezés adminból szerkeszthető.
        return (string) SimplepaySettingsService::get('payment_method_label', 'SimplePay bankkártyás fizetés');
    }

    public function isOnline()
    {
        return true;
    }

    public function createPayment(PaymentRequestData $data)
    {
        try {
            $result = $this->service->startPayment($data);
        } catch (\Throwable $e) {
            return PaymentCreateResult::failure([
                'status' => PaymentStatus::FAILED,
                'provider' => $this->getCode(),
                'message' => 'Kivétel a SimplePay fizetés indításakor: ' . $e->getMessage(),
            ]);
        }

        if (!$result['success']) {
            return PaymentCreateResult::failure([
                'status' => PaymentStatus::FAILED,
                'provider' => $this->getCode(),
                'message' => $result['message'] ?: 'A SimplePay fizetés indítása sikertelen.',
                'raw_response' => $result['raw'],
            ]);
        }

        return PaymentCreateResult::success([
            'status' => PaymentStatus::PENDING,
            'provider' => $this->getCode(),
            // A saját azonosítónk az orderRef – erre keres vissza az IPN feldolgozó.
            'transaction_id' => $result['order_ref'],
            'provider_transaction_id' => $result['transaction_id'],
            'redirect_url' => $result['payment_url'],
            'message' => 'SimplePay fizetés elindítva.',
            'raw_response' => $result['raw'],
        ]);
    }

    /**
     * A vásárló visszatérése a fizetőoldalról (böngésző, GET).
     *
     * Csak tájékoztatásra való: az "r" mező az authorizáció eredményét adja, nem a
     * tranzakció végállapotát. A tényleges állapotot a query hívással pontosítjuk,
     * a végső szót pedig mindig az IPN mondja ki.
     */
    public function handleReturn(array $payload)
    {
        $decoded = $this->service->client()->decodeBack(
            $payload['r'] ?? null,
            $payload['s'] ?? null
        );

        if (!$decoded) {
            return PaymentCallbackResult::failure([
                'provider' => $this->getCode(),
                'status' => PaymentStatus::PENDING,
                'message' => 'A SimplePay visszatérés aláírása érvénytelen vagy hiányzik.',
                'raw_payload' => $payload,
            ]);
        }

        $orderRef = $decoded['o'] ?? null;
        $status = SimplepayPaymentService::mapBackEvent($decoded['e'] ?? null);

        // A "SUCCESS" csak sikeres authorizációt jelent. A tranzakció valódi
        // állapotát lekérdezzük, hogy a vásárlónak azonnal pontosat mutassunk.
        if ($orderRef && $status === PaymentStatus::PAID) {
            $transaction = $this->service->queryTransaction((string) $orderRef);
            if ($transaction && !empty($transaction['status'])) {
                $status = SimplepayPaymentService::mapIpnStatus($transaction['status']);
            }
        }

        return new PaymentCallbackResult([
            'success' => $status === PaymentStatus::PAID,
            'status' => $status,
            'provider' => $this->getCode(),
            'transaction_id' => $orderRef,
            'provider_transaction_id' => $decoded['t'] ?? null,
            'message' => 'SimplePay visszatérés: ' . ($decoded['e'] ?? '?'),
            'raw_payload' => $decoded,
        ]);
    }

    /**
     * IPN feldolgozása (szerver-szerver, POST).
     *
     * Ez a tranzakció végállapotának egyetlen megbízható forrása. Az aláírás
     * ellenőrzése a controllerben történik, ide már ellenőrzött adat érkezik.
     */
    public function handleCallback(array $payload)
    {
        $orderRef = $payload['orderRef'] ?? null;
        $status = SimplepayPaymentService::mapIpnStatus($payload['status'] ?? null);

        return new PaymentCallbackResult([
            'success' => $status === PaymentStatus::PAID,
            'status' => $status,
            'provider' => $this->getCode(),
            'transaction_id' => $orderRef,
            'provider_transaction_id' => $payload['transactionId'] ?? null,
            'message' => 'SimplePay IPN: ' . ($payload['status'] ?? '?'),
            'raw_payload' => $payload,
        ]);
    }

    /**
     * A visszatérítés jelenleg nem része az integrációnak.
     * A SimplePay API támogatja (refund végpont), de admin felület is kellene hozzá.
     */
    public function refund(PaymentRefundData $data)
    {
        return new PaymentRefundResult([
            'success' => false,
            'message' => 'A SimplePay visszatérítés még nem támogatott ebben a verzióban.',
        ]);
    }
}
