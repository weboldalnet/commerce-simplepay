<?php

namespace Weboldalnet\PackageTemplate\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Weboldalnet\PackageTemplate\Support\PackageHelper;

class InstallArticlesCommand extends Command
{
    protected $signature = PackageHelper::PACKAGE_PREFIX . ':install {--tag=}';
    protected $description = PackageHelper::PACKAGE_NAME . ' fájlok publikálása a projectbe';

    public function handle()
    {
        $tag = $this->option('tag');

        $this->info(PackageHelper::PACKAGE_NAME . ' fájlok publikálása a projectbe...');

        Artisan::call('vendor:publish', [
            '--provider' => 'Weboldalnet\\PackageTemplate\\ArticleServiceProvider',
            '--tag' => $tag,
            '--force' => true,
        ]);

        // 2. SCSS import
        $this->addScssImport();

        $this->info(PackageHelper::PACKAGE_NAME . ' telepítése sikeres!');
    }

    protected function addScssImport()
    {
        $stylesPath = getcwd() . '/public/site/styles.scss';
        $importLine = '@import "pages/blog";';

        if (!file_exists($stylesPath)) {
            $this->warn('styles.scss nem található.');
            return;
        }

        $content = file_get_contents($stylesPath);

        if (strpos($content, $importLine) !== false) {
            $this->info('SCSS már importálva van.');

            $output = null; $retval = null;
            exec('gulp', $output, $retval);
            echo "A gulp lefutott!\n";

            return;
        }

        file_put_contents(
            $stylesPath,
            rtrim($content) . PHP_EOL . $importLine . PHP_EOL
        );

        $this->info('SCSS hozzáadva.');

        $output = null; $retval = null;
        exec('gulp', $output, $retval);
        echo "A gulp lefutott!\n";
    }
}
