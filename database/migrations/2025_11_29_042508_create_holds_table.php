<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('qty');
            $table->dateTime('expires_at');
            $table->unsignedBigInteger('used_for_order_id')->nullable();
            $table->enum('status', ['reserved', 'expired', 'consumed'])->default('reserved');
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index('expires_at');
            $table->index('status');
            $table->index('used_for_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};

