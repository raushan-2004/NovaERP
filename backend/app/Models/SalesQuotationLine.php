<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesQuotationLine extends Model
{
    protected $fillable = [
        'sales_quotation_id',
        'product_id',
        'quantity',
        'unit_id',
        'unit_price',
        'discount',
        'tax_rate',
        'tax_amount',
        'line_total',
    ];

    public function salesQuotation(): BelongsTo
    {
        return $this->belongsTo(SalesQuotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
