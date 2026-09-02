<?php
/**
 * SimplePay fizetési provider konfiguráció.
 *
 * Csomagváz: a tényleges SimplePay API beállítások (merchant azonosító, secret key,
 * környezet, pénznem) a fizetési funkció fejlesztésekor kerülnek ide.
 */
return [
    'enabled' => env('COMMERCE_SIMPLEPAY_ENABLED', false),

    'provider_code' => 'simplepay',

    'default_payment_method_label' => 'SimplePay bankkártyás fizetés',

    'log_payloads' => env('COMMERCE_SIMPLEPAY_LOG_PAYLOADS', true),
];
