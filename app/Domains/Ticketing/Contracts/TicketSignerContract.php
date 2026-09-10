<?php

namespace App\Domains\Ticketing\Contracts;

use App\Domains\Ticketing\DTOs\TicketQrPayload;

/**
 * Signature et verification des billets.
 *
 * Isole derriere un contrat pour une raison qui n'est pas theorique : le jour
 * ou la signature symetrique (HMAC) ne suffit plus — parce qu'on ne veut plus
 * confier a chaque tablette de gare un secret capable de *fabriquer* des
 * billets, seulement de les verifier —, on passe a une signature asymetrique
 * en changeant une implementation, sans toucher a l'emission ni au controle.
 *
 * Le service de validation, lui, ne connait que cette interface : il ne sait
 * pas s'il verifie un HMAC ou une signature Ed25519, et n'a aucune raison de
 * le savoir.
 */
interface TicketSignerContract
{
    /** Signature du billet, avec la cle courante. */
    public function sign(TicketQrPayload $payload): string;

    /**
     * La signature correspond-elle a ce contenu, pour cette version de cle ?
     *
     * La version est un parametre et non une constante : une rotation de
     * secret doit laisser verifiables les billets deja en circulation, y
     * compris celui du voyageur qui monte a l'instant.
     */
    public function verify(TicketQrPayload $payload, string $signature, int $keyVersion): bool;

    /** Version de cle utilisee pour les nouvelles emissions. */
    public function currentKeyVersion(): int;
}
