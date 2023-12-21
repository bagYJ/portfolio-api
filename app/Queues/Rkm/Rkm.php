<?php
declare(strict_types=1);

namespace App\Queues\Rkm;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class Rkm
{
    private string $vin;
    private string $title;
    private string $body;
    private string $uri;
    private string $key;
    private string $type = 'I';

    /**
     * @param string $vin
     * @param string $title
     * @param string $body
     */
    public function __construct(string $vin, string $title, string $body)
    {
        $this->vin = $vin;
        $this->title = $title;
        $this->body = $body;
        $this->uri = env('RKM_PUSH_URL');
        $this->key = env('RKM_PUSH_KEY');
    }

    /**
     * @throws GuzzleException
     */
    public function init(): void
    {
        Log::channel('request')->info('rkm push request : ', (array)$this);

        $response = (new Client())->request('POST', $this->uri, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'AppPushKey' => $this->key
            ],
            'form_params' => [
                'targetVin' => $this->vin,
                'pTitle' => $this->title,
                'pBody' => $this->body,
                'type' => $this->type
            ]
        ])->getBody()->getContents();

        Log::channel('response')->info('rkm push response : ', [$response]);
    }
}
