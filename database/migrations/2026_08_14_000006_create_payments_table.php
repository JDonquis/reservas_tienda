<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('provider');
            $table->string('provider_payment_id')->nullable();
            $table->string('status')->default('pending'); // pending, paid, failed
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->nullableMorphs('payable');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
