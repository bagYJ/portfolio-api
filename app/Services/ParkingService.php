<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnumYN;
use App\Enums\ParkingTicketSellingStatus;
use App\Enums\ParkingType;
use App\Exceptions\MobilXException;
use App\Exceptions\OwinException;
use App\Models\MemberCarinfo;
use App\Models\ParkingOrderList;
use App\Models\ParkingSite;
use App\Models\ParkingSiteImage;
use App\Models\ParkingSiteTicket;
use App\Utils\Code;
use App\Utils\Parking;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ParkingService
{
    /**
     * 주차장 정보 조회
     * @param float $radius 반경
     * @param float $lat latitude
     * @param float $lng longitude
     * @return Collection
     */
    public static function gets(float $radius, float $lat, float $lng): Collection
    {
        return ParkingSite::select([
            'parking_site.*',
            DB::raw(
                sprintf(
                    '(6371 * ACOS(COS(RADIANS(%1$s)) * COS(RADIANS(at_lat)) * COS(RADIANS(at_lng) -RADIANS(%2$s)) + SIN(RADIANS(%1$s)) * SIN(RADIANS(at_lat)))) AS distance',
                    $lat,
                    $lng
                )
            )
        ])->with([
            'parkingSiteImages',
            'parkingSiteTickets'
        ])->where('ds_status', 'Y')->having('distance', '<=', $radius)->orderBy('distance')->get();
    }

    /**
     * 주차장 단일 조회
     *
     * @param array $parameter
     *
     * @return ParkingSite|Builder|Model|object|null
     */
    public static function get(array $parameter)
    {
        return ParkingSite::with([
            'parkingSiteImages',
            'parkingSiteTickets'
        ])->where($parameter)->get()->whenEmpty(function () {
            throw new OwinException(Code::message('M1304'));
        })->first();
    }

    /**
     * 구입 가능 티켓 조회
     * @param string $idSite
     * @param int $noProduct
     * @return Model
     * @throws GuzzleException
     * @throws OwinException
     */
    public static function getActiveTicket(string $idSite, int $noProduct): Model
    {
        ParkingSite::where([
            'id_site' => $idSite,
            'ds_type' => ParkingType::WEB->name,
        ])->get()->whenEmpty(function () {
            throw new OwinException(Code::message('9910'));
        });

        return ParkingSiteTicket::where([
            'id_site' => $idSite,
            'no_product' => $noProduct,
            'cd_selling_status' => ParkingTicketSellingStatus::AVAILABLE->name
        ])->with(['parkingSite.parkingSiteImages'])->get()->whenNotEmpty(function ($collect) {
            match ($collect->first()->cd_selling_status) {
                ParkingTicketSellingStatus::NOT_YET_TIME->name => throw new OwinException(Code::message('1003')),
                ParkingTicketSellingStatus::SOLD_OUT->name => throw new OwinException(Code::message('1004')),
                default => null
            };

            if (empty($collect->first()->ds_selling_days) == false
                && !in_array(date('w'), explode(',', $collect->first()->ds_selling_days))
            ) {
                throw new OwinException(Code::message('1003'));
            }
        })->whenEmpty(function () {
            throw new OwinException(Code::message('1007'));
        })->first();
    }

    /**
     * 이전 주문정보 조회
     *
     * @param int        $noUser
     * @param array|null $operate
     * @param array|null $notIn
     *
     * @return mixed
     */
    public function ordering(int $noUser, ?array $operate = null, ?array $notIn = null): Collection
    {
        return ParkingOrderList::where('no_user', $noUser)
            ->where(function ($query) use ($operate, $notIn) {
                if (empty($operate) === false) {
                    foreach ($operate as $key => $value) {
                        $query->where($key, $value[0], $value[1]);
                    }
                }
                if (empty($notIn) === false) {
                    foreach ($notIn as $key => $value) {
                        $query->whereNotIn($key, $value);
                    }
                }
            })->get();
    }

    /**
     * 주문내역 업데이트
     * @param array $where
     * @param array $update
     * @return void
     */
    public static function updateParkingOrder(array $where, array $update): void
    {
        ParkingOrderList::where($where)->update($update);
    }

    /**
     * 회원 주문내역 조회
     * @param array $where
     * @param array|null $operate
     * @param array|null $whereIn
     * @return array
     * @throws OwinException
     */
    public static function getOrderList(array $where = [], ?array $operate = null, ?array $whereIn = null, ): array
    {

        $data = new ParkingOrderList();
        $uids = new ParkingOrderList();
        if ($where) {
            $data = $data->where($where);
            $uids = $uids->where($where);
        }
        if (empty($whereIn) == false) {
            foreach ($whereIn as $key => $value) {
                $data = $data->whereIn($key, $value);
                $uids = $uids->whereIn($key, $value);
            }
        }

        $count = $data->count();
        $data = $data->orderByDesc('dt_reg');

        $dataHash = [];
        $uids = array_chunk($uids->where('cd_parking_status', 'WAIT')->get()->pluck('no_booking_uid')->all(), 10);
        foreach ($uids AS $uid) {
            $bookingData = Parking::getTicketByIds($uid);
            if ($bookingData && !empty(data_get($bookingData, 'bookings'))) {
                foreach (data_get($bookingData, 'bookings') as $booking) {
                    $dataHash[$booking['uid']] = $booking;
                }
            }
        }

        DB::transaction(function () use ($data, $dataHash) {
            $rows = $data->get();
            foreach ($rows as $row) {
                if (isset($dataHash[$row['booking_uid']])) {
                    ParkingOrderList::where([
                        'no_order' => $row['no_order']
                    ])->update([
                        'cd_parking_status' => data_get($dataHash[$row['booking_uid']], 'status'),
                        'ds_user_parking_reserve_time' => data_get($dataHash[$row['booking_uid']], 'reserveTime'),
                        'dt_user_parking_used' => data_get($dataHash[$row['booking_uid']], 'usedAt'),
                        'dt_user_parking_canceled' => data_get($dataHash[$row['booking_uid']], 'canceledAt'),
                        'dt_user_parking_expired' => data_get($dataHash[$row['booking_uid']], 'expiredAt'),
                    ]);
                }
            }
        });

        return [
            'count' => $count,
            'rows' => $data->with([
                'parkingSite',
                'ticket',
                'user',
            ])->get()
        ];
    }

    /**
     * @param array $parameter
     * @param string|null $interfaceCode
     * @return ParkingOrderList
     */
    public static function getAutoParkingOrderInfo(array $parameter, string $interfaceCode = null): ParkingOrderList
    {
        return ParkingOrderList::with(['autoParking', 'carInfo'])->where($parameter)->get()
            ->whenEmpty(function () use ($interfaceCode) {
                if ($interfaceCode) {
                    throw new MobilXException($interfaceCode, 9011);
                }
                throw new OwinException(Code::message('AP9011'));
        })->first();
    }

    /**
     * @param $noUser
     * @param $noOrder
     * @return ParkingOrderList
     */
    public static function getOrderInfo($noUser, $noOrder): ParkingOrderList
    {
        return ParkingOrderList::where([
            'no_user' => $noUser,
            'no_order' => $noOrder
        ])->with([
            'parkingSite',
            'autoParking',
            'ticket',
        ])->get()->whenEmpty(function () {
            throw new OwinException(Code::message('P2120'));
        })->map(function ($item) {
            list($item->cd_status, $item->nm_status) = getOrderStatus(
                cdBizKind: '201500',
                cdOrderStatus: $item->cd_order_status,
                cdPickupStatus: $item->cd_pickup_status,
                cdPaymentStatus: $item->cd_payment_status,
                cdPgResult: $item->cd_pg_result
            );
            return $item;
        })->first();
    }

    /**
     * 주차장 정보 조회 및 데이터 업데이트
     * @param int $noSite
     * @param array $parking
     * @return mixed
     */
    public static function updateOrCreate(int $noSite, array $parking)
    {
        $parkingData = [
            'nm_shop' => $parking['name'],
            'ds_price_info' => $parking['price'],
            'ds_tel' => $parking['tel'],
            'at_lat' => $parking['lat'],
            'at_lng' => $parking['lon'],
            'ds_address' => $parking['address'],
            'ds_operation_time' => $parking['operationTime'],
            'ds_caution' => $parking['caution'] ?? null,
        ];

        $imageRows = [];
        if (isset($parking['picture']) && count($parking['picture'])) {
            foreach ($parking['picture'] as $picture) {
                $imageRows[] = [
                    'no_parking_site' => $noSite,
                    'ds_image_url' => $picture
                ];
            }
        }
        $ticketRows = [];
        if (isset($parking['tickets']) && count($parking['tickets'])) {
            foreach ($parking['tickets'] as $ticket) {
                $ticketRows[] = [
                    'no_product' => $ticket['uid'],
                    'no_parking_site' => $noSite,
                    'nm_product' => $ticket['title'],
                    'cd_ticket_type' => $ticket['ticketType'],
                    'cd_ticket_day_type' => $ticket['ticketDayType'],
                    'ds_parking_start_time' => $ticket['parkingStartTime'],
                    'ds_parking_end_time' => $ticket['parkingEndTime'],
                    'ds_selling_days' => implode(',', $ticket['sellingDays']),
                    'ds_selling_start_Time' => $ticket['sellingStartTime'],
                    'ds_selling_end_time' => $ticket['sellingEndTime'],
                    'at_price' => $ticket['price'] ?? 0,
                    'cd_selling_status' => $ticket['sellingStatus'],
                ];
            }
        }
        return DB::transaction(function () use ($noSite, $parkingData, $imageRows, $ticketRows) {
            ParkingSite::updateOrCreate(['no_site' => $noSite], $parkingData);
            ParkingSiteImage::where('no_parking_site', $noSite)->delete();
            ParkingSiteImage::insert($imageRows);
            ParkingSiteTicket::upsert($ticketRows, ['no_product', 'no_parking_site'], [
                'nm_product',
                'cd_ticket_type',
                'cd_ticket_day_type',
                'ds_parking_start_time',
                'ds_parking_end_time',
                'ds_selling_days',
                'ds_selling_start_time',
                'ds_selling_end_time',
                'at_price',
                'cd_selling_status'
            ]);
            return ParkingSite::where('no_site', $noSite)->with(['parkingSiteImages', 'parkingSiteTickets'])->first();
        });
    }


    /**
     * 미결제 주문내역 조회
     * @method GET
     * @param int $noUser
     * @return mixed
     */
    public static function checkPayment(int $noUser)
    {
        return ParkingOrderList::where([
            ['no_user', '=', $noUser],
            ['id_auto_parking', '!=', null], //자동결제 주차인 것
            ['dt_exit_time', '!=', null], //출차 상태인 것
            ['cd_third_party', '=', getAppType()->value],
            ['cd_payment_status', '=', '603200'], // 결제 실패 상태
        ])->with(['autoParking'])->get()->map(function ($collect) {
            return [
                'no_order' => $collect->no_order,
                'nm_order' => $collect->nm_order,
                'no_site' => $collect->no_site,
                'nm_shop' => $collect->autoParking?->nm_shop,
                'ds_car_number' => $collect->ds_car_number,
                'dt_entry_time' => Carbon::parse($collect->dt_entry_time)->format('Y-m-d H:i:s'),
                'dt_exit_time' => Carbon::parse($collect->dt_exit_time)->format('Y-m-d H:i:s'),
                'parking_time' => $collect->dt_entry_time->diff($collect->dt_exit_time)->format('%H시간 %I분'),
                'at_price' => $collect->at_price,
                'cd_card_corp' => $collect->cd_card_corp,
                'card_corp' => CodeService::getCode($collect->cd_card_corp)->nm_code ?? '',
                'no_card_user' => $collect->no_card_user,
            ];
        })->first();
    }

    /**
     * 자동결제 등록 카드 확인
     * @param int $noUser
     * @return void
     */
    public static function checkAutoParkingPayment(int $noUser)
    {
        MemberCarinfo::where([
            ['no_user', '=', $noUser],
            ['yn_use_auto_parking', '=', EnumYN::Y->name],
            ['yn_delete', '=', 'N'],
            ['no_card', '<>', null]
        ])->with('cards')->whereDoesntHave('cards')
            ->get()->whenNotEmpty(function () {
                throw new OwinException(Code::message('AP0008'));
            });
    }
}
