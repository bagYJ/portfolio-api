<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['error', 'slack'],
            'ignore_exceptions' => false,
        ],

        'error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/error/error.log'),
            'tap' => [App\Logging\CustomizeFormatter::class],
            'level' => 'error',
            'days' => 60
        ],

        'pg' => [
            'driver' => 'daily',
            'path' => storage_path('logs/pg/pg.log'),
            'tap' => [App\Logging\CustomizeFormatter::class],
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 60
        ],

        'sql' => [
            'driver' => 'daily',
            'path' => storage_path('logs/sql/sql.log'),
            'tap' => [App\Logging\CustomizeFormatter::class],
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60
        ],

        'spc' => [
            'driver' => 'daily',
            'path' => storage_path('logs/spc/spc.log'),
            'tap' => [App\Logging\CustomizeFormatter::class],
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'tap' => [App\Logging\CustomizeFormatter::class],
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'request' => [
            'driver' => 'daily',
            'path' => storage_path('logs/request/request.log'),
            'tap' => [App\Logging\CustomizeFormatter::class],
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],

        'response' => [
            'driver' => 'daily',
            'path' => storage_path('logs/response/response.log'),
            'tap' => [App\Logging\CustomizeFormatter::class],
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],

        'cu' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cu/cu.log'),
            'tap' => [App\Logging\CustomizeFormatter::class],
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 14,
        ],

        'auto-parking' => [
            'driver' => 'daily',
            'path' => storage_path('logs/auto-parking/auto-parking.log'),
            'tap' => [App\Logging\CustomizeFormatter::class],
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],

        'parking' => [
            'driver' => 'daily',
            'path' => storage_path('logs/parking/parking.log'),
            'tap' => [App\Logging\CustomizeFormatter::class],
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/request.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 60,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => 'critical',
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://' . env('PAPERTRAIL_URL') . ':' . env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],

];