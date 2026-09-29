<?php

namespace App\Domains\Shared\Http\Requests;

use App\Domains\Shared\Enums\ListingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Decision de l'administrateur de plateforme sur une fiche en attente.
 *
 * Partagee entre compagnies, appartements et vehicules de location : la
 * meme regle vaut partout, et seule change l'entite sur laquelle elle
 * s'applique. `pending` n'est volontairement pas une cible acceptee ici — on
 * ne renvoie pas une fiche deja tranchee en attente, on la retranche.
 */
class UpdateListingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([ListingStatus::Active->value, ListingStatus::Rejected->value])],
        ];
    }
}
