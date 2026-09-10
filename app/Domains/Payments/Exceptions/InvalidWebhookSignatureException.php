<?php

namespace App\Domains\Payments\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Rappel dont la signature ne correspond pas.
 *
 * Aucun detail n'est renvoye : ni la signature attendue, ni la raison precise
 * du rejet. Un endpoint d'encaissement qui explique pourquoi une signature est
 * fausse aide surtout celui qui essaie de la forger.
 */
final class InvalidWebhookSignatureException extends DomainException
{
    public static function make(): self
    {
        return new self(
            'Signature du rappel invalide.',
            'invalid_webhook_signature',
            401,
        );
    }
}
