<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_reference')->unique();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('service_packages')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->string('status')->default('pending'); // pending, active, expired, cancelled, suspended, failed
            $table->string('payment_reference')->nullable();
            $table->string('payment_status')->default('pending'); // pending, successful, failed
            $table->boolean('auto_renew')->default(false);
            $table->unsignedInteger('listings_used')->default(0);
            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['service_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_subscriptions');
    }
};
