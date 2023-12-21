<?php

declare(strict_types=1);

namespace App\Utils;

use GuzzleHttp\Client;

class Parking
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
     * @param string $noSite
     * @return array|null
     */
    public static function getParkingSite(string $noSite): ?array
    {
        return self::client(path: sprintf(env('PARKING_API_PATH_SITE'), $noSite), method: 'GET');
    }

    /**
     * @param int $ticketUid
     * @param string $carPlate
     * @param int $noUser
     * @return ?array
     */
    public static function setTicket(int $ticketUid, string $carPlate, int $noUser): ?array
    {
        return self::client(env('PARKING_API_PATH_BOOKINGS'), [
            'ticketUid' => $ticketUid,
            'carPlate' => $carPlate,
            'userCode' => $noUser
        ]);
    }

    /**
     * @param int $bookingUid
     * @return array|null
     */
    public static function getTicket(int $bookingUid): ?array
    {
        return self::client(path: sprintf(env('PARKING_API_PATH_BOOKINGS_DETAIL'), $bookingUid), method: 'GET');
    }

    /**
     * @param string $noUser
     * @param int $page
     * @return array|null
     */
    public static function getTicketsByNoUser(string $noUser, int $page): ?array
    {
        return self::client(path: sprintf('%s?%s', env('PARKING_API_PATH_BOOKINGS'), http_build_query([
            'page' => $page,
            'userCode' => $noUser
        ])), method: 'GET');
    }

    /**
     * @param $bookingUids
     * @return array|null
     */
    public static function getTicketByIds($bookingUids): ?array
    {
        return self::client(env('PARKING_API_PATH_BOOKINGS_SEARCH'), [
            'uids' => $bookingUids
        ]);
    }

    /**
     * @param int $bookingUid
     * @return array|null
     */
    public static function cancelTicket(int $bookingUid): ?array
    {
        return self::client(path: sprintf(env('PARKING_API_PATH_BOOKINGS_CANCEL'), $bookingUid), method: 'PUT');
    }
}
