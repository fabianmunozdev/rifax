<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PanelPermission;
use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PaymentProofController extends Controller
{
    public function show(Request $request, PaymentProof $paymentProof): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('filament.admin.auth.login'));
        }

        abort_unless($user instanceof User && $user->hasPanelPermission(PanelPermission::PaymentsView), 403);

        $disk = Storage::disk($paymentProof->storage_disk ?: PaymentProof::STORAGE_DISK);

        abort_if(blank($paymentProof->storage_path) || ! $disk->exists($paymentProof->storage_path), 404);

        return $disk->response($paymentProof->storage_path, basename($paymentProof->storage_path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
