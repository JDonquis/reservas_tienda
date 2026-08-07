<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('google_event_id')->nullable(); // ID retornado por Google Calendar
            $table->string('status')->default('confirmed'); // confirmed, cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
