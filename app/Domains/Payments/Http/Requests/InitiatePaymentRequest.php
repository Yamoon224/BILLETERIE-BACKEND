<?php

namespace App\Domains\Payments\Http\Requests;

use App\Domains\Payments\Enums\MobileMoneyProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lancement d'un paiement mobile money pour une reservation en attente.
 *
 * Le montant ne figure pas dans les regles : il vient de la reservation. Le
 * laisser entrer par la requete permettrait de payer un billet de 9 000 F avec
 * 100 F, et aucune validation cote client ne protegerait de cela.
 */
class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(MobileMoneyProvider::values())],
            // Numero du payeur : il peut differer de celui du voyageur, un
            // proche payant souvent pour un autre.
            'payer_msisdn' => ['required', 'string', 'max:20'],
        ];
    }

    public function provider(): MobileMoneyProvider
    {
        return MobileMoneyProvider::from((string) $this->input('provider'));
    }
}
