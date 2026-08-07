<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;
use App\Models\Schedule;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear una tienda de prueba
        $store = Store::create([
            'name' => 'Spa Centro de Bienestar',
            'api_key' => 'spa_demo_key_12345', // Esta clave la usaremos en el header del widget
            'google_access_token' => null,     // Se vinculará más adelante
            'google_refresh_token' => null,
            'google_calendar_id' => 'primary',
        ]);

        // 2. Crear Categorías
        $catFacial = Category::create([
            'store_id' => $store->id,
            'name' => 'Tratamientos Faciales',
        ]);

        $catCorporal = Category::create([
            'store_id' => $store->id,
            'name' => 'Masajes y Corporal',
        ]);

        // 3. Crear Productos / Servicios
        Product::create([
            'store_id' => $store->id,
            'category_id' => $catFacial->id,
            'name' => 'Limpieza Facial Profunda',
            'image_path' => 'products/facial.jpg',
            'price' => 35.00,
            'offer_price' => 25.00, // En oferta
            'is_active' => true,
        ]);

        Product::create([
            'store_id' => $store->id,
            'category_id' => $catFacial->id,
            'name' => 'Hidratación con Ácido Hialurónico',
            'image_path' => 'products/hidratacion.jpg',
            'price' => 50.00,
            'offer_price' => null,
            'is_active' => true,
        ]);

        Product::create([
            'store_id' => $store->id,
            'category_id' => $catCorporal->id,
            'name' => 'Masaje Relajante Descontracturante (60 min)',
            'image_path' => 'products/masaje.jpg',
            'price' => 40.00,
            'offer_price' => 30.00, // En oferta
            'is_active' => true,
        ]);

        for ($day = 1; $day <= 5; $day++) {
            Schedule::create([
                'store_id' => $store->id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'slot_duration_minutes' => 60,
            ]);
        }
    }
}
