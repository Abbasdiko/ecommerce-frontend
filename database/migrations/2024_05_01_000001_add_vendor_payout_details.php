<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('bank_code', 10)->nullable()->after('bank_name');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('bank_code', 10)->nullable()->after('bank_name');
            $table->string('payout_method')->nullable()->after('status');
            $table->string('transfer_reference')->nullable()->after('payout_method');
            $table->string('transfer_code')->nullable()->after('transfer_reference');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['bank_code', 'payout_method', 'transfer_reference', 'transfer_code']);
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('bank_code');
        });
    }
};
