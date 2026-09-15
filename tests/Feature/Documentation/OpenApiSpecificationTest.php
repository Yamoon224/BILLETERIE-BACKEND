<?php

namespace Tests\Feature\Documentation;

use App\Domains\Booking\Enums\BookingChannel;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\CarRental\Enums\FuelType;
use App\Domains\CarRental\Enums\RentalVehicleCategory;
use App\Domains\CarRental\Enums\TransmissionType;
use App\Domains\Network\Enums\VehicleClass;
use App\Domains\Partners\Enums\PartnerType;
use App\Domains\Payments\Enums\MobileMoneyProvider;
use App\Domains\Payments\Enums\PaymentMethod;
use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Scheduling\Enums\TripStatus;
use App\Domains\Shared\Http\Controllers\DocumentationController;
use App\Domains\Ticketing\Enums\ScanOutcome;
use App\Domains\Ticketing\Enums\TicketStatus;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La documentation de l'API est ecrite a la main : ce test empeche qu'elle
 * derive du code. Il echoue si une route exposee n'est pas documentee, si une
 * route documentee n'existe plus, ou si un enum documente ne reflete plus
 * l'enum PHP. La coherence est verifiee, pas esperee — et c'est ce contrat que
 * l'equipe de l'application agent Android lira.
 */
class OpenApiSpecificationTest extends TestCase
{
    /** @return list<string> « METHODE /chemin » tels que documentes */
    private function documentedOperations(): array
    {
        $spec = DocumentationController::specificationArray();
        $operations = [];

        foreach ($spec['paths'] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $operations[] = strtoupper($method).' '.$path;
                }
            }
        }

        sort($operations);

        return $operations;
    }

    /** @return list<string> « METHODE /chemin » tels qu'enregistres, prefixe /api retire */
    private function registeredOperations(): array
    {
        $operations = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $operations[] = $method.' /'.substr($route->uri(), 4);
            }
        }

        sort($operations);

        return array_values(array_unique($operations));
    }

    #[Test]
    public function la_specification_est_un_document_openapi_valide(): void
    {
        $spec = DocumentationController::specificationArray();

        $this->assertStringStartsWith('3.', (string) $spec['openapi']);
        $this->assertNotEmpty($spec['info']['title']);
        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
    }

    #[Test]
    public function chaque_route_exposee_est_documentee(): void
    {
        $missing = array_diff($this->registeredOperations(), $this->documentedOperations());

        $this->assertSame([], array_values($missing), 'Routes non documentees dans openapi.yaml.');
    }

    #[Test]
    public function chaque_operation_documentee_existe(): void
    {
        $stale = array_diff($this->documentedOperations(), $this->registeredOperations());

        $this->assertSame([], array_values($stale), 'Operations documentees sans route correspondante.');
    }

    /**
     * @param  list<string>  $values
     */
    #[Test]
    #[DataProvider('enums')]
    public function les_enums_documentes_refletent_les_enums_php(string $schema, array $values): void
    {
        $spec = DocumentationController::specificationArray();

        $this->assertSame($values, $spec['components']['schemas'][$schema]['enum'], "Schema {$schema} desynchronise.");
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function enums(): iterable
    {
        yield 'TripStatus' => ['TripStatus', TripStatus::values()];
        yield 'BookingStatus' => ['BookingStatus', BookingStatus::values()];
        yield 'BookingChannel' => ['BookingChannel', BookingChannel::values()];
        yield 'TicketStatus' => ['TicketStatus', TicketStatus::values()];
        yield 'ScanOutcome' => ['ScanOutcome', ScanOutcome::values()];
        yield 'PaymentMethod' => ['PaymentMethod', PaymentMethod::values()];
        yield 'PaymentStatus' => ['PaymentStatus', PaymentStatus::values()];
        yield 'MobileMoneyProvider' => ['MobileMoneyProvider', MobileMoneyProvider::values()];
        yield 'VehicleClass' => ['VehicleClass', VehicleClass::values()];
        yield 'PartnerType' => ['PartnerType', PartnerType::values()];
        yield 'TransmissionType' => ['TransmissionType', TransmissionType::values()];
        yield 'FuelType' => ['FuelType', FuelType::values()];
        yield 'RentalVehicleCategory' => ['RentalVehicleCategory', RentalVehicleCategory::values()];
    }

    #[Test]
    public function la_documentation_est_servie(): void
    {
        $this->get('/docs')->assertOk()->assertSee('swagger-ui', false);
        $this->getJson('/docs/openapi.json')->assertOk()->assertJsonPath('info.title', DocumentationController::specificationArray()['info']['title']);
    }
}
