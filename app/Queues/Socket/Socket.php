<?php

declare(strict_types=1);

namespace App\Queues\Socket;

use App\Exceptions\SlackNotiException;
use App\Utils\Code;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class Socket
{
    protected string $ip;
    protected int $port;

    public function send(string $parameter): ?string
    {
        Log::channel('request')->info(sprintf('IP: %s:%s, arkserver request: %s', $this->ip, $this->port, base64_encode($parameter)));
        $response = (new Client())->post(sprintf('%s%s', Code::conf('gs-proxy.ip'), Code::conf('gs-proxy.path.socket')), [
            'form_params' => [
                'ip' => $this->ip,
                'port' => $this->port,
                'parameter' => base64_encode($parameter)
            ],
            'http_errors' => false
        ]);
        $result = json_decode($response->getBody()->getContents());
        Log::channel('response')->info('arkserver response: ', (array)$result);

        return match ($result->code) {
            '0000' => base64_decode($result->message),
            default => throw new SlackNotiException(sprintf(Code::message($result->code), $result->message))
        };
    }
}
