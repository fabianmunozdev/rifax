<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

trait DeletesReplacedUploadedFiles
{
    protected static function bootDeletesReplacedUploadedFiles(): void
    {
        static::updating(function ($model): void {
            foreach ($model->managedUploadedFileAttributes() as $attribute) {
                if (! $model->isDirty($attribute)) {
                    continue;
                }

                $originalPath = $model->getOriginal($attribute);
                $newPath = $model->getAttribute($attribute);

                if (! is_string($originalPath) || $originalPath === '' || $originalPath === $newPath) {
                    continue;
                }

                Storage::disk('public')->delete($originalPath);
            }
        });

        static::deleted(function ($model): void {
            foreach ($model->managedUploadedFileAttributes() as $attribute) {
                $path = $model->getAttribute($attribute);

                if (! is_string($path) || $path === '') {
                    continue;
                }

                Storage::disk('public')->delete($path);
            }
        });
    }

    /**
     * @return list<string>
     */
    abstract public function managedUploadedFileAttributes(): array;
}
