<?php

namespace App\Actions\WhatsApp;

use App\Models\WhatsappMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class ProcessWhatsappStatusUpdateAction
{
    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    public function execute(array $status): array
    {
        $providerMessageId = (string) Arr::get($status, 'id', '');
        $providerStatus = (string) Arr::get($status, 'status', '');

        if ($providerMessageId === '' || $providerStatus === '') {
            return [
                'updated' => false,
                'reason' => 'missing_provider_message_id_or_status',
            ];
        }

        $message = WhatsappMessage::query()
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if ($message === null) {
            return [
                'updated' => false,
                'provider_message_id' => $providerMessageId,
                'provider_status' => $providerStatus,
                'reason' => 'message_not_found',
            ];
        }

        $providerStatusAt = $this->resolveStatusTimestamp($status);
        $payloadJson = $message->payload_json ?? [];
        $history = collect(Arr::get($payloadJson, 'provider_status_history', []))
            ->push([
                'status' => $providerStatus,
                'timestamp' => $providerStatusAt?->toIso8601String(),
                'recipient_id' => Arr::get($status, 'recipient_id'),
                'conversation' => Arr::get($status, 'conversation'),
                'pricing' => Arr::get($status, 'pricing'),
                'errors' => Arr::get($status, 'errors'),
            ])
            ->values()
            ->all();

        $payloadJson['provider_status_event'] = $status;
        $payloadJson['provider_status_history'] = $history;

        $message->forceFill([
            'status' => $providerStatus === 'failed' ? 'failed' : $message->status,
            'provider_status' => $providerStatus,
            'provider_status_at' => $providerStatusAt,
            'payload_json' => $payloadJson,
        ])->save();

        return [
            'updated' => true,
            'whatsapp_message_id' => $message->id,
            'provider_message_id' => $providerMessageId,
            'provider_status' => $providerStatus,
        ];
    }

    /**
     * @param  array<string, mixed>  $status
     */
    protected function resolveStatusTimestamp(array $status): ?Carbon
    {
        $timestamp = Arr::get($status, 'timestamp');

        if ($timestamp === null || $timestamp === '') {
            return now();
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }
}
