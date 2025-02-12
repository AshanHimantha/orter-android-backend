<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('charge', 10, 2);  // Base delivery charge
            $table->decimal('extra_per_kg', 10, 2);  // Extra charge per kg
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriers');
    }
};