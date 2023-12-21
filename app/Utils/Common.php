<?php

declare(strict_types=1);

namespace App\Utils;

use App\Enums\AppType;
use Carbon\Carbon;
use Owin\OwinCommonUtil\Enums\ServiceCodeEnum;

class Common
{

    /**
     * return resource image path
     * @param string|null $path
     * @param string|null $prefix
     * @return string|null
     */
    public static function getImagePath(?string $path, ?string $prefix = ''): ?string
    {
        if ($path) {
            if (str_starts_with($path, 'http')) {
                return $path;
            } else {
                return Code::conf('image_path') . $prefix . $path;
            }
        }
        return null;
    }


    /**
     * 할인률 변환 (무조건 내림으로 처리)
     * @param float|int|null $before
     * @param float|int|null $after
     * @return float|int
     */
    public static function getSaleRatio(float|int|null $before, float|int|null $after): float|int
    {
        // 할인가격이 정상인경우
        return match ($before && $before > $after) {
            true => floor(($before - $after) / $before * 100),
            default => 0
        };
    }

    /**
     * 월 주차 구하기
     * @param string $date
     * @return int
     */
    public static function getWeekByMonth(string $date): int
    {
        $w = Carbon::createFromFormat('Y-m-d', Carbon::parse($date)->format('Y-m-d'))->startOfMonth()->format('w');
        return intval(ceil(((int)$w + (int)date('j', strtotime($date)) - 1) / 7));
    }

    /**
     * 할인율 계산
     * @param int $price
     * @param int $rate
     * @return float
     */
    public static function getDiscountRate(int $price, int $rate): float
    {
        return round($price * ($rate * 0.01));
    }

    /**
     * 14자리 회원번호 생성
     * @return int
     */
    public static function generateNoUser(): int
    {
        // timestemp(10)  + rand(4) 16자리
        return (int)((time() + 1000000000) . mt_rand(100000, 999999));
    }

    /**
     * 요일 한글로 리턴
     * @param $weekday
     *
     * @return string
     */
    public static function getWeekDay($weekday): string
    {
        return match($weekday) {
            0 => '월요일',
            1 => '화요일',
            2 => '수요일',
            3 => '목요일',
            4 => '금요일',
            5 => '토요일',
            6 => '일요일',
            default => ''
        };
    }

    /**
     * xml => json
     * @param $data
     * @return mixed
     */
    public static function xmlToJson($data)
    {
        $xml = simplexml_load_string((string)$data, "SimpleXMLElement", LIBXML_NOCDATA);
        return json_decode(json_encode($xml, JSON_UNESCAPED_UNICODE), true);
    }

    /**
     * 쿠폰번호 생성
     * @return string
     */
    public static function getMakeCouponNo(): string
    {
        return substr((string)time(), 0, 3) . substr(microtime(), 2, 5) . sprintf('%03d', mt_rand(0,999));
    }

    /**
     * ServiceCodeEnum 과 AppType 매칭
     * @param ServiceCodeEnum $serviceCodeEnum
     * @return AppType
     */
    public static function getAppTypeFromServiceCodeEnum(ServiceCodeEnum $serviceCodeEnum): AppType {
        return match ($serviceCodeEnum) {
            ServiceCodeEnum::GTCS => AppType::GTCS,
            ServiceCodeEnum::RENAULT => AppType::AVN,
            ServiceCodeEnum::TMAP => AppType::TMAP_AUTO,
            default => AppType::OWIN
        };
    }
}
