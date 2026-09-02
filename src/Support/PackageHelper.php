<?php

namespace Weboldalnet\PackageTemplate\Support;

class PackageHelper
{
    const PACKAGE_NAME = 'Blog/Cikkek modul';
    const PACKAGE_PREFIX = 'article';

    const PACKAGE_LIST = [
        'app' => [
            'name' => 'app | app/',
            'source' => __DIR__.'/../../app',
            'destination' => '/app',
        ],
        'database' => [
            'name' => 'database | database/migrations',
            'source' => __DIR__.'/../../database/migrations',
            'destination' => '/database/migrations',
        ],
        'public' => [
            'name' => 'public | public/js,site',
            'source' => __DIR__.'/../../public',
            'destination' => '/public',
        ],
        'views' => [
            'name' => 'views | resources/views',
            'source' => __DIR__.'/../../resources/views',
            'destination' => '/resources/views',
        ],
    ];

    const PACKAGE_VIEW_EXTENDS = [
        'sidebar' => [
            'view_path' => '/resources/views/admin/package-container/admin-p-sidebar.blade.php',
            'include' => "@include('" . self::PACKAGE_PREFIX . "::admin.sidebar')"
        ],
        'package-settings' => [
            'view_path' => '/resources/views/admin/package-settings/package-settings-container.blade.php',
            'include' => "@include('" . self::PACKAGE_PREFIX . "::admin.package-functions')"
        ],
    ];
}