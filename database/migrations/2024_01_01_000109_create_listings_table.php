<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('agent_subscriptions')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('sale_price', 14, 2)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->string('stock_status')->default('in_stock'); // in_stock, out_of_stock, preorder
            $table->string('condition')->default('new');         // new, used, refurbished
            $table->string('type')->default('product');          // product, service
            $table->string('status')->default('draft');          // draft, inactive, suspended
            $table->string('approval_status')->default('pending'); // pending, pending_review, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agent_id', 'status']);
            $table->index(['service_id']);
            $table->index(['category_id']);
            $table->index(['approval_status']);
            $table->index(['sku']);
        });

        Schema::create('listing_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_images');
        Schema::dropIfExists('listings');
    }
};
