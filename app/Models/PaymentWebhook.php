<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhook extends Model
{
    protected $table = 'payments_webhooks';

    protected $fillable = [
        'idempotency_key',
        'payload',
        'processed_at',
        'status',
        'order_id',
        'target_order_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }
}

