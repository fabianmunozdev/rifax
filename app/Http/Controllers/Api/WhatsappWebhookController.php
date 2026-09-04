<?php

namespace App\Http\Controllers\Api;

use App\Actions\WhatsApp\ProcessIncomingWhatsappMessageAction;
use App\Actions\WhatsApp\ProcessWhatsappStatusUpdateAction;
use App\Actions\WhatsApp\ValidateIncomingWhatsappWebhookSignatureAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class WhatsappWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode');
        $token = (string) $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge');

        abort_unless(
            $mode === 'subscribe' && hash_equals(config('services.whatsapp.webhook_verify_token'), $token),
            Response::HTTP_FORBIDDEN,
        );

        return response($challenge, Response::HTTP_OK, [
            'Content-Type' => 'text/plain',
        ]);
    }

    public function handle(
        Request $request,
        ProcessIncomingWhatsappMessageAction $processor,
        ProcessWhatsappStatusUpdateAction $statusProcessor,
        ValidateIncomingWhatsappWebhookSignatureAction $signatureValidator,
    ): JsonResponse
    {
        if (! $signatureValidator->execute($request)) {
            return response()->json([
                'message' => 'Forbidden',
            ], Response::HTTP_FORBIDDEN);
        }

        Log::info('[WhatsappWebhook] inbound raw payload', [
            'payload' => $request->input(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $responses = collect($request->input('entry', []))
            ->flatMap(function (array $entry) use ($processor, $statusProcessor) {
                return collect($entry['changes'] ?? [])
                    ->flatMap(function (array $change) use ($processor, $statusProcessor) {
                        $value = $change['value'] ?? [];
                        $contacts = $value['contacts'] ?? [];
                        $messageResponses = collect($value['messages'] ?? [])
                            ->map(function (array $message) use ($processor, $contacts): array {
                                try {
                                    return $processor->execute($message, $contacts);
                                } catch (InvalidArgumentException $e) {
                                    Log::warning('[WhatsappWebhook] Skipping inbound with invalid phone: '
                                        .$e->getMessage().' message='.json_encode($message, JSON_UNESCAPED_UNICODE));

                                    return [
                                        'skipped' => true,
                                        'reason' => $e->getMessage(),
                                    ];
                                }
                            });

                        $statusResponses = collect($value['statuses'] ?? [])
                            ->map(fn (array $status): array => $statusProcessor->execute($status));

                        return $messageResponses
                            ->concat($statusResponses)
                            ->all();
                    });
            })
            ->values();

        return response()->json([
            'processed' => $responses->isNotEmpty(),
            'responses' => $responses,
        ]);
    }
}
