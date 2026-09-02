<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SimplePay beállítások tábla.
 *
 * A kereskedői fiók (merchant, SECRET_KEY) és a fizetőoldal viselkedése az admin
 * felületről is megadható legyen, ne csak .env-ből – így éles környezetben nem
 * kell fájlhoz nyúlni. A SECRET_KEY titkosítva tárolódik.
 *
 * Ugyanaz a séma, mint a commerce-barion és commerce-gls csomagoknál.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('public.commerce_simplepay_settings')) {
            Schema::create('public.commerce_simplepay_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                // string, boolean, integer, json, encrypted
                $table->string('type')->default('string');
                $table->string('group')->default('general');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public.commerce_simplepay_settings');
    }
};
