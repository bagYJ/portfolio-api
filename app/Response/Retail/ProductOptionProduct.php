<?php

declare(strict_types=1);

namespace App\Response\Retail;

use App\Models\RetailProductOption;
use Illuminate\Support\Collection;

class ProductOptionProduct
{
    public int $no_option;
    public string $nm_option;
    public ?int $cnt_product;
    public float $at_add_price;

    public function __construct(RetailProductOption $option, Collection $stock)
    {
        $this->no_option = $option->no_option;
        $this->nm_option = $option->nm_product_opt;
        $this->cnt_product = data_get($stock->firstWhere('no_barcode', $option->no_barcode_opt), 'cnt_product', 0);
        $this->at_add_price = $option->at_price_opt;
    }

    public function setProductOption(): ProductOptionProduct
    {
        return $this;
    }
}
