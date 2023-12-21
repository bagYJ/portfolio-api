<?php
declare(strict_types=1);

namespace App\Utils;

use GuzzleHttp\Client;

class Pg
{
    private static function client(string $path, array $json = [], string $method = 'POST'): ?array
    {
        $response = (new Client())->request($method, sprintf('%s%s', env('PROXY_URI'), $path), [
            'headers' => getProxyHeaders(),
            'timeout' => 5,
            'json' => $json,
            'http_errors' => false
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public static function regist(array $parameters): ?array
    {
        return self::client(env('PG_API_PATH_REGIST'), $parameters);
    }

    public static function payment(array $parameters): ?array
    {
        return self::client(env('PG_API_PATH_PAYMENT'), $parameters);
    }

    public static function refund(array $parameters): ?array
    {
        return self::client(env('PG_API_PATH_REFUND'), $parameters);
    }
}
