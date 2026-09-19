<?php

namespace Database\Seeders;

use App\Domains\Booking\DTOs\BookingDraft;
use App\Domains\Booking\DTOs\PassengerDraft;
use App\Domains\Booking\Enums\BookingChannel;
use App\Domains\Booking\Services\BookingService;
use App\Domains\CarRental\Enums\FuelType;
use App\Domains\CarRental\Enums\RentalVehicleCategory;
use App\Domains\CarRental\Enums\TransmissionType;
use App\Domains\Housing\Enums\ApartmentAmenity;
use App\Domains\Network\Enums\VehicleClass;
use App\Domains\Partners\Enums\PartnerType;
use App\Domains\Payments\Enums\MobileMoneyProvider;
use App\Domains\Payments\Services\PaymentService;
use App\Domains\Scheduling\Services\TripService;
use App\Models\Apartment;
use App\Models\City;
use App\Models\Company;
use App\Models\Itinerary;
use App\Models\Partner;
use App\Models\RentalVehicle;
use App\Models\RouteGridEntry;
use App\Models\Station;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Jeu de demonstration : un reseau ivoirien credible, avec des ventes.
 *
 * Les reservations sont creees **par les services du domaine**, pas par des
 * insertions directes. C'est plus lent, et c'est voulu : un jeu de
 * demonstration fabrique a la main finit toujours par contenir des etats
 * impossibles — une reservation confirmee sans encaissement, deux billets sur
 * la meme place — qui font perdre une journee a chercher un bug qui n'existe
 * pas dans le code. Ici, si le seeder passe, c'est que le domaine accepte ces
 * donnees.
 */
class DemoDataSeeder extends Seeder
{
    /** @var list<array{name: string, slug: string, region: string}> */
    private const CITIES = [
        ['name' => 'Abidjan', 'slug' => 'abidjan', 'region' => 'Lagunes'],
        ['name' => 'Yamoussoukro', 'slug' => 'yamoussoukro', 'region' => 'Lacs'],
        ['name' => 'Bouake', 'slug' => 'bouake', 'region' => 'Vallee du Bandama'],
        ['name' => 'Korhogo', 'slug' => 'korhogo', 'region' => 'Savanes'],
        ['name' => 'San-Pedro', 'slug' => 'san-pedro', 'region' => 'Bas-Sassandra'],
        ['name' => 'Daloa', 'slug' => 'daloa', 'region' => 'Haut-Sassandra'],
        ['name' => 'Man', 'slug' => 'man', 'region' => 'Montagnes'],
        ['name' => 'Abengourou', 'slug' => 'abengourou', 'region' => 'Comoe'],
        // Ligne pilote Bonoua - Treichville, preselectionnee dans la recherche
        // du site.
        ['name' => 'Bonoua', 'slug' => 'bonoua', 'region' => 'Sud-Comoe'],
        ['name' => 'Treichville', 'slug' => 'treichville', 'region' => 'Lagunes'],
        // Villes balneaires : hors reseau de bus (aucune liaison pilote ne les
        // dessert), mais destinations des piliers Appartements et Location
        // auto — d'ou leur presence ici malgre l'absence de gare programmee.
        ['name' => 'Assinie', 'slug' => 'assinie', 'region' => 'Sud-Comoe'],
        ['name' => 'Grand-Bassam', 'slug' => 'grand-bassam', 'region' => 'Sud-Comoe'],
        ['name' => 'Jacqueville', 'slug' => 'jacqueville', 'region' => 'Grands-Ponts'],
    ];

    public function run(): void
    {
        $cities = $this->seedCities();
        $companies = $this->seedCompanies();

        $admin = $this->seedPlatformAdmin();

        foreach ($companies as $company) {
            $this->seedCompanyNetwork($company, $cities);
        }

        $this->seedSales();
        $this->seedPartners($cities);
        $this->seedRouteGrid($cities);

        $this->command->info("Compte administrateur : {$admin->email} / password");
    }

    /** @return array<string, City> */
    private function seedCities(): array
    {
        $cities = [];

        foreach (self::CITIES as $definition) {
            $cities[$definition['slug']] = City::firstOrCreate(
                ['slug' => $definition['slug']],
                ['name' => $definition['name'], 'region' => $definition['region'], 'is_active' => true],
            );
        }

        return $cities;
    }

