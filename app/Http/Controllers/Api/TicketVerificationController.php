<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class TicketVerificationController extends Controller
{
    public function verifyByCode(string $code): JsonResponse
    {
        $ticket = Ticket::query()
            ->with(['purchase.raffle', 'purchase.numbers'])
            ->where('code', $code)
            ->firstOrFail();

        return response()->json($this->buildPayload($ticket));
    }

    public function verifyByToken(Request $request, string $verificationToken): JsonResponse|View
    {
        $ticket = Ticket::query()
            ->with(['purchase.raffle', 'purchase.numbers'])
            ->where('verification_token', $verificationToken)
            ->firstOrFail();

        $payload = $this->buildPayload($ticket);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        $company = CompanySetting::query()->first();

        return view('tickets.show', [
            'ticket' => $ticket,
            'payload' => $payload,
            'company' => $company,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(Ticket $ticket): array
    {
        $purchase = $ticket->purchase;
        $raffle = $purchase->raffle;

        return [
            'ticket' => [
                'code' => $ticket->code,
                'public_url' => $ticket->public_url,
                'image_url' => $ticket->image_path ? asset('storage/'.$ticket->image_path) : null,
                'thumbnail_url' => $ticket->thumbnail_path ? asset('storage/'.$ticket->thumbnail_path) : null,
                'version' => $ticket->version,
                'generated_at' => $ticket->generated_at?->toIso8601String(),
                'status' => $purchase->status === 'paid' ? 'valid' : 'inactive',
            ],
            'raffle' => [
                'title' => $raffle->title,
                'draw_date' => $raffle->draw_date?->format('Y-m-d'),
                'draw_time' => $raffle->draw_time,
                'lottery_name' => $raffle->lottery_name,
                'lottery_draw_number' => $raffle->lottery_draw_number,
            ],
            'numbers' => $purchase->numbers->pluck('number')->values()->all(),
        ];
    }
}
