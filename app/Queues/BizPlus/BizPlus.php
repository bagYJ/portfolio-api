<?php

namespace App\Queues\BizPlus;

use App\Exceptions\OwinException;
use App\Exceptions\SlackNotiException;
use App\Models\AlimtalkLog;
use App\Utils\Code;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 문자메시지, 알림톡
 */
class BizPlus
{
    private string $schema;
    private string $accessToken;
    private string $phoneNum;
    private string $templateCode;
    private string $content;

    /**
     * @throws OwinException
     * @throws GuzzleException
     */
    public function __construct(string $phoneNum, string $templateCode, array $message)
    {
        self::token();

        $this->phoneNum = sprintf(Code::bizPlus('phone_prefix'), substr($phoneNum, 1));
        $this->templateCode = $templateCode;
        $this->content = sprintf(Code::bizPlus(sprintf('template.%s.content', $templateCode)), ...$message);
    }

    /**
     * @throws GuzzleException
     * @throws OwinException
     */
    public function token(): void
    {
        $response = (new Client())->post(Code::bizPlus('token'), [
            'headers' => [
                'X-IB-Client-Id' => Code::bizPlus('client_id'),
                'X-IB-Client-Passwd' => Code::bizPlus('client_password'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'http_errors' => false
        ]);
        $data = json_decode($response->getBody());

        match ($response->getStatusCode()) {
            200 => (function () use ($data) {
                $this->accessToken = $data->accessToken;
                $this->schema = $data->schema;
            })(),
            default => (function () use ($data) {
                Log::channel('error')->error('biz plus token error::', [
                    'status' => $data->status,
                    'message' => $data->text
                ]);

                throw new SlackNotiException(Code::message('09000'));
            })()
        };
    }

    /**
     * @throws GuzzleException
     * @throws OwinException|Throwable
     */
    public function sendKakao(): object
    {
        $request = $this->getRequest();
        $response = (new Client())->post(Code::bizPlus('send_kakao'), [
            'headers' => [
                'Authorization' => sprintf('%s %s', $this->schema, $this->accessToken),
                'Content-Type' => 'application/json;charset=UTF-8',
                'Accept' => 'application/json'
            ],
            'json' => $request,
            'http_errors' => false
        ]);
        $data = json_decode($response->getBody());

        (new AlimtalkLog([
            'ds_phone' => $this->phoneNum,
            'cd_templates' => $this->templateCode,
            'ds_messageid' => $data?->messageId,
            'ds_request' => json_encode($request),
            'ds_response' => json_encode($data)
        ]))->saveOrFail();

        return match ($response->getStatusCode()) {
            200 => $data,
            default => (function () use ($data) {
                Log::channel('error')->error('biz plus sendKakao error::', [
                    'status' => $data->status,
                    'message' => $data->text
                ]);

                throw new SlackNotiException(Code::message('09001'));
            })()
        };
    }

    private function getRequest(): array
    {
        return [
            'msg_type' => 'AL',
            'mt_failover' => 'N',
            'msg_data' => [
                'senderid' => Code::bizPlus('sender_id'),
                'to' => $this->phoneNum,
                'content' => $this->content
            ],
            'msg_attr' => [
                'sender_key' => Code::bizPlus('sender_key'),
                'template_code' => $this->templateCode,
                'response_method' => 'push',
            ]
        ];
    }
}