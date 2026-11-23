<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('owner_type')->default('customer'); // customer, agent
            $table->decimal('pending_balance', 16, 2)->default(0);
            $table->decimal('available_balance', 16, 2)->default(0);
            $table->decimal('withdrawn_balance', 16, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->timestamps();

            $table->unique(['user_id', 'owner_type']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type');            // referral_commission, refund_credit, settlement, adjustment
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('amount', 16, 2);
            $table->string('direction');       // credit, debit
            $table->decimal('balance_before', 16, 2);
            $table->decimal('balance_after', 16, 2);
            $table->string('bucket')->default('available'); // pending, available, withdrawn
            $table->string('description')->nullable();
            $table->string('status')->default('completed');
            $table->timestamp('created_at')->nullable();

            $table->index(['wallet_id', 'created_at']);
        });

        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('commission_base', 14, 2)->default(0);
            $table->decimal('commission_rate', 6, 3)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->string('status')->default('pending'); // pending, available, paid, reversed
            $table->timestamps();

            $table->index(['referrer_user_id', 'status']);
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 16, 2);
            $table->decimal('fee', 16, 2)->default(0);
            $table->decimal('net_amount', 16, 2);
            $table->string('status')->default('pending'); // pending, approved, processing, completed, rejected
            $table->text('note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('referral_commissions');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('bank_accounts');
    }
};
