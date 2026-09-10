<?php

namespace App\Domains\Scheduling\Http\Requests;

use App\Domains\Scheduling\DTOs\TripSearchCriteria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Recherche de departs par le voyageur. Endpoint public.
 *
 * Les villes sont designees par leur slug : une recherche se partage par
 * messagerie, et un lien doit rester lisible et retapable.
 */
class SearchTripsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'origin' => ['required', 'string', 'exists:cities,slug'],
            'destination' => ['required', 'string', 'exists:cities,slug', 'different:origin'],
            'date' => ['required', 'date'],
            // Le nombre de voyageurs sert a masquer les departs qui ne peuvent
            // pas accueillir le groupe entier : proposer un depart ou seules
            // deux places restent a une famille de quatre lui ferait perdre le
            // temps du tunnel de reservation.
            'passengers' => ['nullable', 'integer', 'min:1', 'max:10'],
            'company_id' => ['nullable', 'uuid', 'exists:companies,id'],
            'only_available' => ['nullable', 'boolean'],
        ];
    }

    public function criteria(): TripSearchCriteria
    {
        return new TripSearchCriteria(
            originSlug: (string) $this->input('origin'),
            destinationSlug: (string) $this->input('destination'),
            date: Carbon::parse((string) $this->input('date')),
            passengers: $this->integer('passengers', 1),
            companyId: $this->input('company_id'),
            onlyAvailable: $this->boolean('only_available'),
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'destination.different' => 'La ville d arrivee doit differer de la ville de depart.',
        ];
    }
}
