<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hold extends Model
{
    protected $fillable = [
        'product_id',
        'qty',
        'expires_at',
        'used_for_order_id',
        'status',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'qty' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'hold_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'reserved' && !$this->isExpired();
    }
}

