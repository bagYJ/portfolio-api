<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnumYN;
use App\Exceptions\OwinException;
use App\Models\GsSaleCard;
use App\Models\GsSaleCardIssueLog;
use App\Models\MemberCard;
use App\Models\MemberDeal;
use App\Models\MemberPointcard;
use App\Models\MemberWallet;
use App\Models\OrderPayment;
use App\Models\ShopOilUnuseCard;
use App\Utils\Code;
use App\Utils\Pg;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Owin\OwinCommonUtil\CodeUtil;
use Owin\OwinCommonUtil\Enums\ServiceCodeEnum;
use Throwable;

class CardService extends Service
{
    /**
     * @param int $noUser
     * @param array $request
     * @param bool $isMain
     * @return string
     * @throws OwinException
     *
     * 카드 등록
     */
    public function regist(int $noUser, array $request, bool $isMain = false): string
    {
        $response = Pg::regist([
            'noOrder' => CodeUtil::generateOrderCode(ServiceCodeEnum::OWIN),
            'nmOrder' => Code::conf('billkey.nm_order'),
            'nmBuyer' => Auth::user()->nm_user,
            'phone' =>Auth::user()->ds_phone,
            'email' => Auth::user()->id_user,
            'cardNum' => data_get($request, 'no_cardnum'),
            'expYear' => data_get($request, 'no_expyea'),
            'expMon' => data_get($request, 'no_expmon'),
            'noBiz' => data_get($request, 'no_biz'),
            'birthday' => Auth::user()->ds_birthday,
            'noPin' => data_get($request, 'no_pin'),
        ]);
        if (data_get($response, 'result_code') && data_get($response, 'result_code') != '0000') {
            throw new OwinException(data_get($response, 'result_msg') ?? Code::message('P1022'));
        }

        $noCard = 10 . (time() + 3000000000) . mt_rand(1000, 9999);
        DB::transaction(function () use ($response, $noUser, $noCard, $isMain) {
            $maxCardSeq = MemberCard::where('no_user', $noUser)->withTrashed()->max('no_seq') ?? 1000;
            $noOrder = CodeUtil::generateOrderCode(ServiceCodeEnum::OWIN);
            $cardCorp = data_get($response, 'fdk.cd_card_corp');
            $noCardUser = data_get($response, 'fdk.no_card_user');

            foreach ($response as $pg) {
                if (data_get($pg, 'result_code') != '0000') {
                    throw new OwinException(data_get($pg, 'result_msg') ?? Code::message('P1022'));
                }

                (new OrderPayment([
                    'no_order' => $noOrder,
                    'no_payment' => (time() + 3000000000) . mt_rand(1000, 9999),
                    'no_partner' => Code::conf('billkey.no_partner'),
                    'no_shop' => Code::conf('billkey.no_shop'),
                    'no_user' => $noUser,
                    'cd_pg' => $pg['cd_pg'],
                    'ds_res_order_no' => $pg['ds_res_order_no'],
                    'cd_payment' => '501200',
                    'cd_payment_kind' => '',
                    'cd_payment_status' => '603300',
                    'ds_server_reg' => date('YmdHis'),
                    'ds_res_param' => json_encode($pg['res_param']),
                    'cd_pg_result' => '604100',
                    'ds_res_msg' => $pg['result_msg'],
                    'ds_res_code' => $pg['result_code'],
                    'at_price' => Code::conf('billkey.at_price_zero'),
                    'at_price_pg' => Code::conf('billkey.at_price_zero'),
                ]))->saveOrFail();

                (new MemberCard([
                    'no_user' => $noUser,
                    'no_seq' => ++$maxCardSeq,
                    'cd_card_corp' => '5030' . (Code::card(sprintf('500100.%s', $cardCorp)) ?? $cardCorp), // 없는 코드이면 값을 그대로 저장한다
                    'no_card' => $noCard,
                    'no_card_user' => $noCardUser,
                    'ds_billkey' => $pg['ds_billkey'],
                    'cd_pg' => $pg['cd_pg'],
                    'yn_credit' => $pg['yn_credit'],
                    'yn_main_card' => $isMain ? 'Y' : 'N'
                ]))->saveOrFail();
            }
        });

        return $noCard;
    }

    /**
     * @param int $noCard
     * @param int $noUser
     * @return void
     */
    public function remove(int $noCard, int $noUser): void
    {
        MemberCard::where('no_user', $noUser)
            ->where('no_card', $noCard)
            ->update([
                'yn_delete' => EnumYN::Y->name,
                'dt_del' => now()
            ]);
    }

