<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference')->unique();
            $table->string('gateway');   // paystack, flutterwave, remita, bank_transfer
            $table->string('method')->nullable();
            $table->string('purpose');   // subscription, order
            $table->nullableMorphs('payable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('status')->default('pending'); // pending, processing, awaiting_verification, successful, failed, cancelled, refunded
            $table->string('gateway_reference')->nullable();
            $table->string('proof_path')->nullable(); // bank transfer proof upload
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_reference']);
            $table->index('status');
        });

        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('event')->nullable();
            $table->string('reference')->nullable();
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payments');
    }
};
