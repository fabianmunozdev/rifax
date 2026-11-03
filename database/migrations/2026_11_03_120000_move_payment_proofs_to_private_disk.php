<?php

use App\Models\PaymentProof;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Los comprobantes se guardaban en el disco público con nombres consecutivos
 * (/storage/whatsapp-proofs/N.jpg), visibles sin sesión. Se mueven al disco
 * privado con un nombre impredecible y se borra la copia pública.
 */
return new class extends Migration
{
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk(PaymentProof::STORAGE_DISK);

        DB::table('payment_proofs')
            ->where('storage_disk', 'public')
            ->orderBy('id')
            ->each(function (object $proof) use ($public, $private): void {
                $oldPath = (string) $proof->storage_path;
                $newPath = $oldPath;

                if ($oldPath !== '' && $public->exists($oldPath)) {
                    $newPath = 'whatsapp-proofs/'.pathinfo($oldPath, PATHINFO_FILENAME).'-'.Str::random(32).'.'.(pathinfo($oldPath, PATHINFO_EXTENSION) ?: 'jpg');
                    $private->put($newPath, $public->get($oldPath));
                    $public->delete($oldPath);
                }

                DB::table('payment_proofs')->where('id', $proof->id)->update([
                    'storage_disk' => PaymentProof::STORAGE_DISK,
                    'storage_path' => $newPath,
                ]);
            });
    }

    public function down(): void
    {
        // Sin vuelta atrás: no se vuelven a publicar los comprobantes.
    }
};
