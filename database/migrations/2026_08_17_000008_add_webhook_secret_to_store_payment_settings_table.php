<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_payment_settings', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('secret_key');
        });
    }

    public function down(): void
    {
        Schema::table('store_payment_settings', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
    }
};
