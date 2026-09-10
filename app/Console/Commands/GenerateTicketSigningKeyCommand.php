<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Genere la cle de signature des billets.
 *
 * Distincte de APP_KEY a dessein : l'application agent embarque cette cle pour
 * verifier les QR codes hors ligne. Lui confier la cle de chiffrement de
 * l'application entiere donnerait a une tablette de gare — objet qui se perd,
 * se vend et se demonte — les moyens de dechiffrer les sessions du serveur.
 */
class GenerateTicketSigningKeyCommand extends Command
{
    protected $signature = 'ticketing:signing-key {--show : Affiche la cle sans ecrire dans .env}';

    protected $description = 'Genere la cle de signature des billets (TICKETING_SIGNING_KEY).';

    public function handle(): int
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        if ($this->option('show')) {
            $this->line($key);

            return self::SUCCESS;
        }

        $path = base_path('.env');

        if (! File::exists($path)) {
            $this->error('Aucun fichier .env : copiez .env.example avant de generer la cle.');

            return self::FAILURE;
        }

        $contents = File::get($path);

        // Une rotation invaliderait tous les billets en circulation si l'on ne
        // conservait pas l'ancienne cle : le refus est explicite plutot que
        // silencieux, et la marche a suivre est donnee.
        if (preg_match('/^TICKETING_SIGNING_KEY=(.+)$/m', $contents, $matches) && trim($matches[1]) !== '') {
            $this->error('Une cle de signature existe deja.');
            $this->line('Une rotation demande de conserver l ancienne cle dans TICKETING_PREVIOUS_KEYS');
            $this->line('et d incrementer TICKETING_KEY_VERSION, sans quoi les billets deja vendus');
            $this->line('deviendraient invalides a l embarquement.');

            return self::FAILURE;
        }

        File::put($path, preg_replace(
            '/^TICKETING_SIGNING_KEY=.*$/m',
            'TICKETING_SIGNING_KEY='.$key,
            $contents,
        ));

        $this->info('Cle de signature des billets generee.');

        return self::SUCCESS;
    }
}
