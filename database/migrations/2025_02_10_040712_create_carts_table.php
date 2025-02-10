<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_uid')->index();
            $table->foreignId('stock_id')->constrained()->onDelete('cascade');
            $table->enum('size', ['XS', 'S', 'M', 'L', 'XL', 'XXL']);
            $table->integer('quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Add index for faster user cart queries
            $table->foreign('firebase_uid')
                  ->references('firebase_uid')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};