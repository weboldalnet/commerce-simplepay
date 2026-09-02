<?php

namespace Weboldalnet\PackageTemplate\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Weboldalnet\PackageTemplate\Support\PackageHelper;

class ExtendViewsArticlesCommand extends Command
{
    protected $signature = PackageHelper::PACKAGE_PREFIX . ':extend {--view=}';
    protected $description = 'Fájl kiegészítések';

    public function handle()
    {
        $viewName = $this->option('view');

        $this->info('Fájl kiegészítések feltöltése...');

        if ($viewName == 'all') {
            foreach (PackageHelper::PACKAGE_VIEW_EXTENDS as $viewName => $viewData) {
                $this->addViewInclude($viewData);
            }
        } else {
            $viewData = PackageHelper::PACKAGE_VIEW_EXTENDS[$viewName];
            $this->addViewInclude($viewData);
        }

        $this->info('A kiegészítések elkészültek!');
    }

    protected function addViewInclude($viewData)
    {
        $viewPath = base_path() . $viewData['view_path'];
        $includeLine = $viewData['include'];

        if (!file_exists($viewPath)) {
            $this->warn('Nem található: ' . $viewPath);
            return;
        }

        $content = file_get_contents($viewPath);

        if (strpos($content, $includeLine) !== false) {
            $this->info( 'Már importálva van: ' . $viewData['view_path'] . ' -> ' . $includeLine . '');

            return;
        }

        file_put_contents(
            $viewPath,
            rtrim($content) . PHP_EOL . $includeLine . PHP_EOL
        );

        $this->info('Hozzáadva: ' . $viewData['view_path'] . ' -> ' . $includeLine);
    }
}
