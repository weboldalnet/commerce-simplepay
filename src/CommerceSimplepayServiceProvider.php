<?php

namespace Weboldalnet\CommerceSimplepay;

use Illuminate\Support\ServiceProvider;
use Weboldalnet\CommerceSimplepay\Support\PackageHelper;
use Weboldalnet\CommerceSimplepay\Console\ExtendViewsCommerceSimplepayCommand;
use Weboldalnet\CommerceSimplepay\Console\InstallCommerceSimplepayCommand;

class CommerceSimplepayServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // route-ok
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../settings/views', PackageHelper::PACKAGE_PREFIX);

        // migrációk
        //$this->loadMigrationsFrom(__DIR__.'/../database/migrations');

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

        $this->commands([
            InstallCommerceSimplepayCommand::class,
        ]);

        $this->commands([
            ExtendViewsCommerceSimplepayCommand::class,
        ]);
    }
}
