<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->timestamp('welcome_email_sent_at')->nullable();
            $table->timestamp('verification_email_sent_at')->nullable();
        });

        Schema::create('agent_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['agent_id', 'service_id']);
        });

        Schema::create('agent_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('billing_mode');
            $table->string('currency', 3)->default('NGN');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['agent_id', 'status']);
        });

        Schema::create('agent_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_invoice_id')->constrained('agent_invoices')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('service_packages')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('agent_subscriptions')->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_invoice_items');
        Schema::dropIfExists('agent_invoices');
        Schema::dropIfExists('agent_services');
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['welcome_email_sent_at', 'verification_email_sent_at']);
        });
    }
};
