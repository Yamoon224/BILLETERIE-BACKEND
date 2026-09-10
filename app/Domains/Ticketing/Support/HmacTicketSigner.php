<?php

namespace App\Domains\Ticketing\Support;

use App\Domains\Ticketing\Contracts\TicketSignerContract;
use App\Domains\Ticketing\DTOs\TicketQrPayload;
use RuntimeException;

/**
 * Signature HMAC-SHA256 des billets.
 *
 * Trois choix meritent d'etre expliques.
 *
 * **Troncature a 32 caracteres hexadecimaux.** Soit 128 bits conserves sur les
 * 256 produits. Un QR code doit rester lisible par la camera d'une tablette
 * d'entree de gamme, sur un ticket thermique parfois froisse : chaque
 * caractere retire abaisse la densite du code. 128 bits laissent une chance de
 * forger une signature valide de l'ordre de 1 sur 3.10^38 — hors d'atteinte
 * pour un attaquant qui doit, en plus, se presenter physiquement devant un
 * agent.
 *
 * **Comparaison en temps constant.** `hash_equals` et non `===` : la
 * verification tourne aussi sur une tablette, et une comparaison qui s'arrete
 * au premier octet different laisse fuir, mesure assez de fois, la signature
 * attendue.
 *
 * **Cles versionnees.** Une rotation de secret ne doit pas invalider les
 * billets deja vendus. La version voyage en clair dans le QR, et la cle
 * correspondante est retrouvee ici ; une version inconnue est un refus, pas
 * une tentative avec la cle courante.
 */
final class HmacTicketSigner implements TicketSignerContract
{
    private const ALGORITHM = 'sha256';

    private const SIGNATURE_LENGTH = 32;

    public function sign(TicketQrPayload $payload): string
    {
        return $this->computeWith($payload, $this->keyFor($this->currentKeyVersion()));
    }

    public function verify(TicketQrPayload $payload, string $signature, int $keyVersion): bool
    {
        if (! in_array($keyVersion, $this->acceptedVersions(), true)) {
            return false;
        }

        try {
            $key = $this->keyFor($keyVersion);
        } catch (RuntimeException) {
            // Version declaree acceptee mais dont la cle n'est plus fournie :
            // un refus vaut mieux qu'une exception qui remonterait en 500
            // devant la porte d'un bus.
            return false;
        }

        return hash_equals($this->computeWith($payload, $key), $signature);
    }

    public function currentKeyVersion(): int
    {
        return (int) config('ticketing.signing.version', 1);
    }

    private function computeWith(TicketQrPayload $payload, string $key): string
    {
        return substr(
            hash_hmac(self::ALGORITHM, $payload->canonical(), $key),
            0,
            self::SIGNATURE_LENGTH,
        );
    }

    /** @return list<int> */
    private function acceptedVersions(): array
    {
        $versions = (array) config('ticketing.signing.accepted_versions', []);
        $versions[] = $this->currentKeyVersion();

        return array_values(array_unique(array_map('intval', $versions)));
    }

    private function keyFor(int $version): string
    {
        $key = $version === $this->currentKeyVersion()
            ? (string) config('ticketing.signing.key', '')
            : (string) (config('ticketing.signing.previous_keys', [])[(string) $version] ?? '');

        if ($key === '') {
            throw new RuntimeException(
                "Aucune cle de signature de billet pour la version {$version}. ".
                'Renseignez TICKETING_SIGNING_KEY (php artisan ticketing:signing-key).',
            );
        }

        // Le secret est stocke encode, comme APP_KEY : c'est la convention
        // Laravel, et un secret binaire ne survit pas a un fichier .env.
        return str_starts_with($key, 'base64:')
            ? (string) base64_decode(substr($key, 7), true)
            : $key;
    }
}
