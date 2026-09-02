<?php

namespace Weboldalnet\CommerceSimplepay;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Weboldalnet\CommerceCore\Managers\PaymentManager;
use Weboldalnet\CommerceCore\Services\ProviderLogger;
use Weboldalnet\CommerceSimplepay\Providers\SimplepayPaymentProvider;
use Weboldalnet\CommerceSimplepay\Services\SimplepayClient;
use Weboldalnet\CommerceSimplepay\Services\SimplepayPaymentService;
use Weboldalnet\CommerceSimplepay\Services\SimplepaySettingsService;
use Weboldalnet\CommerceSimplepay\Support\PackageHelper;
use Weboldalnet\CommerceSimplepay\Console\ExtendViewsCommerceSimplepayCommand;
use Weboldalnet\CommerceSimplepay\Console\InstallCommerceSimplepayCommand;

class CommerceSimplepayServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // route-ok és admin nézetek
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../settings/views', PackageHelper::PACKAGE_PREFIX);

        // migrációk (a csomag maga tölti be, ahogy a testvércsomagok is)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Provider regisztráció a commerce-core-ba.
        try {
            $paymentManager = $this->app->make(PaymentManager::class);
            $code = config('commerce-simplepay.provider_code', 'simplepay');

            // Telepített integrációként mindig bejelentkezünk, hogy a webshop
            // beállítófelületén akkor is látszódjon (és onnan visszakapcsolható
            // legyen), ha a modul épp ki van kapcsolva.
            $paymentManager->registerAvailable($code, [
                'name' => (string) SimplepaySettingsService::get('payment_method_label', 'SimplePay bankkártyás fizetés'),
                'settings_url' => '/webshop/simplepay/settings',
                'settings_label' => 'SimplePay',
                'online' => true,
            ]);

            if (SimplepaySettingsService::getBool('enabled', false)) {
                $paymentManager->register($code, $this->app->make(SimplepayPaymentProvider::class));
            }
        } catch (\Throwable $e) {
            Log::error('SimplePay regisztrációs hiba: ' . $e->getMessage());
        }

        $publishList = [];
        foreach (PackageHelper::PACKAGE_LIST as $name => $publish) {
            $this->publishes([
                $publish['source'] => base_path($publish['destination']),
            ], PackageHelper::PACKAGE_PREFIX . '-' . $name);

            $publishList[$publish['source']] = base_path($publish['destination']);
        }

        $this->publishes($publishList, PackageHelper::PACKAGE_PREFIX . '-all');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/commerce-simplepay.php', 'commerce-simplepay');

        $this->app->singleton(SimplepaySettingsService::class, function ($app) {
            return new SimplepaySettingsService();
        });

        $this->app->singleton(SimplepayClient::class, function ($app) {
            $logger = null;
            try {
                $logger = $app->make(ProviderLogger::class);
            } catch (\Throwable $e) {
                // A naplózás hiánya ne akadályozza a fizetést.
            }

            return new SimplepayClient($logger);
        });

        $this->app->singleton(SimplepayPaymentService::class, function ($app) {
            return new SimplepayPaymentService($app->make(SimplepayClient::class));
        });

        $this->app->singleton(SimplepayPaymentProvider::class, function ($app) {
            return new SimplepayPaymentProvider($app->make(SimplepayPaymentService::class));
        });

        $this->commands([
            InstallCommerceSimplepayCommand::class,
            ExtendViewsCommerceSimplepayCommand::class,
        ]);
    }
}
