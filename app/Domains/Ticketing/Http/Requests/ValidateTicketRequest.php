<?php

namespace App\Domains\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Scan d'un billet a l'embarquement.
 *
 * `code` accepte deux formes : le contenu complet d'un QR code, ou le code du
 * billet saisi a la main quand le QR est illisible — cas frequent avec un
 * ticket thermique defraichi ou une camera de tablette bas de gamme.
 */
class ValidateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],

            // Le depart devant lequel se tient l'agent. Facultatif mais
            // fortement recommande : sans lui, un billet valide pour le bus
            // d'a cote serait accepte — techniquement authentique, mais monte
            // dans le mauvais vehicule.
            'trip_id' => ['nullable', 'uuid', 'exists:trips,id'],

            'station_id' => ['nullable', 'uuid', 'exists:stations,id'],

            // Identifiant du geste cote tablette. Il rend le scan idempotent :
            // un scan rejoue apres retour du reseau est reconnu comme le meme,
            // et non compte comme une seconde presentation du billet.
            'client_reference' => ['nullable', 'string', 'max:64'],
        ];
    }
}
