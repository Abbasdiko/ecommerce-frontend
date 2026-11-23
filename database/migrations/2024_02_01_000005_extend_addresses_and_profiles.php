<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('type')->default('shipping')->after('label'); // shipping, billing
        });

        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('default_address_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code')->nullable()->unique()->after('status');
            $table->foreignId('referred_by')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropColumn('referral_code');
        });
        Schema::table('customer_profiles', fn (Blueprint $table) => $table->dropColumn('settings'));
        Schema::table('addresses', fn (Blueprint $table) => $table->dropColumn('type'));
    }
};
