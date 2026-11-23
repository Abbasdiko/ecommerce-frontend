<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('service_type')->nullable(); // free-form label set by admin
            $table->string('status')->default('active'); // active, inactive
            $table->boolean('requires_approval')->default(true);
            $table->timestamps();
        });

        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->unsignedInteger('duration_days')->default(30);
            $table->unsignedInteger('listing_limit')->default(10);
            $table->unsignedInteger('featured_listing_limit')->default(0);
            $table->decimal('commission_rate', 6, 3)->default(0); // percent
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('services');
    }
};
