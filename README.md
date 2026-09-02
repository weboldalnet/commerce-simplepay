# SimplePay fizetési provider a commerce-core-hoz

Ez a csomag a SimplePay (OTP) fizetési kapu integrációját adja a `weboldalnet/commerce-core` alapú rendszerekhez.

> **Állapot:** csomagváz. A struktúra és az elnevezések készen állnak (composer autoload,
> service provider, publish/extend parancsok, config), a tényleges SimplePay API integráció
> (`PaymentProviderInterface` implementáció, callback kezelés, admin felület, útvonalak)
> még nincs megírva.

## Telepítés

A projekt `composer.json`-jában:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/weboldalnet/commerce-simplepay"
    }
]
```

```bash
composer require weboldalnet/commerce-simplepay:^1.0
```

A service provider Laravel package auto-discovery-vel regisztrálódik
(`Weboldalnet\CommerceSimplepay\CommerceSimplepayServiceProvider`).

## Konfiguráció

Publikálás a projektbe:

```bash
php artisan commerce-simplepay:install --tag=commerce-simplepay-all
php artisan commerce-simplepay:extend --view=all
```

Publikálható tagek:

| tag | tartalom |
| --- | --- |
| `commerce-simplepay-routes` | `routes/web.php` → `routes/commerce-simplepay.php` |
| `commerce-simplepay-settings` | `settings/` → `settings/commerce-simplepay` |
| `commerce-simplepay-config` | `config/commerce-simplepay.php` |
| `commerce-simplepay-all` | mindegyik |

`.env` beállítások:

```env
COMMERCE_SIMPLEPAY_ENABLED=false
COMMERCE_SIMPLEPAY_LOG_PAYLOADS=true
```

## Névterek és fájlszerkezet

```
src/CommerceSimplepayServiceProvider.php             – service provider (publish, route, view betöltés)
src/Console/InstallCommerceSimplepayCommand.php      – commerce-simplepay:install
src/Console/ExtendViewsCommerceSimplepayCommand.php  – commerce-simplepay:extend
src/Support/PackageHelper.php                        – publish lista és view kiegészítések
config/commerce-simplepay.php                        – konfiguráció
routes/web.php                                       – útvonalak (egyelőre üres váz)
settings/views/admin/                                – admin sidebar és package-functions blade
```
