<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        if ($user->role === 'store_owner') {
            $this->assignStore($user, $data);
        }

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

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

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
}
