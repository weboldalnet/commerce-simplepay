<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SimplePay fizetési modul útvonalai
|--------------------------------------------------------------------------
|
| Csomagváz: a visszatérő és callback (IPN) útvonalak a fizetési funkció
| fejlesztésekor kerülnek ide, a commerce-barion mintájára:
|
| Route::middleware(['web'])->group(function () {
|     Route::get('/commerce/simplepay/return', [SimplepayCallbackController::class, 'handleReturn'])
|         ->name('commerce.simplepay.return');
|     Route::post('/commerce/simplepay/callback', [SimplepayCallbackController::class, 'handleCallback'])
|         ->name('commerce.simplepay.callback');
| });
|
*/
