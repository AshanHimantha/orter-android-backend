<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Relations
            $table->foreignId('added_by')->constrained('admins')->onDelete('cascade');
            $table->foreignId('gender_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('product_categories')->onDelete('cascade');
            $table->foreignId('collection_id')->nullable()->constrained()->onDelete('set null');

            // Basic Info
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 10, 2);

            // Properties
            $table->string('material');
            $table->string('color');

            // Images
            $table->string('main_image');
            $table->string('image_1')->nullable();
            $table->string('image_2')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
