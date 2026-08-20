<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerActivity extends Model
{
    use SoftDeletes;

    protected $table = 'customer_activities';

    protected $fillable = [
        'customer_id',
        'user_id',
        'type',
        'subject',
        'description',
        'activity_date',
        'follow_up_date',
        'status',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
