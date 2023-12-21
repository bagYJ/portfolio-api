<?php

declare(strict_types=1);

namespace App\Utils;

use GuzzleHttp\Client;

class BizCall
{
    private static function client(string $path, array $json = [], string $method = 'POST'): array
    {
        $response = (new Client())->request($method, sprintf('%s%s', env('PROXY_URI'), $path), [
            'headers' => getProxyHeaders(),
            'timeout' => 5,
            'json' => $json,
            'http_errors' => false
        ]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @return array
     */
    public static function getVns(): array
    {
        return self::client(path: env('VIRTUAL_NUMBER_API_PATH_LIST'), method: 'GET');
    }

    /**
     * @param string $realNumber
     * @param string|null $cdBizKind
     * @param string|null $noOrder
     * @return array
     */
    public static function autoMapping(string $realNumber, ?string $cdBizKind = null, ?string $noOrder = null): array
    {
        return self::client(env('VIRTUAL_NUMBER_API_PATH_AUTO_MAPPING'), [
            'real_number' => $realNumber,
            'cd_biz_kind' => $cdBizKind,
            'no_order' => $noOrder,
        ]);
    }

    /**
     * @param $data
     * @return array
     */
    public static function setVn($data): array
    {
        return self::client(path: sprintf(env('VIRTUAL_NUMBER_API_PATH_CLOSE_MAPPING'), data_get($data, 'virtualNumber')), method: 'PUT');
    }
}
