<?php

namespace App\Utils;

use App\Exceptions\MobilXException;
use App\Exceptions\SlackNotiException;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class AutoParking
{
    private static function client(string $path, array $json = [], string $method = 'POST'): ?array
    {
        try {
            $response = (new Client())->request($method, sprintf('%s%s', env('PROXY_URI'), $path), [
                'headers' => getProxyHeaders(),
                'timeout' => 5,
                'json' => $json,
                'http_errors' => false
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            throw new MobilXException("IF_0001", 9999, null, $e->getMessage());
        }
    }

    public static function parkingLotsList(): ?array
    {
        return self::client(path: env('AUTOPARKING_API_PATH_LIST'), method: 'GET');
    }

    /**
     * @param array $plateNumbers
     * @param bool $regType
     * @return array|null
     * @throws SlackNotiException
     */
    public static function registerCar(array $plateNumbers, bool $regType = true): ?array
    {
        try {
            return self::client(env('AUTOPARKING_API_PATH_REGIST_CAR'), [
                'interfaceCode' => 'IF_0002',
                'carList' => array_map(function ($carNumber) use ($regType) {
                    return [
                        'plateNumber' => $carNumber,
                        'regType' => $regType ? "1" : "0"
                    ];
                }, $plateNumbers)
            ]);
        } catch (Exception $e) {
            $code = $regType ? 'AP0004' : 'AP0005';
            Log::channel('error')->error("[{$code}] auto parking registerCar error", [$e->getMessage()]);
            throw new SlackNotiException(Code::message($code));
        }
    }

    /**
     * @param string $plateNumber
     * @param string $storeId
     * @param string $txId
     * @return array|null
     * @throws SlackNotiException
     */
    public static function checkFee(string $plateNumber, string $storeId, string $txId): ?array
    {
        try {
            return self::client(env('AUTOPARKING_API_PATH_CHECK_FEE'), [
                'interfaceCode' => 'IF_0004',
                'plateNumber' => $plateNumber,
                'storeId' => $storeId,
                'txId' => $txId,
            ]);
        } catch (Exception $e) {
            Log::channel('error')->critical('[AP9012] auto parking checkFee error', [$e->getMessage()]);
            throw new SlackNotiException(Code::message('AP9012'));
        }
    }

    /**
     * @param array $request
     * @return array
     */
    public static function resultPayment(array $request): ?array
    {
        try {
            return self::client(env('AUTOPARKING_API_PATH_PAYMENT'), [
                'interfaceCode'   => 'IF_0006', //인터페이스 코드
                'txId'            => $request['txId'], //no_order
                'storeId'         => $request['storeId'], //주차장 ID
                'storeCategory' => $request['storeCategory'], //주차장 분류
                'plateNumber'     => $request['plateNumber'], //차량번호
                'approvalPrice'   => $request['approvalPrice'] ?? null, //승인금액
                'approvalDate'    => Carbon::createFromFormat('Y-m-d H:i:s', $request['approvalDate'])->format('YmdHis') ?? null, //승인일시
                'approvalNumber'  => $request['approvalNumber'] ?? null, //승인번호
                'approvalResult'  => $request['approvalResult'], //승인 실패/성공
                'approvalMessage' => $request['approvalMessage'], //결과 메시지
            ]);
        } catch (Exception $e) {
            Log::channel('error')->critical('[AP0006] auto parking resultPayment error', [$e->getMessage()]);
            Log::channel('slack')->critical(env('APP_ENV'), [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'time' => now()
            ]);
            return [];
        }
    }

    /**
     * 결제 승인 취소 후 정보 전달
     *
     * @param array $request
     *
     * @return array
     * @throws GuzzleException
     * @throws MobilXException
     */
    public static function refund(array $request): array
    {
        try {
            return self::client(env('AUTOPARKING_API_PATH_REFUND'), [
                'interfaceCode'   => 'IF_0007', //인터페이스 코드
                'txId'            => $request['txId'], //no_order
                'storeId'         => $request['storeId'], //주차장 ID
                'plateNumber'     => $request['plateNumber'], //차량번호
                'cancelPrice'  => $request['cancelPrice'], //승인 실패/성공
                'cancelDate' => $request['cancelDate'], //결과 메시지
            ]);
        } catch (Exception $e) {
            throw new MobilXException("IF_0006", 9999, null, $e->getMessage());
        }
    }

    /**
     * 결제금액 암호화
     * @param $data
     * @return string
     */
    public static function encryptFee($data): string
    {
        $iv = Code::conf('auto_parking.iv');
        $key = Code::conf('auto_parking.encrypt_key');
        return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv));
    }

    /**
     * 결제금액 복호화
     * @param $data
     * @return string
     */
    public static function decryptFee($data): string
    {
        $iv = Code::conf('auto_parking.iv');
        $key = Code::conf('auto_parking.encrypt_key');
        return openssl_decrypt(base64_decode($data), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }
}
