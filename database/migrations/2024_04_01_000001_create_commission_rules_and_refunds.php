<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configurable commission (spec §55–57). First applicable rule wins by scope priority.
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type');            // global, service, category, agent
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('commission_type')->default('percentage'); // percentage, fixed
            $table->decimal('commission_value', 10, 3);
            $table->string('status')->default('active');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id', 'status']);
        });

        // Refunds issued when a return/refund request is approved (spec §65).
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('return_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method')->default('wallet'); // wallet, gateway, bank_transfer
            $table->string('status')->default('completed'); // processing, completed, failed
            $table->boolean('commission_reversed')->default(false);
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('commission_rules');
    }
};
