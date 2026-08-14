<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Login Tradicional (Email + Contraseña)
     */
    public function login(Request $request)
    {
              $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Redirige al consentimiento de Google
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Procesa la respuesta de Google
     */
    public function handleGoogleCallback()
    {
        try {

            $googleUser = Socialite::driver('google')->stateless()->user();

            // 1. Validar si el correo ya existe (creado por Admin/Seeder)
            $user = User::where('email', $googleUser->getEmail())->first();

            if (! $user) {
                // RECHAZADO: No puede registrarse solo
                return redirect(config('app.frontend_url').'/login?error=unauthorized_user');
            }

            // 2. Asociar ID de Google y Avatar si es primera vez que entra con Google
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]);

            // 3. Crear Token de Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            // 4. Redirigir a la SPA enviando el token en la URL
            return redirect(config('app.frontend_url')."/auth/google/callback?token={$token}");
        } catch (\Exception $e) {
            return redirect(config('app.frontend_url').'/login?error=google_failed');
        }
    }

    /**
     * Retorna la información del usuario autenticado
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Retorna el usuario autenticado junto con su tienda (si es propietario).
     */
    public function userStore(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'store' => $user->store,
        ]);
    }

    /**
     * Cerrar Sesión
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
