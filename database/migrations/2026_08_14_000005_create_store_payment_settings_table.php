<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // paypal, mercadopago, stripe
            $table->boolean('enabled')->default(false);
            $table->string('mode')->default('sandbox'); // sandbox, live
            $table->text('public_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_payment_settings');
    }
};
