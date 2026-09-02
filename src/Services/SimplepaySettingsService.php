<?php

namespace Weboldalnet\CommerceSimplepay\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Weboldalnet\CommerceSimplepay\Models\SimplepaySetting;

/**
 * SimplePay beállítások: az adatbázisban tárolt (adminból szerkesztett) érték az
 * elsődleges, hiányában a config/.env alapértelmezés érvényes.
 *
 * Ugyanaz a minta, mint a commerce-barion és commerce-gls csomagoknál.
 */
class SimplepaySettingsService
{
    protected static $cacheKey = 'commerce_simplepay_settings';
    protected static $typeCacheKey = 'commerce_simplepay_setting_types';

    /**
     * A lapos beállítás-kulcsok leképezése a config útvonalakra.
     */
    protected const CONFIG_PATH_MAP = [
        // A pénztárban megjelenő elnevezés – a config kulcsa hosszabb nevű.
        'payment_method_label' => 'default_payment_method_label',
        'merchant' => 'merchant',
        'secret_key' => 'secret_key',
        'currency' => 'currency',
        'language' => 'language',
        'timeout_minutes' => 'timeout_minutes',
        'ipn_ip_check' => 'ipn_ip_check',
        'log_payloads' => 'log_payloads',
    ];

    /**
     * Az admin beállítófelületen szerkeszthető kulcsok.
     */
    public static function viewKeys(): array
    {
        return [
            'enabled', 'environment', 'payment_method_label',
            'merchant', 'secret_key', 'currency',
            'language', 'timeout_minutes',
            'ipn_ip_check', 'log_payloads',
        ];
    }

    /** Titkosítva tárolandó kulcsok */
    public static function encryptedKeys(): array
    {
        return ['secret_key'];
    }

    /** Logikai (checkbox) kulcsok */
    public static function booleanKeys(): array
    {
        return ['enabled', 'ipn_ip_check', 'log_payloads'];
    }

    public static function all(): array
    {
        try {
            return Cache::rememberForever(self::$cacheKey, function () {
                return SimplepaySetting::all()->pluck('value', 'key')->toArray();
            });
        } catch (\Throwable $e) {
            // A tábla még nem létezik (migráció előtt) – a config érvényes.
            return [];
        }
    }

    protected static function types(): array
    {
        try {
            return Cache::rememberForever(self::$typeCacheKey, function () {
                return SimplepaySetting::all()->pluck('type', 'key')->toArray();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected static function configDefault($key, $default = null)
    {
        $path = self::CONFIG_PATH_MAP[$key] ?? $key;
        $value = config('commerce-simplepay.' . $path);

        if ($value !== null && $value !== '') {
            return $value;
        }

        return $default;
    }

    public static function get($key, $default = null)
    {
        $settings = self::all();
        $hasDbValue = array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '';
        $value = $hasDbValue ? $settings[$key] : self::configDefault($key, $default);

        $type = self::types()[$key] ?? null;

        // A titkosítás csak a DB-ben tárolt értékre vonatkozik, a config/.env értéke nyers.
        if ($hasDbValue && $type === 'encrypted' && $value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e) {
                return $value;
            }
        }

        if ($type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }

    public static function getBool($key, $default = false): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public static function save($key, $value, $type = 'string', $group = 'general'): void
    {
        if ($type === 'encrypted' && $value) {
            $value = Crypt::encryptString($value);
        }

        SimplepaySetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => $group]
        );

        self::clearCache();
    }

    /**
     * Van-e elegendő adat a SimplePay hívásokhoz?
     */
    public static function hasCredentials(): bool
    {
        return (string) self::get('merchant') !== '' && (string) self::get('secret_key') !== '';
    }

    /**
     * 'sandbox' vagy 'live' – minden más értéket sandboxnak tekintünk, hogy egy
     * elgépelés soha ne indítson véletlenül éles fizetést.
     */
    public static function environment(): string
    {
        return self::get('environment', 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    public static function isLive(): bool
    {
        return self::environment() === 'live';
    }

    /**
     * A SimplePay API alap-URL-je a környezet szerint.
     */
    public static function apiBaseUrl(): string
    {
        return (string) config('commerce-simplepay.endpoints.' . self::environment(), '');
    }

    public static function clearCache(): void
    {
        Cache::forget(self::$cacheKey);
        Cache::forget(self::$typeCacheKey);
    }
}
