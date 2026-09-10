<?php

namespace App\Domains\Shared\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Racine des erreurs metier.
 *
 * Chaque sous-classe porte son code HTTP et un code applicatif stable, ce qui
 * permet au handler global de les rendre en JSON de facon uniforme sans que
 * les controleurs aient a les intercepter.
 *
 * Le code applicatif compte particulierement ici : l'application agent tourne
 * sur une tablette, souvent en reseau degrade, et doit distinguer sans lire un
 * message francais « place deja vendue » de « billet deja scanne » de « depart
 * annule ». Un message se traduit et se reformule ; un `error_code` ne bouge
 * pas.
 */
abstract class DomainException extends RuntimeException
{
    /** @param  array<string, mixed>  $context */
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $statusCode = 422,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'context' => (object) $this->context,
        ], $this->statusCode);
    }
}
