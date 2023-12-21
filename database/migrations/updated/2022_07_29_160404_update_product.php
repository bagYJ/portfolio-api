<?php

use App\Models\Product;
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
        Product::where('no_sel_group1', '>', 0)->get()->map(function ($product) {
            $groups = [];
            for ($i = 1; $i <= 5; $i++) {
                if ($product['no_sel_group' . $i] <= 0) break;

                $groups[] = $product['no_sel_group' . $i];
            }
            if (empty($groups) === false) {
                $product->update(['option_group' => json_encode($groups)]);
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
