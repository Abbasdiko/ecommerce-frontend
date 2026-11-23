<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('verification_status');
            $table->timestamp('kyc_submitted_at')->nullable()->after('settings');
        });

        Schema::table('agent_orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('status');
            $table->timestamp('funds_released_at')->nullable()->after('delivered_at');
        });

        Schema::table('agent_subscriptions', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('payment_reference')->constrained('payments')->nullOnDelete();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->text('response')->nullable()->after('body');
            $table->timestamp('responded_at')->nullable()->after('response');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('payment_method')->constrained('coupons')->nullOnDelete();
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('change');            // +/- units
            $table->integer('quantity_after');
            $table->string('reason');             // restock, correction, damage, sale, return
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
        Schema::table('orders', fn (Blueprint $t) => $t->dropConstrainedForeignId('coupon_id'));
        Schema::table('reviews', fn (Blueprint $t) => $t->dropColumn(['response', 'responded_at']));
        Schema::table('agent_subscriptions', fn (Blueprint $t) => $t->dropConstrainedForeignId('payment_id'));
        Schema::table('agent_orders', fn (Blueprint $t) => $t->dropColumn(['delivered_at', 'funds_released_at']));
        Schema::table('agents', fn (Blueprint $t) => $t->dropColumn(['settings', 'kyc_submitted_at']));
    }
};