    /**
     * Grille des trajets : ce que le site affiche, avec « Bientot disponible »,
     * pour les liaisons autres que la ligne pilote.
     *
     * @param  array<string, City>  $cities
     */
    private function seedRouteGrid(array $cities): void
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: int, 4: int, 5: int, 6: list<string>}> $rows */
        $rows = [
            ['abidjan', 'yamoussoukro', 'UTB', 5000, 240, 180, ['06:00', '12:00', '18:00']],
            ['abidjan', 'bouake', 'UTB', 7000, 350, 300, ['06:00', '13:00']],
            ['abidjan', 'bouake', 'STC', 7500, 350, 300, ['08:00', '15:00']],
            ['abidjan', 'san-pedro', 'STC', 8000, 340, 330, ['07:00', '19:00']],
            ['abidjan', 'daloa', 'UTB', 8500, 380, 360, ['06:30', '14:00']],
            ['bouake', 'korhogo', 'UTB', 6000, 280, 270, ['07:00', '16:00']],
        ];

        foreach ($rows as [$origin, $destination, $company, $price, $km, $minutes, $times]) {
            RouteGridEntry::firstOrCreate(
                [
                    'origin_city_id' => $cities[$origin]->id,
                    'destination_city_id' => $cities[$destination]->id,
                    'company_name' => $company,
                ],
                [
                    'price' => $price,
                    'distance_km' => $km,
                    'duration_minutes' => $minutes,
                    'departure_times' => $times,
                    'is_active' => true,
                ],
            );
        }
    }

    /** @return list<Company> */
    private function seedCompanies(): array
    {
        return [
            Company::firstOrCreate(['code' => 'UTB'], [
                'name' => 'Union des Transports de Bouake',
                'legal_name' => 'UTB SA',
                'phone' => '+2252722445566',
                'email' => 'contact@utb.example',
                'commission_per_mille' => null,
                'is_active' => true,
            ]),
            Company::firstOrCreate(['code' => 'STC'], [
                'name' => 'Societe de Transport Cotier',
                'legal_name' => 'STC SARL',
                'phone' => '+2252734556677',
                'email' => 'contact@stc.example',
                // Taux negocie, distinct du bareme de la plateforme.
                'commission_per_mille' => 20,
                'is_active' => true,
            ]),
        ];
    }

    private function seedPlatformAdmin(): User
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@billetterie.test'],
            [
                'name' => 'Administrateur plateforme',
                'phone' => '+2250700000001',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );

        $admin->syncRoles(['platform_admin']);

        return $admin;
    }

    /** @param  array<string, City>  $cities */
    private function seedCompanyNetwork(Company $company, array $cities): void
    {
        $suffix = strtolower($company->code);

        $manager = User::firstOrCreate(
            ['email' => "gestionnaire.{$suffix}@billetterie.test"],
            [
                'name' => 'Gestionnaire '.$company->code,
                'phone' => '+225070000'.random_int(1000, 9999),
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'is_active' => true,
            ],
        );
        $manager->syncRoles(['company_manager']);

        $agent = User::firstOrCreate(
            ['email' => "agent.{$suffix}@billetterie.test"],
            [
                'name' => 'Agent guichet '.$company->code,
                'phone' => '+225070000'.random_int(1000, 9999),
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'is_active' => true,
            ],
        );
        $agent->syncRoles(['agent']);

        // Gares partagees : une par ville, comme les gares routieres reelles.
        $stations = [];

        foreach ($cities as $slug => $city) {
            $stations[$slug] = Station::firstOrCreate(
                ['city_id' => $city->id, 'company_id' => null, 'name' => 'Gare routiere de '.$city->name],
                ['address' => 'Quartier central, '.$city->name, 'is_active' => true],
            );
        }

        $vehicles = [];

        foreach ([['standard', VehicleClass::Standard, 70, 4], ['vip', VehicleClass::Vip, 40, 3]] as [$key, $class, $capacity, $perRow]) {
            $vehicles[$key] = Vehicle::firstOrCreate(
                ['registration' => strtoupper($company->code).'-'.strtoupper($key).'-01'],
                [
                    'company_id' => $company->id,
                    'model' => $class === VehicleClass::Vip ? 'Yutong ZK6122 VIP' : 'Higer KLQ6128',
                    'class' => $class,
                    'seat_capacity' => $capacity,
                    'seats_per_row' => $perRow,
                    'is_active' => true,
                ],
            );
        }

        /** @var list<array{0: string, 1: string, 2: int, 3: int, 4: int}> $liaisons */
        $liaisons = [
            ['abidjan', 'yamoussoukro', 240, 180, 5000],
            ['abidjan', 'bouake', 350, 300, 7000],
            ['abidjan', 'san-pedro', 340, 330, 8000],
            ['bouake', 'korhogo', 280, 270, 6000],
            ['abidjan', 'daloa', 380, 360, 8500],
            // Ligne pilote, dans les deux sens : seule liaison reservable en ligne.
            ['bonoua', 'treichville', 55, 70, 1500],
            ['treichville', 'bonoua', 55, 70, 1500],
        ];

        $tripService = app(TripService::class);

        foreach ($liaisons as [$origin, $destination, $km, $minutes, $price]) {
            $itinerary = Itinerary::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'origin_city_id' => $cities[$origin]->id,
                    'destination_city_id' => $cities[$destination]->id,
                ],
                [
                    'distance_km' => $km,
                    'duration_minutes' => $minutes,
                    'base_price' => $price,
                    'is_active' => true,
                ],
            );

            if ($itinerary->trips()->exists()) {
                continue;
            }

            // Trois departs quotidiens sur sept jours : de quoi remplir un
            // ecran de recherche sans noyer la base de demonstration.
            foreach (range(0, 6) as $day) {
                foreach ([6, 12, 18] as $index => $hour) {
                    $vehicle = $index === 1 ? $vehicles['vip'] : $vehicles['standard'];

                    $tripService->create(
                        [
                            'departure_station_id' => $stations[$origin]->id,
                            'arrival_station_id' => $stations[$destination]->id,
                            'departs_at' => Carbon::tomorrow()->addDays($day)->setTime($hour, 0)->toDateTimeString(),
                            'price' => $vehicle->class === VehicleClass::Vip ? $price + 2000 : $price,
                        ],
                        $itinerary,
                        $vehicle,
                    );
                }
            }
        }
    }

    /**
     * Quelques ventes, passees par les services du domaine.
     *
     * Un melange volontaire : des ventes en ligne payees par mobile money, des
     * ventes au guichet en especes, et une reservation laissee en attente. Sans
     * cette derniere, le tableau de bord n'aurait aucun blocage a montrer et la
     * commande d'expiration n'aurait rien a traiter le jour de la
     * demonstration.
     */
    private function seedSales(): void
    {
        $bookings = app(BookingService::class);
        $payments = app(PaymentService::class);

        $agents = User::role('agent')->get();

        $trips = Trip::query()
            ->with('company')
            ->orderBy('departs_at')
            ->limit(12)
            ->get();

        foreach ($trips as $index => $trip) {
            $seatMap = $trip->seatMap()->seatNumbers();
            $agent = $agents->firstWhere('company_id', $trip->company_id);

            // Deux ventes par depart, sur des places distinctes.
            $online = $bookings->reserve(new BookingDraft(
                tripId: $trip->id,
                channel: BookingChannel::Online,
                customerName: 'Client en ligne '.($index + 1),
                customerPhone: '+22507'.str_pad((string) (1000000 + $index), 8, '0', STR_PAD_LEFT),
                customerEmail: 'client'.($index + 1).'@example.test',
                passengers: [
                    new PassengerDraft($seatMap[0], 'Client en ligne '.($index + 1)),
                    new PassengerDraft($seatMap[1], 'Accompagnant '.($index + 1)),
                ],
            ));

            // Une reservation sur quatre reste en attente de paiement.
            if ($index % 4 !== 3) {
                $payments->payWithMobileMoney(
                    $online,
                    MobileMoneyProvider::OrangeMoney,
                    $online->customer_phone,
                );
            }

            $counter = $bookings->reserve(new BookingDraft(
                tripId: $trip->id,
                channel: BookingChannel::Counter,
                customerName: 'Client guichet '.($index + 1),
                customerPhone: '+22505'.str_pad((string) (2000000 + $index), 8, '0', STR_PAD_LEFT),
                customerEmail: null,
                passengers: [new PassengerDraft($seatMap[2], 'Client guichet '.($index + 1))],
                soldByUserId: $agent?->id,
                stationId: $trip->departure_station_id,
                clientReference: 'DEMO-'.Str::upper(Str::random(10)),
            ));

            $payments->collectCash($counter, $agent?->id);
        }

        $this->command->info($trips->count() * 2 .' reservations de demonstration creees.');
    }

    /**
     * Partenaires de demonstration pour les deux autres piliers : quelques
     * appartements sur la cote (Assinie, Grand-Bassam, Jacqueville) et
     * quelques vehicules de location a Abidjan, avec un gestionnaire de
     * partenaire pour montrer le perimetre `partner_manager`.
     *
     * @param  array<string, City>  $cities
     */
    private function seedPartners(array $cities): void
    {
        $housingPartner = Partner::firstOrCreate(
            ['name' => 'Cote Emeraude Residences'],
            [
                'type' => PartnerType::Housing,
                'phone' => '+2252721556677',
                'whatsapp' => '+2250701556677',
                'email' => 'contact@cote-emeraude.example',
                'city_id' => $cities['grand-bassam']->id,
                'description' => 'Appartements meubles sur la cote, geres par une agence locale.',
                'is_active' => true,
            ],
        );

        $rentalPartner = Partner::firstOrCreate(
            ['name' => 'Wharf Location Auto'],
            [
                'type' => PartnerType::CarRental,
                'phone' => '+2252722667788',
                'whatsapp' => '+2250701667788',
                'email' => 'contact@wharf-location.example',
                'city_id' => $cities['abidjan']->id,
                'description' => 'Location de vehicules a l\'aeroport d\'Abidjan et en ville.',
                'is_active' => true,
            ],
        );

        $partnerManager = User::firstOrCreate(
            ['email' => 'gestionnaire.partenaires@billetterie.test'],
            [
                'name' => 'Gestionnaire partenaires',
                'phone' => '+2250700009999',
                'password' => Hash::make('password'),
                'partner_id' => $housingPartner->id,
                'is_active' => true,
            ],
        );
        $partnerManager->syncRoles(['partner_manager']);

        /** @var list<array{title: string, city: string, neighborhood: string, bedrooms: int, bathrooms: int, capacity: int, price: int, featured: bool}> */
        $apartments = [
            ['title' => 'Studio vue lagune', 'city' => 'assinie', 'neighborhood' => 'Assinie Plage', 'bedrooms' => 1, 'bathrooms' => 1, 'capacity' => 2, 'price' => 15000, 'featured' => true],
            ['title' => 'Appartement 2 chambres pieds dans l\'eau', 'city' => 'assinie', 'neighborhood' => 'Assinie Plage', 'bedrooms' => 2, 'bathrooms' => 2, 'capacity' => 4, 'price' => 35000, 'featured' => true],
            ['title' => 'Villa coloniale renovee', 'city' => 'grand-bassam', 'neighborhood' => 'Grand-Bassam France', 'bedrooms' => 3, 'bathrooms' => 2, 'capacity' => 6, 'price' => 45000, 'featured' => true],
            ['title' => 'Studio proche du musee', 'city' => 'grand-bassam', 'neighborhood' => 'Grand-Bassam Centre', 'bedrooms' => 1, 'bathrooms' => 1, 'capacity' => 2, 'price' => 18000, 'featured' => false],
            ['title' => 'Appartement familial vue mer', 'city' => 'jacqueville', 'neighborhood' => 'Jacqueville Centre', 'bedrooms' => 2, 'bathrooms' => 1, 'capacity' => 5, 'price' => 25000, 'featured' => false],
        ];

        foreach ($apartments as $definition) {
            Apartment::firstOrCreate(
                ['partner_id' => $housingPartner->id, 'title' => $definition['title']],
                [
                    'city_id' => $cities[$definition['city']]->id,
                    'description' => 'Appartement meuble, cuisine equipee, a quelques minutes de la plage.',
                    'neighborhood' => $definition['neighborhood'],
                    'address_line' => null,
                    'bedrooms' => $definition['bedrooms'],
                    'bathrooms' => $definition['bathrooms'],
                    'capacity' => $definition['capacity'],
                    'price_per_night' => $definition['price'],
                    'amenities' => [ApartmentAmenity::Wifi->value, ApartmentAmenity::AirConditioning->value, ApartmentAmenity::Parking->value],
                    'is_featured' => $definition['featured'],
                    'is_active' => true,
                ],
            );
        }

        /** @var list<array{brand: string, model: string, category: RentalVehicleCategory, seats: int, price: int, featured: bool}> */
        $rentalVehicles = [
            ['brand' => 'Toyota', 'model' => 'Yaris', 'category' => RentalVehicleCategory::Citadine, 'seats' => 5, 'price' => 25000, 'featured' => true],
            ['brand' => 'Hyundai', 'model' => 'Tucson', 'category' => RentalVehicleCategory::Suv, 'seats' => 5, 'price' => 45000, 'featured' => true],
            ['brand' => 'Toyota', 'model' => 'Land Cruiser Prado', 'category' => RentalVehicleCategory::Suv, 'seats' => 7, 'price' => 65000, 'featured' => true],
            ['brand' => 'Renault', 'model' => 'Symbol', 'category' => RentalVehicleCategory::Berline, 'seats' => 5, 'price' => 30000, 'featured' => false],
            ['brand' => 'Toyota', 'model' => 'Hiace', 'category' => RentalVehicleCategory::Minibus, 'seats' => 14, 'price' => 55000, 'featured' => false],
        ];

        foreach ($rentalVehicles as $index => $definition) {
            RentalVehicle::firstOrCreate(
                ['partner_id' => $rentalPartner->id, 'brand' => $definition['brand'], 'model' => $definition['model']],
                [
                    'city_id' => $cities['abidjan']->id,
                    'year' => 2024,
                    'category' => $definition['category'],
                    'transmission' => TransmissionType::Automatic,
                    'fuel_type' => FuelType::Petrol,
                    'seats' => $definition['seats'],
                    'price_per_day' => $definition['price'],
                    'with_driver_available' => true,
                    'plate_number' => 'CI-DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'is_featured' => $definition['featured'],
                    'is_active' => true,
                ],
            );
        }

        $this->command->info(count($apartments).' appartements et '.count($rentalVehicles).' vehicules de location crees.');
    }
}
