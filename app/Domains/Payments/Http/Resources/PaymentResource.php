<?php

namespace App\Domains\Payments\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un encaissement.
 *
 * `gateway_payload` n'est jamais expose : c'est la reponse brute d'un
 * prestataire, conservee comme piece justificative interne, et sa forme
 * changera sans preavis. La publier ferait entrer le vocabulaire de
 * l'agregateur dans le contrat d'API que nos clients consomment.
 *
 * Le numero du payeur est tronque : le support en a besoin pour rapprocher un
 * debit, l'ecran de suivi d'une compagnie n'a pas a afficher le numero complet
 * de chaque voyageur.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'booking_id' => $this->booking_id,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'provider' => $this->provider?->value,
            'provider_label' => $this->provider?->label(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_settled' => $this->status->isSettled(),
            'external_reference' => $this->external_reference,
            'payer_msisdn' => $this->maskedMsisdn(),
            'failure_reason' => $this->failure_reason,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'collected_by' => $this->whenLoaded('collectedBy', fn () => $this->collectedBy ? [
                'id' => $this->collectedBy->id,
                'name' => $this->collectedBy->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function maskedMsisdn(): ?string
    {
        if ($this->payer_msisdn === null) {
            return null;
        }

        $length = strlen($this->payer_msisdn);

        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($this->payer_msisdn, 0, 4).str_repeat('*', $length - 6).substr($this->payer_msisdn, -2);
    }
}
