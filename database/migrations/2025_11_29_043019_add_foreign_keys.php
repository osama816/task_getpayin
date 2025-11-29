<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add foreign key for holds.used_for_order_id
        Schema::table('holds', function (Blueprint $table) {
            $table->foreign('used_for_order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');
        });

        // Add foreign key for payments_webhooks.order_id
        Schema::table('payments_webhooks', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('holds', function (Blueprint $table) {
            $table->dropForeign(['used_for_order_id']);
        });

        Schema::table('payments_webhooks', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
    }
};

