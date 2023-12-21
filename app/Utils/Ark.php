<?php
declare(strict_types=1);

namespace App\Utils;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

class Ark
{
    public static function client(string $path, array $json = [], string $method = 'POST'): ?array
    {
        try {
            $response = (new Client())->request($method, sprintf('%s%s', env('PROXY_URI'), $path), [
                'headers' => getProxyHeaders(),
                'timeout' => 5,
                'json' => $json,
                'http_errors' => false
            ]);
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'result' => data_get($data, 'code') == '0000',
                ...$data
            ];
        } catch (Throwable $t) {
            Log::channel('error')->error($t->getMessage(), [$t->getFile(), $t->getLine(), $t->getTraceAsString()]);
            return null;
        }
    }
}
