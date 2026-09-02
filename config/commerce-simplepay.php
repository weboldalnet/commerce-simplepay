<?php
/**
 * SimplePay fizetési provider konfiguráció.
 *
 * FONTOS: az itt szereplő értékek csak ALAPÉRTELMEZÉSEK. Az admin felületen
 * (Webshop → SimplePay) megadott – és titkosítva tárolt – beállítások mindig
 * erősebbek, ugyanaz a minta, mint a commerce-barion és commerce-gls csomagoknál.
 */
return [
    'enabled' => env('COMMERCE_SIMPLEPAY_ENABLED', false),

    'provider_code' => 'simplepay',

    /*
    |--------------------------------------------------------------------------
    | Környezet
    |--------------------------------------------------------------------------
    |
    | 'sandbox' vagy 'live'. A két rendszer teljesen elkülönül egymástól: külön
    | kereskedői fiók, külön SECRET_KEY, külön admin felület. Minden más értéket
    | sandboxnak tekintünk, hogy egy elgépelés soha ne indítson éles fizetést.
    |
    */
    'environment' => env('COMMERCE_SIMPLEPAY_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Kereskedői fiók
    |--------------------------------------------------------------------------
    |
    | merchant:   a kereskedői fiók azonosítója (pl. PUBLICTESTHUF)
    | secret_key: a fiókhoz tartozó SECRET_KEY a kereskedői vezérlőpultról
    |
    | A SimplePay devizanemenként külön fiókot ad. Jelenleg egy fiókot kezelünk;
    | több deviza esetén a beállítás-séma bővíthető ugyanezzel a mintával.
    |
    */
    'merchant' => env('COMMERCE_SIMPLEPAY_MERCHANT', ''),
    'secret_key' => env('COMMERCE_SIMPLEPAY_SECRET_KEY', ''),
    'currency' => env('COMMERCE_SIMPLEPAY_CURRENCY', 'HUF'),

    /*
    |--------------------------------------------------------------------------
    | API végpontok
    |--------------------------------------------------------------------------
    |
    | A szolgáltatás neve (start, query, ipn…) ehhez az alap-URL-hez fűződik.
    |
    */
    'endpoints' => [
        'sandbox' => 'https://sandbox.simplepay.hu/payment/v2/',
        'live' => 'https://secure.simplepay.hu/payment/v2/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fizetőoldal
    |--------------------------------------------------------------------------
    |
    | language:       a fizetőoldal nyelve (HU, EN, DE…)
    | timeout_minutes: eddig lehet elkezdeni a fizetést a tranzakció indítása után
    |
    */
    'language' => env('COMMERCE_SIMPLEPAY_LANGUAGE', 'HU'),
    'timeout_minutes' => env('COMMERCE_SIMPLEPAY_TIMEOUT_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | IPN forrás-ellenőrzés
    |--------------------------------------------------------------------------
    |
    | A SimplePay a dokumentációban közölt tartományokból küld IPN-t. Az aláírás
    | ellenőrzése önmagában is elegendő védelem, ezért az IP-szűrés alapból ki van
    | kapcsolva – proxy vagy CDN mögött ugyanis téves elutasításhoz vezetne.
    |
    */
    'ipn_ip_check' => env('COMMERCE_SIMPLEPAY_IPN_IP_CHECK', false),
    'ipn_allowed_ranges' => [
        '80.249.162.112/28',
        '84.2.229.128/27',
        '195.228.18.224/29',
    ],

    /*
    |--------------------------------------------------------------------------
    | Azonosítók (nem admin-szerkeszthetők)
    |--------------------------------------------------------------------------
    |
    | A provider_code a rendelésekben tárolt fizetési mód kódja – megváltoztatása
    | a már leadott rendeléseket tenné felismerhetetlenné.
    |
    */
    'default_payment_method_label' => 'SimplePay bankkártyás fizetés',

    /*
    | A SimplePay kéri, hogy a kereskedői rendszer azonosítsa magát a start
    | hívásban (sdkVersion mező).
    */
    'sdk_version' => 'Weboldalnet_Commerce_SimplePay_1.0',

    'log_payloads' => env('COMMERCE_SIMPLEPAY_LOG_PAYLOADS', true),
];
