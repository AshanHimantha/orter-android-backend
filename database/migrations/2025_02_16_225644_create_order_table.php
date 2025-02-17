php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('pickup_id')->unique()->nullable();
            $table->string('firebase_uid');
            $table->enum('delivery_type', ['delivery', 'pickup']);
            $table->foreignId('branch_id')->nullable()->constrained();
            
            // Delivery Address (nullable for pickup)
            $table->string('delivery_name')->nullable();
            $table->string('delivery_phone')->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('delivery_city')->nullable();
            
            // Order Status
            $table->enum('status', [
                'pending', 'confirmed', 'processing', 'shipped', 
                'delivered', 'ready_for_pickup', 'picked_up', 
                'completed', 'cancelled'
            ])->default('pending');
            $table->text('notes')->nullable();
            
            // Payment Details
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('pending');
            $table->string('transaction_id')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('firebase_uid')
                  ->references('firebase_uid')
                  ->on('users')
                  ->onDelete('cascade');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('stock_id')->constrained()->onDelete('cascade');
            $table->string('product_name');
            $table->string('product_image');
            $table->string('size');
            $table->integer('quantity');
            $table->decimal('selling_price', 10, 2);
            $table->decimal('cost_price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};