    /**
     * @param array|null $where
     * @param string|null $bandwidthSt
     * @param string|null $bandwidthEnd
     * @return Collection
     */
    public static function gsSaleCard(?array $where = [], ?string $bandwidthSt = null, ?string $bandwidthEnd = null): Collection
    {
        return GsSaleCard::when(empty($where) === false, function($query) use ($where) {
            $query->where($where);
        })->where(function ($query) use ($bandwidthSt, $bandwidthEnd) {
            if (empty($bandwidthSt) === false) {
                $query->where('id_pointcard', '>=', $bandwidthSt);
            }
            if (empty($bandwidthEnd) === false) {
                $query->where('id_pointcard', '<', $bandwidthEnd);
            }
        })->get();
    }

    /**
     * @param string $key
     * @return GsSaleCard
     */
    public function maxGsPointCard(string $key): GsSaleCard
    {
        return GsSaleCard::max($key);
    }

    /**
     * @param array $where
     * @param array $parameter
     * @return void
     */
    public static function upsertGsSalesCard(array $where, array $parameter): void
    {
        GsSaleCard::updateOrCreate($where, $parameter);
    }

    /**
     * @param GsSaleCard $card
     * @param array $parameter
     * @return void
     */
    public static function updateGsSalesCard(GsSaleCard $card, array $parameter): void
    {
        $card->update($parameter);
    }

    /**
     * @param int $noUser
     * @param string $cardNumber
     * @param EnumYN $yn
     * @return void
     * @throws Throwable
     */
    public function gsCardLog(int $noUser, string $cardNumber, EnumYN $yn): void
    {
        (new GsSaleCardIssueLog([
            'no_user' => $noUser,
            'id_pointcard' => $cardNumber,
            'ds_issue_status' => $yn->name
        ]))->saveOrFail();
    }

    /**
     * @param int $noUser
     * @param int $idPointcard
     * @return void
     */
    public function pointCardRemove(int $noUser, int $idPointcard): void
    {
        $pointcard = $this->memberPointCard([
            'no_user' => $noUser,
            'id_pointcard' => $idPointcard
        ])->whenEmpty(function () {
            throw new OwinException(Code::message('SC9999'));
        })->first();

        if ($pointcard->yn_sale_card == EnumYN::Y->name) {
            $pointcard->update(['yn_delete' => EnumYN::Y->name]);
            MemberDeal::where('no_user', $noUser)->update(['yn_pointcard_issue' => EnumYN::N->name]);
        } else {
            $pointcard->delete();
        }
    }

    /**
     * @param int|null $noUser
     * @param int|null $noShop
     * @param array $listCdPg
     * @param bool $isUnUseCard
     * @param bool $isCreditOnly
     * @param bool $isDelete
     * @return Collection
     */
    public function cardList(
        ?int $noUser,
        ?int $noShop = null,
        array $listCdPg = [],
        bool $isUnUseCard = false,
        bool $isCreditOnly = false,
        bool $isDelete = false
    ): Collection {
        $memberCard = new MemberCard();
        if ($listCdPg) {
            $memberCard = $memberCard->whereIn('cd_pg', $listCdPg);
        }

        if ($isUnUseCard && $noShop) {
            $unUseCards = ShopOilUnuseCard::where([
                'no_shop' => $noShop,
                'yn_unuse_status' => 'Y'
            ])->pluck('cd_card_corp')->toArray();

            $unUseCards = array_unique($unUseCards);
            $memberCard = $memberCard->whereNotIn('cd_card_corp', $unUseCards);
        }

        if ($isCreditOnly) {
            $memberCard = $memberCard->whereRaw("(yn_credit = 'N' OR yn_credit IS NULL)");
        }
        if ($isDelete) {
            $memberCard = $memberCard->whereRaw("(yn_delete = 'N')");
        }

        return $memberCard->where('no_user', $noUser)->get()->unique('no_card')->map(function ($collect) {
            return [
                'no_card' => $collect->no_card,
                'no_card_user' => $collect->no_card_user,
                'cd_card_corp' => $collect->cd_card_corp,
                'yn_main_card' => $collect->yn_main_card,
                'img_card' => $collect->img_card,
            ];
        })->sortByDesc('yn_main_card')->values();
    }

