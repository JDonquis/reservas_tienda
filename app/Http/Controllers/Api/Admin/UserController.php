<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Mail\WelcomeUserMail;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('store')->orderByDesc('id')->get();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $password = Str::password(12);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'role' => $data['role'],
        ]);

        if ($user->role === 'store_owner') {
            $this->assignStore($user, $data);
        }

        $this->sendWelcomeMail($user, $password);

        return UserResource::make($user->load('store'));
    }

    public function show(User $user)
    {
        return UserResource::make($user->load('store'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        $user->fill($request->only(['name', 'email', 'role']));

        $user->save();

        if ($user->role === 'store_owner' && $request->has('store_id')) {
            if (empty($data['store_id'])) {
                $user->store()?->update(['user_id' => null]);
            } else {
                $this->assignStore($user, $data);
            }
        } elseif ($user->role !== 'store_owner') {
            $user->store()?->update(['user_id' => null]);
        }

        return UserResource::make($user->load('store'));
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }

    protected function assignStore(User $user, array $data): void
    {
        if (! empty($data['store']['name'] ?? null)) {
            Store::create([
                'user_id' => $user->id,
                'name' => $data['store']['name'],
                'allowed_domain' => $data['store']['allowed_domain'] ?? null,
            ]);

            return;
        }

        if (! empty($data['store_id'] ?? null)) {
            $user->store()?->update(['user_id' => null]);
            Store::find($data['store_id'])->update(['user_id' => $user->id]);
        }
    }

    protected function sendWelcomeMail(User $user, string $password): void
    {
        try {
            Mail::to($user->email)->send(new WelcomeUserMail(
                name: $user->name,
                email: $user->email,
                password: $password,
                storeName: $user->store?->name,
                loginUrl: config('app.frontend_url').'/login',
            ));
        } catch (\Exception $e) {
            Log::error('Error enviando correo de bienvenida: '.$e->getMessage());
        }
    }
}
