<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('payment_method');
            $table->string('virtual_account')->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop the columns if migration is rolled back
            $table->dropColumn('bank_name');
            $table->dropColumn('virtual_account');
        });
    }
};