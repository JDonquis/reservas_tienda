<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Models\Store;

trait AuthorizesStoreAccess
{
    protected function authorizeStore($user, Store $store): void
    {
        if ($user->role !== 'superadmin' && $store->user_id !== $user->id) {
            abort(403, 'No tienes permisos sobre esta tienda.');
        }
    }

    protected function ensureBelongsToStore(Store $store, $model): void
    {
        abort_unless($model->store_id === $store->id, 404);
    }
}
