<?php

namespace App\Http\Controllers\Api;

use App\Actions\WhatsApp\ProcessIncomingWhatsappMessageAction;
use App\Actions\WhatsApp\ProcessWhatsappStatusUpdateAction;
use App\Http\Controllers\Controller;
use App\Services\WhatsApp\TwilioInboundMapper;
use App\Services\WhatsApp\TwilioWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Webhooks de Twilio para WhatsApp. Las respuestas del bot se envían por la API
 * (no por TwiML), así que siempre se devuelve un <Response/> vacío.
 */
class TwilioWhatsappWebhookController extends Controller
{
    public function __construct(
        protected TwilioWebhookSignature $signature,
        protected TwilioInboundMapper $mapper,
    ) {}

    public function inbound(Request $request, ProcessIncomingWhatsappMessageAction $processor): Response
    {
        if (! $this->signature->isValid($request)) {
            Log::warning('[TwilioWebhook] invalid signature', ['ip' => $request->ip(), 'url' => $request->fullUrl()]);

            return $this->twiml(403);
        }

        Log::info('[TwilioWebhook] inbound payload', ['payload' => $request->post(), 'ip' => $request->ip()]);

        ['message' => $message, 'contacts' => $contacts] = $this->mapper->message($request->post());

        try {
            $processor->execute($message, $contacts);
        } catch (InvalidArgumentException $e) {
            Log::warning('[TwilioWebhook] Skipping inbound with invalid phone: '.$e->getMessage());
        }

        return $this->twiml();
    }

    public function status(Request $request, ProcessWhatsappStatusUpdateAction $statusProcessor): Response
    {
        if (! $this->signature->isValid($request)) {
            return $this->twiml(403);
        }

        $statusProcessor->execute($this->mapper->status($request->post()));

        return $this->twiml();
    }

    protected function twiml(int $status = 200): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', $status, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
