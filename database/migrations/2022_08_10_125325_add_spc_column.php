<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product', function (Blueprint $table) {
            $table->string('cd_spc')->nullable()->comment('SPC 상품 코드 번호');
        });

        Schema::table('partner', function (Blueprint $table) {
            $table->string('cd_spc_brand')->nullable()->comment('SPC 브랜드 코드');
        });

        Schema::table('shop', function (Blueprint $table) {
            $table->string('cd_spc_store')->nullable()->comment('SPC 매장 코드');
        });

        Schema::table('order_list', function (Blueprint $table) {
            $table->string('ds_spc_order')->nullable()->comment('SPC 중계 주문번호');
        });

        Schema::table('product_option', function (Blueprint $table) {
            $table->string('cd_spc')->nullable()->comment('SPC 상품 옵션 코드 번호');
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
