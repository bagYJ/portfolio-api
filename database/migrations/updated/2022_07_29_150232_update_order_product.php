<?php

use App\Models\OrderProduct;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        OrderProduct::with([
            'product.productOptionGroups.productOptions'
        ])->where('no_sel_option1', '>', 0)->get()->map(function ($product) {
            $options = [];
            for ($i = 1; $i <= 5; $i++) {
                if ($product['no_sel_group' . $i] <= 0) break;

                $options[] = [
                    'no_option_group' => $product['no_sel_group' . $i],
                    'no_option' => $product['no_sel_option' . $i],
                    'nm_option' => $product->product?->productOptionGroups?->firstWhere('no_group', $product['no_sel_group' . $i])?->productOptions?->firstWhere('no_option', $product['no_sel_option' . $i])?->nm_option,
                    'at_add_price' => $product['no_sel_price' . $i],
                    'add_price' => $product['no_sel_price' . $i],
                    'nm_option_group' => $product->product?->productOptionGroups?->firstWhere('no_group', $product['no_sel_group' . $i])?->nm_group,
                ];
            }

            if (empty($options) === false) {
                $product->update(['options' => json_encode($options, JSON_UNESCAPED_UNICODE)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
