<?php

use Illuminate\Support\Facades\Route;
use Weboldalnet\CommerceSimplepay\Http\Controllers\SimplepayCallbackController;
use Weboldalnet\CommerceSimplepay\Http\Controllers\Admin\SimplepaySettingController;

/*
|--------------------------------------------------------------------------
| SimplePay fizetési modul útvonalai
|--------------------------------------------------------------------------
*/

// A vásárló visszatérése a fizetőoldalról: böngészőben történik, kell hozzá
// session (átirányítás, flash üzenet), ezért a "web" csoportban van.
Route::middleware(['web'])->group(function () {
    Route::get('/commerce/simplepay/return', [SimplepayCallbackController::class, 'handleReturn'])
        ->name('commerce.simplepay.return');
});

// IPN: szerver-szerver POST a SimplePay rendszeréből.
//
// FIGYELEM: szándékosan NINCS "web" middleware rajta. A "web" csoport CSRF-token
// ellenőrzést végez, amit egy külső szerver nem tud teljesíteni – a hívás 419-cel
// elszállna, a SimplePay pedig 3 napon át újrapróbálná. Sessionre sincs szükség.
// Az üzenet hitelesítését az aláírás-ellenőrzés adja a controllerben.
Route::post('/commerce/simplepay/ipn', [SimplepayCallbackController::class, 'handleIpn'])
    ->name('commerce.simplepay.ipn');

// FIGYELEM: a platformon 'admin_share' a middleware alias, nem 'admin'.
Route::domain(getAdminDomain())
    ->middleware(['web', 'admin_share', 'auth:admin'])
    ->prefix('webshop/simplepay')
    ->name('admin.webshop.simplepay.')
    ->group(function () {
        Route::get('/settings', [SimplepaySettingController::class, 'index'])->name('settings');
        Route::post('/settings', [SimplepaySettingController::class, 'update'])->name('settings.update');
        Route::post('/test-connection', [SimplepaySettingController::class, 'testConnection'])->name('test-connection');
    });
