<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->json('payload');
            $table->dateTime('processed_at')->nullable();
            $table->enum('status', ['success', 'failed', 'pending'])->default('pending');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('target_order_id')->nullable();
            $table->timestamps();

            $table->index('target_order_id');
            $table->index('idempotency_key');
            $table->index('status');
            $table->index('order_id');
            // Foreign key will be added in a later migration after orders table exists
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments_webhooks');
    }
};

