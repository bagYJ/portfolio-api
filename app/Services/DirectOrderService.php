<?php

namespace App\Services;

use App\Enums\DiscountSale;
use App\Enums\EnumYN;
use App\Enums\Pickup;
use App\Enums\SearchBizKind;
use App\Models\DirectOrderList;
use Illuminate\Support\Collection;
use Throwable;

class DirectOrderService extends Service
{
    /**
     * @param array $parameter
     * @return Collection
     */
    public static function get(array $parameter): Collection
    {
        return DirectOrderList::where($parameter)
            ->with([
                'orderList.shop.partner',
                'orderList.shop.shopDetail',
                'orderList.retailOrderProduct.retailProduct',
                'orderList.retailOrderProduct.retailOrderProductOption.retailProductOption',
                'orderList.orderProduct.product.partnerCategory',
                'orderList.orderProduct.product.productIgnore',
                'orderList.orderProduct.product.productOptionGroups.productOptions',
                'parkingOrderList.parkingSite'
            ])
            ->orderByDesc('dt_reg')
            ->get()->map(function ($collect) {
                $bizKind = SearchBizKind::getBizKind($collect->cd_biz_kind);
                $orderProducts = match ($bizKind) {
                    SearchBizKind::FNB => $collect->orderList->orderProduct,
                    SearchBizKind::RETAIL => $collect->orderList->retailOrderProduct,
                    default => null
                };

                $data = [
                    'no' => $collect->no,
                    'cd_biz_kind' => $collect->cd_biz_kind,
                    'biz_kind' => $bizKind->name,
                    'no_site' => $collect->parkingOrderList?->no_site,
                    'no_shop' => $collect->orderList?->no_shop,
                    'pickup_type' => match ($bizKind) {
                        SearchBizKind::FNB => Pickup::tryFrom($collect->orderList->cd_pickup_type)->name,
                        default => null,
                    },
                    'is_car_pickup' => $collect->orderList?->shop->shopDetail->yn_car_pickup == 'Y',
                    'is_shop_pickup' => $collect->orderList?->shop->shopDetail->yn_shop_pickup == 'Y',
                    'at_price_total' => match ($bizKind) {
                        SearchBizKind::PARKING => $collect->parkingOrderList->at_price_pg,
                        default => $collect->orderList->at_price - ($collect->orderList->at_commission_rate + ($collect->orderList->at_send_price - ($collect->orderList->at_send_sub_disct ?? $collect->orderList->at_send_disct)))
                    },
                    'nm_order' => match ($bizKind) {
                        SearchBizKind::PARKING => $collect->parkingOrderList->nm_order,
                        default => $collect->orderList->nm_order
                    },
                    'nm_shop' => match ($bizKind) {
                        SearchBizKind::PARKING => $collect->parkingOrderList->parkingSite->nm_shop,
                        default => sprintf('%s %s', $collect->orderList->shop->partner->nm_partner, $collect->orderList->shop->nm_shop)
                    },
                    'list_product' => match ($bizKind) {
                        SearchBizKind::RETAIL, SearchBizKind::FNB => OrderService::makeListProduct($collect->orderList)->map(function (
                            $product
                        ) use ($collect, $bizKind, $orderProducts) {
                            $orderProduct = $orderProducts->firstWhere('no_product', $product['no_product']);
                            $ynCupDeposit = collect($product['option'])->where('yn_cup_deposit', '=', EnumYN::Y->name)->count() && !empty($collect->orderList->shop->at_cup_deposit) && $collect->orderList->shop->at_cup_deposit > 0 ? 'Y' : 'N';
                            return [
                                'no_product' => $product['no_product'],
                                'category' => $orderProduct->product?->partnerCategory->no_partner_category ?? $orderProduct->retailProduct?->no_category,
                                'ea' => $product['ct_inven'],
                                'discount_type' => $orderProduct->cd_discount_sale ? DiscountSale::tryFrom($orderProduct->cd_discount_sale)?->name : null,
                                'at_price' => $product['at_price_product'],
                                'yn_cup_deposit' => $ynCupDeposit,
                                'at_cup_deposit' => match ($ynCupDeposit) {
                                    EnumYN::Y->name => $collect->orderList->shop->at_cup_deposit * $product['ct_inven'],
                                    default => 0,
                                },
                                'is_buy' => match ($bizKind) {
                                    SearchBizKind::FNB => empty($orderProduct->product) === false && $orderProduct->product->productIgnore->where('no_shop', $collect->orderList?->no_shop)->count() <= 0 && $orderProduct->product->ds_status == 'Y',
                                    SearchBizKind::RETAIL => empty($orderProduct->retailProduct) === false && $orderProduct->retailProduct->yn_show == 'Y',
                                    default => true
                                },
                                'option' => match (gettype($product['option'])) {
                                    'object' => collect($product['option'])->map(function ($option) use (
                                        $bizKind,
                                        $orderProduct
                                    ) {
                                        return [
                                            'no_option_group' => $option['no_option_group'],
                                            'no_option' => $option['no_option'],
                                            'add_price' => $option['add_price'],
                                            'at_add_price' => $option['add_price'],
                                            'yn_cup_deposit' => $orderProduct->product?->productOptionGroups->firstWhere('no_group', $option['no_option_group'])->productOptions->firstWhere('no_option', $option['no_option'])?->yn_cup_deposit ?? 'N',
                                            'is_buy' => match ($bizKind) {
                                                SearchBizKind::FNB => empty($orderProduct->product?->productOptionGroups->firstWhere('no_group', $option['no_option_group'])->productOptions->firstWhere('no_option', $option['no_option'])) === false,
                                                SearchBizKind::RETAIL => empty($orderProduct->retailOrderProductOption->firstWhere('no_option', $option['no_option'])->retailProductOption) === false,
                                                default => false
                                            }
                                        ];
                                    }),
                                    'boolean' => [],
                                    default => $product['option']
                                }
                            ];
                        }),
                        default => null
                    }
                ];

                $data['at_cup_deposit'] = collect($data['list_product'])->sum('at_cup_deposit');
                return $data;
            });
    }

    /**
     * @param int $noUser
     * @param string $noOrder
     * @param string $cdBizKind
     * @return void
     * @throws Throwable
     */
    public static function create(int $noUser, string $noOrder, string $cdBizKind): void
    {
        (new DirectOrderList([
            'no_user' => $noUser,
            'cd_biz_kind' => $cdBizKind,
            'no_order' => $noOrder,
        ]))->saveOrFail();
    }

    /**
     * @param int $noUser
     * @param int $no
     * @return void
     */
    public static function remove(int $noUser, int $no)
    {
        DirectOrderList::where([
            'no' => $no,
            'no_user' => $noUser,
        ])->delete();
    }

    /**
     * @param array $parameter
     * @return bool
     */
    public static function hasDirectOrder(array $parameter): bool
    {
        return DirectOrderList::where($parameter)->count() <= 0;
    }
}