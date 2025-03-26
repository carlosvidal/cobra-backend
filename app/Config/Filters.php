<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\SecureHeaders;
use App\Filters\CorsFilter;
use App\Filters\AuthFilter;
use App\Filters\ApiAuthFilter;
use App\Filters\ApiLogFilter;

class Filters extends BaseConfig
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     */
    public array $aliases = [
        'csrf'          => \CodeIgniter\Filters\CSRF::class,
        'toolbar'       => \CodeIgniter\Filters\DebugToolbar::class,
        'honeypot'      => \CodeIgniter\Filters\Honeypot::class,
        'invalidchars'  => \CodeIgniter\Filters\InvalidChars::class,
        'secureheaders' => \CodeIgniter\Filters\SecureHeaders::class,
        'cors'          => \App\Filters\CorsFilter::class,
        'auth'          => \App\Filters\AuthFilter::class,
        'apiAuth'       => \App\Filters\ApiAuthFilter::class,
        'apiLog'        => \App\Filters\ApiLogFilter::class,
        // Combined filter aliases
        'api-public'    => ['cors'],
        'api-auth'      => ['cors', 'apiAuth', 'apiLog'],
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     */
    public array $globals = [
        'before' => [
            'honeypot',
            'csrf' => ['except' => [
                'api/*',
                'api',
                'clients/import*',
                'invoices/import*',
                'login*',
                'logout'
            ]],
            'invalidchars',
        ],
        'after' => [
            'toolbar',
            'honeypot',
            'secureheaders',
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'post' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     */
    public array $filters = [
        'auth' => [
            'before' => ['dashboard', 'dashboard/*', 'organizations/*', 'clients/*', 'invoices/*', 'users/*', 'profile/*', 'portfolios/*', 'payments/*', 'webhooks/*'],
            'except' => [
                'clients/import*',
                'invoices/import*'
            ]
        ],
    ];
}