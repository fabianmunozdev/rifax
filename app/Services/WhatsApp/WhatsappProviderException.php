<?php

namespace App\Services\WhatsApp;

use RuntimeException;
use Throwable;

/**
 * Error de envío devuelto por un proveedor. Conserva la petición enviada y el
 * detalle del error para guardarlos en el mensaje.
 */
class WhatsappProviderException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $error
     */
    public function __construct(
        string $message,
        public readonly array $request = [],
        public readonly array $error = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
