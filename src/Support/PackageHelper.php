<?php

namespace Weboldalnet\CommerceSimplepay\Support;

class PackageHelper
{
    const PACKAGE_NAME = 'SimplePay fizetési modul';
    const PACKAGE_PREFIX = 'commerce-simplepay';

    const PACKAGE_LIST = [
        'routes' => [
            'name' => 'routes | routes/web.php',
            'source' => __DIR__.'/../../routes/web.php',
            'destination' => '/routes/commerce-simplepay.php',
        ],
        'settings' => [
            'name' => 'settings | settings/',
            'source' => __DIR__.'/../../settings',
            'destination' => '/settings/commerce-simplepay',
        ],
        'config' => [
            'name' => 'config | config/commerce-simplepay.php',
            'source' => __DIR__.'/../../config/commerce-simplepay.php',
            'destination' => '/config/commerce-simplepay.php',
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
