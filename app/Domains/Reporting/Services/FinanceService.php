<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Reporting\Contracts\SalesReportReaderContract;
use App\Domains\Reporting\DTOs\ReportFilters;

/**
 * Ecran Finances de l'administrateur de plateforme.
 *
 * Distinct du tableau de bord d'un gestionnaire de compagnie : la
 * repartition de la commission entre compagnies n'a de sens qu'a l'echelle
 * de la plateforme entiere (voir permission `finance.view`, reservee a
 * `platform_admin`).
 */
final class FinanceService
{
    public function __construct(private readonly SalesReportReaderContract $reports) {}

    /** @return array<string, mixed> */
    public function overview(ReportFilters $filters): array
    {
        $summary = $this->reports->summary($filters);

        return [
            'period' => [
                'from' => $filters->from->toIso8601String(),
                'to' => $filters->to->toIso8601String(),
            ],
            'currency' => (string) config('ticketing.currency'),
            'summary' => $summary,
            // Reverse aux compagnies : la recette brute moins la commission
            // plateforme, deja calculee par le suivi d'activite.
            'paid_out_to_companies' => $summary['net_revenue'],
            // En pour mille, comme `Company::commission_per_mille` : une
            // moyenne ponderee par le chiffre d'affaires reellement encaisse,
            // pas une moyenne des taux affiches par compagnie.
            'average_commission_per_mille' => $summary['gross_revenue'] > 0
                ? (int) round(($summary['commission'] / $summary['gross_revenue']) * 1000)
                : 0,
            'commission_by_company' => $this->reports->commissionByCompany($filters),
            'revenue_by_payment_method' => $this->reports->revenueByPaymentMethod($filters),
        ];
    }
}
