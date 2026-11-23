<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type');                 // 'listing' or 'agent'
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');           // 1..5
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('verified_purchase')->default(false);
            $table->string('status')->default('published'); // pending, published, rejected
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->unique(['customer_id', 'subject_type', 'subject_id', 'order_item_id'], 'reviews_unique_per_purchase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
