<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->integer('day_of_week'); // 0 = Domingo, 1 = Lunes, ..., 6 = Sábado
            $table->time('start_time');     // Ejemplo: '08:00:00'
            $table->time('end_time');       // Ejemplo: '17:00:00'
            $table->integer('slot_duration_minutes')->default(60); // Duración de cada cita en minutos
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
