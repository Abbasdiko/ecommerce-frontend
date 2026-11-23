<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('commission_base', 14, 2);
            $table->decimal('commission_rate', 6, 3);
            $table->decimal('commission_amount', 14, 2);
            $table->decimal('agent_earning', 14, 2);
            $table->string('status')->default('pending'); // pending, finalized, reversed
            $table->timestamps();

            $table->index(['agent_id', 'status']);
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 16, 2);
            $table->decimal('fee', 16, 2)->default(0);
            $table->decimal('net_amount', 16, 2);
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('status')->default('pending'); // pending, approved, processing, completed, rejected
            $table->text('note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('commissions');
    }
};
