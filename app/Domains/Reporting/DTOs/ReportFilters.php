<?php

namespace App\Domains\Reporting\DTOs;

use Illuminate\Support\Carbon;

/**
 * Perimetre d'une lecture du tableau de bord.
 *
 * `companyId` n'est pas un filtre d'affichage mais une frontiere de securite :
 * il est impose par le controleur d'apres le jeton de l'appelant, jamais lu
 * dans la requete. Un gestionnaire qui pourrait le choisir consulterait la
 * recette de ses concurrents.
 */
final readonly class ReportFilters
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public ?string $companyId = null,
        public ?string $stationId = null,
    ) {}

    /**
     * Perimetre par defaut : les trente derniers jours.
     *
     * Une fenetre bornee et non « tout l'historique » : le tableau de bord
     * s'ouvre sur un reseau mobile, et une agregation sans borne devient
     * lente exactement au moment ou la plateforme commence a marcher.
     */
    public static function lastDays(int $days = 30, ?string $companyId = null, ?string $stationId = null): self
    {
        return new self(
            from: Carbon::now()->subDays($days)->startOfDay(),
            to: Carbon::now()->endOfDay(),
            companyId: $companyId,
            stationId: $stationId,
        );
    }

    /**
     * Horizon des departs pris en compte dans le taux de remplissage.
     *
     * Les ventes se lisent au passe, les departs surtout au futur : un
     * gestionnaire consulte le remplissage pour decider d'ajouter ou de
     * supprimer un car la semaine prochaine, pas pour constater celui du mois
     * dernier. La periode de vente est donc prolongee de quatorze jours pour
     * les departs — assez pour couvrir la programmation courante, assez peu
     * pour que la liste reste lisible.
     */
    public function departuresUntil(): Carbon
    {
        return $this->to->copy()->addDays(14)->endOfDay();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromRequest(array $input, ?string $companyId = null): self
    {
        return new self(
            from: isset($input['from'])
                ? Carbon::parse((string) $input['from'])->startOfDay()
                : Carbon::now()->subDays(30)->startOfDay(),
            to: isset($input['to'])
                ? Carbon::parse((string) $input['to'])->endOfDay()
                : Carbon::now()->endOfDay(),
            companyId: $companyId,
            stationId: isset($input['station_id']) ? (string) $input['station_id'] : null,
        );
    }
}
