<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Booking\Contracts\BookingRepositoryContract;
use App\Domains\Reporting\Contracts\SalesReportReaderContract;
use App\Domains\Reporting\DTOs\ReportFilters;
use App\Models\Booking;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export des donnees d'activite, exigence explicite du cahier des charges.
 *
 * Trois decisions techniques valent d'etre expliquees.
 *
 * **Format CSV, pas Excel.** Un gestionnaire de compagnie ouvre le fichier
 * dans le tableur qu'il a, quel qu'il soit, et le reimporte dans sa
 * comptabilite. Un XLSX ajouterait une dependance et un format proprietaire
 * pour aucun gain sur des lignes plates.
 *
 * **Diffusion en flux, pas en memoire.** Un export de saison porte sur des
 * dizaines de milliers de lignes : les assembler en memoire avant envoi ferait
 * tomber le processus exactement quand l'export devient utile.
 *
 * **BOM UTF-8 en tete.** Sans elle, Excel sous Windows lit le fichier en
 * ANSI et affiche « Yamoussoukro » avec des caracteres casses. Trois octets
 * evitent un ticket de support par utilisateur.
 */
final class SalesExportService
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

    public function __construct(
        private readonly BookingRepositoryContract $bookings,
        private readonly SalesReportReaderContract $reports,
    ) {}

    /** Export detaille des ventes, une ligne par reservation. */
    public function bookingsCsv(ReportFilters $filters): StreamedResponse
    {
        $filename = sprintf('ventes_%s_%s.csv', $filters->from->format('Ymd'), $filters->to->format('Ymd'));

        return $this->stream($filename, function ($handle) use ($filters): void {
            fputcsv($handle, [
                'Reference', 'Date de vente', 'Canal', 'Statut', 'Depart', 'Heure de depart',
                'Origine', 'Destination', 'Client', 'Telephone', 'Places',
                'Montant', 'Commission', 'Net compagnie', 'Agent',
            ], ';');

            $this->eachBooking($filters, function (Booking $booking) use ($handle): void {
                fputcsv($handle, [
                    $booking->reference,
                    $booking->created_at?->format('d/m/Y H:i'),
                    $booking->channel->label(),
                    $booking->status->label(),
                    $booking->trip->reference,
                    $booking->trip->departs_at->format('d/m/Y H:i'),
                    $booking->trip->itinerary->originCity->name,
                    $booking->trip->itinerary->destinationCity->name,
                    $booking->customer_name,
                    $booking->customer_phone,
                    $booking->seats_count,
                    $booking->total_amount,
                    $booking->commission_amount,
                    $booking->netAmount(),
                    // Pas d agent : la vente vient du site voyageur.
                    $booking->sold_by_user_id !== null ? $booking->soldBy->name : 'En ligne',
                ], ';');
            });
        });
    }

    /** Export du taux de remplissage, une ligne par depart. */
    public function occupancyCsv(ReportFilters $filters): StreamedResponse
    {
        $filename = sprintf('remplissage_%s_%s.csv', $filters->from->format('Ymd'), $filters->to->format('Ymd'));

        return $this->stream($filename, function ($handle) use ($filters): void {
            fputcsv($handle, [
                'Depart', 'Date', 'Origine', 'Destination', 'Capacite',
                'Places vendues', 'Taux de remplissage', 'Recette',
            ], ';');

            foreach ($this->reports->occupancyByTrip($filters, 5000) as $row) {
                fputcsv($handle, [
                    $row['reference'],
                    substr($row['departs_at'], 0, 16),
                    $row['origin'],
                    $row['destination'],
                    $row['capacity'],
                    $row['sold'],
                    // Virgule decimale : le tableur francophone du
                    // destinataire ne reconnait pas le point comme separateur.
                    str_replace('.', ',', (string) round($row['occupancy_rate'] * 100, 1)).' %',
                    $row['revenue'],
                ], ';');
            }
        });
    }

    /**
     * Parcourt les reservations par lots.
     *
     * @param  callable(Booking): void  $callback
     */
    private function eachBooking(ReportFilters $filters, callable $callback): void
    {
        $page = 1;

        do {
            $chunk = $this->bookings->paginate([
                'company_id' => $filters->companyId,
                'station_id' => $filters->stationId,
                'from' => $filters->from,
                'to' => $filters->to,
                'sort' => 'created_at',
                'direction' => 'asc',
                'page' => $page,
            ], 500);

            foreach ($chunk->items() as $booking) {
                $callback($booking);
            }

            $page++;
        } while ($chunk->hasMorePages());
    }

    private function stream(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, self::UTF8_BOM);
            $writer($handle);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
