<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'juandonquis07@gmail.com'], // 👈 Pon aquí tu correo de Google
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('password123'), // Contraseña para login tradicional
                'role'     => 'superadmin',              // Si manejas roles en la tabla users
            ]
        );
    }
}
