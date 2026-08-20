<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'unit_id',
        'quantity',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'line_total',
        'received_quantity',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'unit_price'        => 'decimal:4',
        'tax_rate'          => 'decimal:4',
        'tax_amount'        => 'decimal:4',
        'line_total'        => 'decimal:4',
        'received_quantity' => 'decimal:4',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
