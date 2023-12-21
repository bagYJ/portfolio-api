<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_detail', function (Blueprint $table) {
            $table->bigInteger('no_product')->comment('상품번호');
            $table->foreign('no_product')->references('no_product')->on(
                'product'
            )->onUpdate('cascade')->onDelete('cascade');

            $table->mediumText('ds_subtitle')->nullable()->comment('상품 간략설명');
            $table->longText('ds_ingredient')->nullable()->comment('상품 원산지');
            $table->unsignedDecimal('ds_gram', 10, 2)->default(0)->comment(
                '총중량'
            );
            $table->unsignedInteger('ds_calorie')->default(0)->comment('열량');
            $table->unsignedInteger('ds_fat')->default(0)->comment('포화지방');
            $table->unsignedInteger('ds_sugars')->default(0)->comment('당류');
            $table->unsignedInteger('ds_protein')->default(0)->comment('단백질');
            $table->unsignedInteger('ds_salt')->default(0)->comment('나트륨');
            $table->mediumText('ds_allergy')->nullable()->comment('상품 간략설명');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_detail');
    }
};
