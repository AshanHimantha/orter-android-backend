<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payhere_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('payhere_amount')->nullable();
            $table->string('payhere_currency')->nullable();
            $table->string('status_code')->nullable();
            $table->string('md5sig')->nullable();
            $table->string('status_message')->nullable();
            $table->string('authorization_token')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('is_success')->default(false);
            $table->timestamps();

            $table->foreign('order_id')
                  ->references('id')
                  ->on('orders')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payhere_logs');
    }
};