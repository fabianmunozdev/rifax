<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappMessage;

/**
 * Transporte de WhatsApp (Meta Cloud API, Twilio, ...).
 *
 * La lógica de negocio solo conoce este contrato; el proveedor activo se
 * elige con WHATSAPP_PROVIDER.
 */
interface WhatsappProvider
{
    public function name(): string;

    /**
     * Indica si hay credenciales suficientes para enviar mensajes.
     */
    public function isConfigured(): bool;

    /**
     * Envía el mensaje al destinatario (solo dígitos, formato internacional).
     *
     * @return array{provider_message_id: string|null, request: array<string, mixed>, response: mixed}
     *
     * @throws WhatsappProviderException cuando el proveedor rechaza el envío
     */
    public function send(string $recipientDigits, WhatsappMessage $message): array;

    /**
     * Descarga un archivo recibido. $mediaReference es el media id de Meta
     * o la MediaUrl de Twilio.
     *
     * @return array{contents: string, mime_type: string|null, file_size: int|null, meta: array<string, mixed>}
     */
    public function downloadMedia(string $mediaReference): array;
}
