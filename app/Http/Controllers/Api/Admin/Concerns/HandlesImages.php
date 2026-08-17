<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesImages
{
    protected function storeImage(?UploadedFile $file, string $folder, ?string $oldPath = null): ?string
    {
        if (! $file) {
            return $oldPath;
        }

        $path = $file->store($folder, 'public');

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $path;
    }

    protected function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