    /**
     * @param int $noUser
     * @return Collection
     */
    public function getCardList(int $noUser): Collection
    {
        $groupCode = CodeService::getGroupCode('503');

        return $this->cardList($noUser)->map(function ($card) use ($groupCode) {
            return [
                'no_card' => $card->no_card,
                'no_card_user' => $card->no_card_user,
                'cd_card_corp' => $card->cd_card_corp,
                'card_corp' => $groupCode->where('no_code', $card->cd_card_corp)->first()->nm_code ?? '',
//                'cd_payment_method' => "504100",        // controller 안에서 설정하는 것으로 변경해야 함
            ];
        });
    }

    /**
     * @param array $parameter
     * @return Collection
     */
    public function memberPointCard(array $parameter): Collection
    {
        return MemberPointcard::with('promotionDeal')->where($parameter)->get();
    }

    /**
     * @param array $where
     * @param array $parameter
     * @return void
     */
    public static function upsertMemberPointcard(array $where, array $parameter): void
    {
        MemberPointcard::updateOrCreate($where, $parameter);
    }

    /**
     * @param array $parameter
     * @return void
     * @throws Throwable
     */
    public function gsSaleCardIssueLogRegist(array $parameter): void
    {
        (new GsSaleCardIssueLog($parameter))->saveOrFail();
    }

    /**
     * @param int $noUser
     * @param EnumYN $yn
     * @return Model
     */
    public function pointCard(int $noUser, EnumYN $yn): Model
    {
        return MemberPointcard::with('promotionDeal')->where([
            'no_user' => $noUser,
            'yn_delete' => $yn->name
        ])->get()->whenNotEmpty(function () {
            throw new OwinException(Code::message('SC1110'));
        })->first();
    }

    /**
     * @param int $noUser
     * @param int|null $noCard
     * @param string $cdPaymentMethod
     * @param string|null $cdServicePay
     * @param string|null $cdPg
     * @param $ynDelete
     * @return MemberCard|Model|object|null
     */
    public static function getCardInfo(
        int $noUser,
        int $noCard = null,
        string $cdPaymentMethod,
        string $cdServicePay = null,
        string $cdPg = null,
        $ynDelete = null
    ) {
        return match ($cdPaymentMethod) {
            '504200' => self::getMemberWalletInfo($noUser, $noCard, $cdServicePay, $cdPg, $ynDelete),
            default => self::getMemberCardInfo($noUser, $noCard, $cdServicePay, $cdPg, $ynDelete)
        };
    }

    /**
     * @param int $noUser
     * @param int|null $noCard
     * @param string|null $cdServicePay
     * @param string|null $cdPg
     * @param string|null $ynDelete
     * @return MemberCard|Model|object|null
     */
    private static function getMemberCardInfo(
        int $noUser,
        int $noCard = null,
        string $cdServicePay = null,
        string $cdPg = null,
        string $ynDelete = null
    ) {
        $where = [
            'no_user' => $noUser
        ];

        if ($noCard) {
            $where['no_card'] = $noCard;
        }

        if ($cdServicePay) {
            $where['cd_service_pay'] = $cdServicePay;
        }

        if ($cdPg) {
            $where['cd_pg'] = $cdPg;
        }

        if ($ynDelete) {
            $where['yn_delete'] = $ynDelete;
        }

        return MemberCard::where($where)->select([
            'no_user',
            'cd_card_corp',
            'no_card',
            'no_card_user',
            'ds_pay_passwd',
            'yn_main_card',
            'dt_reg',
            'cd_pg',
            'yn_credit',
            DB::raw('ds_billkey AS ds_paykey')
        ])->first();
    }

    /**
     * @param int $noUser
     * @param int|null $noCard
     * @param string|null $cdServicePay
     * @param string|null $cdPg
     * @param string|null $ynDelete
     * @return Model
     */
    private static function getMemberWalletInfo(
        int $noUser,
        int $noCard = null,
        string $cdServicePay = null,
        string $cdPg = null,
        string $ynDelete = null
    ): Model {
        $where = [
            'no_user' => $noUser
        ];

        if ($noCard) {
            $where['no_card'] = $noCard;
        }

        if ($cdServicePay) {
            $where['cd_service_pay'] = $cdServicePay;
        }

        if ($cdPg) {
            $where['cd_pg'] = $cdPg;
        }

        if ($ynDelete) {
            $where['yn_delete'] = $ynDelete;
        }

        return MemberWallet::where($where)->select([
            'no_user',
            'cd_card_corp',
            'no_card',
            'no_card_user',
            'yn_main_card',
            'dt_reg',
            'cd_pg',
            'yn_credit',
            DB::raw('\'\' AS ds_pay_passwd'),
            DB::raw('tr_id AS ds_paykey')
        ])->first();
    }
}
