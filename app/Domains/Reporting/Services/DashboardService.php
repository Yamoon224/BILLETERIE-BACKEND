<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Reporting\Contracts\SalesReportReaderContract;
use App\Domains\Reporting\DTOs\ReportFilters;

/**
 * Tableau de bord des gestionnaires de compagnie.
 *
 * Assemble les lectures agregees en une seule reponse. Ce n'est pas une
 * commodite : l'ecran s'ouvre sur un telephone en reseau variable, et six
 * requetes HTTP successives y produisent six occasions d'echouer a moitie —
 * un tableau de bord dont trois cartes sur six affichent une erreur est pire
 * qu'un tableau de bord lent.
 */
final class DashboardService
{
    public function __construct(private readonly SalesReportReaderContract $reports) {}

    /** @return array<string, mixed> */
    public function overview(ReportFilters $filters): array
    {
        return [
            'period' => [
                'from' => $filters->from->toIso8601String(),
                'to' => $filters->to->toIso8601String(),
            ],
            'currency' => (string) config('ticketing.currency'),
            'summary' => $this->reports->summary($filters),
            'revenue_by_payment_method' => $this->reports->revenueByPaymentMethod($filters),
            'sales_by_channel' => $this->reports->salesByChannel($filters),
            'daily_revenue' => $this->reports->dailyRevenue($filters),
            // Vingt departs suffisent a l'ecran d'accueil : la liste complete
            // vit sur l'ecran de remplissage, avec sa propre pagination.
            'occupancy' => $this->reports->occupancyByTrip($filters, 20),
            'sales_by_agent' => $this->reports->salesByAgent($filters),
        ];
    }
}
