<?php

namespace App\Services\Gs;

use App\Exceptions\OwinException;
use App\Utils\Code;
use App\Utils\Common;
use App\Utils\Encrypt;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class GsService
{
    /**
     * 쿠폰 발급
     *
     * @param string $interworkCode
     * @param string $paymentNo : 발급 번호
     *
     * @return array|null
     */
    public static function issue(string $interworkCode, string $paymentNo): ?array
    {
        $parameter = 'Req_Div_Cd=01&Issu_Req_Val=' . $interworkCode . '&Clico_Issu_Paym_No=' . $paymentNo . '&Clico_Issu_Paym_Seq=1&Cre_Cnt=1&Avl_Div_Cd=02';
        $encrypt = Encrypt::encrypt($parameter, Code::conf('nusoap.gs_key'), Code::conf('nusoap.gs_iv'));

        try {
            $response = self::httpConnect('CouponIssue', $encrypt);
            $data = Common::xmlToJson($response->getBody()->getContents());
            if ($response->getStatusCode() === 200) {
                if ($data) {
                    $result = [
                        'returnCode' => $data['return']['returnCode'],
                        'returnMsg' => $data['return']['returnMsg'],
                        'Issu_Req_Val' => $interworkCode,
                        'couponInfo' => match (empty($data['return']['couponInfo'])) {
                            false => [
                                'avl_Start_Dy' => $data['return']['couponInfo']['avl_Start_Dy'],
                                'avl_End_Dy' => $data['return']['couponInfo']['avl_End_Dy'],
                                'cupn_No' => Encrypt::decrypt($data['return']['couponInfo']['cupn_No'], Code::conf('nusoap.gs_key'), Code::conf('nusoap.gs_iv')),
                            ],
                            default => []
                        }
                    ];
                    Log::channel('response')->info('gs coupon issue response: ', $data);
                    return $result;
                }
            } else {
                Log::channel('error')->error('gs coupon issue error: ', $data);
                throw new OwinException(Code::message('P2300'));
            }
            return null;
        } catch (Exception $e) {
            Log::channel('error')->error('gs coupon issue error: ', ['message' => $e->getMessage()]);
            throw new OwinException(Code::message('P2300'));
        }
    }

    /**
     * 쿠폰 조회
     *
     * @param string $interworkCode
     * @param string $couponNo
     *
     * @return array|null
     */
    public static function search(string $interworkCode, string $couponNo): ?array
    {
        $parameter = 'Req_Div_Cd=01&Issu_Req_Val=' . $interworkCode . '&Search_Div=01&Receive_Div=99&Cupn_No=' . $couponNo;
        $encrypt = Encrypt::encrypt($parameter, Code::conf('nusoap.gs_key'), Code::conf('nusoap.gs_iv'));

        try {
            $response = self::httpConnect('CouponSearch', $encrypt);
            $data = Common::xmlToJson($response->getBody()->getContents());
            if ($response->getStatusCode() === 200) {
                if ($data) {
                    parse_str(Encrypt::decrypt($data['return']['encOut'], Code::conf('nusoap.gs_key'), Code::conf('nusoap.gs_iv')), $couponInfo);
                    $result = [
                        'returnCode' => $data['return']['returnCode'],
                        'returnMsg' => $data['return']['returnMsg'],
                        'couponInfo' => [
                            'REQDOC_IDX' => $couponInfo['REQDOC_IDX'],
                            'CUPN_NO' => $couponInfo['CUPN_NO'],
                            'PROD_CD' => $couponInfo['PROD_CD'],
                            'PROD_NM' => $couponInfo['PROD_NM'],
                            'MCHT_CD' => $couponInfo['MCHT_CD'],
                            'MCHT_NM' => $couponInfo['MCHT_NM'],
                            'USE_YN' => $couponInfo['USE_YN'],
                            'USE_DT' => $couponInfo['USE_DT'],
                            'ISSU_CNCL_YN' => $couponInfo['ISSU_CNCL_YN'],
                            'ISSU_CNCL_DT' => $couponInfo['ISSU_CNCL_DT'],
                            'AVL_START_DY' => $couponInfo['AVL_START_DY'],
                            'AVL_END_DY' => $couponInfo['AVL_END_DY'],
                            'CUPN_RAMT' => $couponInfo['CUPN_RAMT'],
                            'REG_DT' => $couponInfo['REG_DT'],
                            'STATE' => $couponInfo['STATE'],
                            'FAMT_AMT' => $couponInfo['FAMT_AMT'],
                            'TOT_USABLE_CNT' => $couponInfo['TOT_USABLE_CNT'],
                            'USE_UNIT_AMT' => $couponInfo['USE_UNIT_AMT'],
                            'USE_CNT' => $couponInfo['USE_CNT'],
                            'USE_AMT' => $couponInfo['USE_AMT'],
                        ]
                    ];
                    Log::channel('response')->info('gs coupon search response: ', $data);
                    return $result;
                }
            } else {
                Log::channel('error')->error('gs coupon search error: ', $data);
                throw new OwinException(Code::message('P2300'));
            }
            return null;
        } catch (Exception $e) {
            Log::channel('error')->error('gs coupon search error: ', ['message' => $e->getMessage()]);
            throw new OwinException(Code::message('P2300'));
        }
    }

    public static function cancel($interworkCode, $couponNo)
    {
        $parameter = 'Req_Div_Cd=01&Issu_Req_Val=' . $interworkCode . '&Cncl_Req_Div=01&Cupn_No=' . $couponNo;
        $encrypt = Encrypt::encrypt($parameter, Code::conf('nusoap.gs_key'), Code::conf('nusoap.gs_iv'));

        try {
            $response = self::httpConnect('CouponCancel', $encrypt);
            $data = Common::xmlToJson($response->getBody()->getContents());
            if ($response->getStatusCode() === 200) {
                return [
                    'returnCode' => $data['return']['returnCode'],
                    'returnMsg' => $data['return']['returnMsg'],
                    'cancelDate' => Encrypt::decrypt($data['return']['encOut']['Issu_Cncl_Dt'], Code::conf('nusoap.gs_key'), Code::conf('nusoap.gs_iv')),
                ];
            } else {
                Log::channel('error')->error('gs coupon cancel error: ', $data);
                throw new OwinException(Code::message('P2300'));
            }
        } catch (Exception $e) {
            Log::channel('error')->error('gs coupon cancel error: ', ['message' => $e->getMessage()]);
            throw new OwinException(Code::message('P2300'));
        }
    }

    private static function httpConnect(string $url, string $encStr): ResponseInterface
    {
        return (new Client())->post(sprintf('%s%s', Code::conf('gs-proxy.ip'), Code::conf('gs-proxy.path.coupon')), [
            'form_params' => [
                'url' => Code::conf('nusoap.gs_wsdl') . '/' . $url,
                'headers' => base64_encode(json_encode([
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ])),
                'query' => base64_encode(json_encode([
                    'Clico_Cd' => Code::conf('nusoap.gs_company_code'),
                    'EncStr' => $encStr
                ]))
            ]
        ]);
    }
}
