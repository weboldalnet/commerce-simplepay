<?php

namespace Weboldalnet\CommerceSimplepay\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Weboldalnet\CommerceSimplepay\Support\PackageHelper;

class InstallCommerceSimplepayCommand extends Command
{
    protected $signature = PackageHelper::PACKAGE_PREFIX . ':install {--tag=}';
    protected $description = PackageHelper::PACKAGE_NAME . ' fájlok publikálása a projectbe';

    public function handle()
    {
        $tag = $this->option('tag');

        if (empty($tag)) {
            $tag = PackageHelper::PACKAGE_PREFIX . '-all';
        }

        $this->info(PackageHelper::PACKAGE_NAME . ' fájlok publikálása a projectbe...');

        Artisan::call('vendor:publish', [
            '--provider' => 'Weboldalnet\\CommerceSimplepay\\CommerceSimplepayServiceProvider',
            '--tag' => $tag,
            '--force' => true,
        ]);

        $this->info(PackageHelper::PACKAGE_NAME . ' telepítése sikeres!');
    }
}
