<?php

namespace App\Domains\Booking\Http\Requests;

use App\Domains\Payments\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Remontee des ventes realisees hors ligne au guichet.
 *
 * Le lot est plafonne a cent ventes. Ce n'est pas une limite arbitraire : une
 * tablette restee hors reseau une journee entiere remonte plusieurs centaines
 * de ventes d'un coup, et un lot illimite tiendrait une transaction ouverte
 * assez longtemps pour bloquer le guichet voisin. Le client decoupe et rejoue —
 * ce qu'il sait faire, puisque chaque vente est idempotente.
 *
 * `sold_at` est l'heure reelle de la vente cote tablette, et non celle de
 * l'envoi : c'est elle qui rattache la recette a la bonne journee, et sans
 * elle une vente de 23 h 50 remontee a 00 h 10 tomberait dans la caisse du
 * lendemain.
 */
class OfflineSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sales' => ['required', 'array', 'min:1', 'max:100'],

            // La reference client est obligatoire : c'est elle qui rend la
            // remontee rejouable sans doublon. Une vente hors ligne sans
            // reference ne peut pas etre synchronisee sans risque.
            'sales.*.client_reference' => ['required', 'string', 'max:64'],
            'sales.*.sold_at' => ['required', 'date'],
            'sales.*.trip_id' => ['required', 'uuid', 'exists:trips,id'],
            'sales.*.station_id' => ['nullable', 'uuid', 'exists:stations,id'],
            'sales.*.customer_name' => ['required', 'string', 'max:255'],
            'sales.*.customer_phone' => ['required', 'string', 'max:20'],
            'sales.*.customer_email' => ['nullable', 'email', 'max:255'],
            'sales.*.notes' => ['nullable', 'string', 'max:500'],

            'sales.*.passengers' => ['required', 'array', 'min:1', 'max:20'],
            'sales.*.passengers.*.seat_number' => ['required', 'string', 'max:6'],
            'sales.*.passengers.*.name' => ['required', 'string', 'max:255'],
            'sales.*.passengers.*.phone' => ['nullable', 'string', 'max:20'],
            'sales.*.passengers.*.id_number' => ['nullable', 'string', 'max:30'],

            'sales.*.payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'sales.*.payer_msisdn' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function sales(): array
    {
        /** @var list<array<string, mixed>> $sales */
        $sales = $this->input('sales');

        return $sales;
    }
}
