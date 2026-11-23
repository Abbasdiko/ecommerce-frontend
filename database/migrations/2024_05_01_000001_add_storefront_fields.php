<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->boolean('featured')->default(false)->after('type');
            $table->string('brand')->nullable()->after('featured');
            $table->timestamp('deal_ends_at')->nullable()->after('sale_price');
            $table->unsignedInteger('sales_count')->default(0)->after('quantity');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('image');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('service_type');
            $table->string('tagline')->nullable()->after('icon');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->text('about')->nullable()->after('description');
            $table->unsignedInteger('followers')->default(0)->after('about');
        });
    }

    public function down(): void
    {
        Schema::table('agents', fn (Blueprint $t) => $t->dropColumn(['about', 'followers']));
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn(['icon', 'tagline']));
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn('icon'));
        Schema::table('listings', fn (Blueprint $t) => $t->dropColumn(['featured', 'brand', 'deal_ends_at', 'sales_count']));
    }
};
