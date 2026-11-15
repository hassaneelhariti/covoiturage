<?php

namespace App\Service;

use App\Entity\City;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\BookingStatus;
use App\Enum\TripStatus;
use App\Exception\RuleViolation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class TripService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GeoService $geo,
        private readonly PricingService $pricing,
        private readonly TripLocker $locker,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Distances par tronçon : OSRM si disponible, sinon estimation à vol d'oiseau.
     *
     * @param list<City> $cities
     *
     * @return array{legsKm: list<float>, geometry: ?array<string, mixed>, source: string}
     */
    public function previewRoute(array $cities): array
    {
        $points = array_map(fn (City $c) => [(float) $c->getLatitude(), (float) $c->getLongitude()], $cities);
        $route = $this->geo->route($points);
        if (null !== $route && count($route['legsKm']) === count($cities) - 1) {
            return ['legsKm' => $route['legsKm'], 'geometry' => $route['geometry'], 'source' => 'osrm'];
        }
        $km = [];
        for ($i = 0; $i < count($points) - 1; ++$i) {
            $km[] = round(GeoService::haversineKm(...$points[$i], ...$points[$i + 1]) * GeoService::ROAD_FACTOR, 2);
        }

        return ['legsKm' => $km, 'geometry' => null, 'source' => 'estimate'];
    }

    /**
     * @param list<array{city: City, arrival: \DateTimeImmutable, departure: \DateTimeImmutable, meeting: ?string}> $stops
     */
    public function create(User $driver, Vehicle $vehicle, int $seatsTotal, float $ratePerKm, array $stops, ?string $note): Trip
    {
        $now = $this->clock->now();
        if (!$vehicle->getOwner()->sameAs($driver)) {
            throw new RuleViolation("Ce véhicule n'est pas le vôtre.");
        }
        if ($seatsTotal < 1 || $seatsTotal > $vehicle->getSeats() - 1) {
            throw new RuleViolation(sprintf('Proposez de 1 à %d places (le conducteur occupe une place).', $vehicle->getSeats() - 1));
        }
        if ($ratePerKm <= 0 || $ratePerKm > 5) {
            throw new RuleViolation('Le tarif au km doit être compris entre 0 et 5 MAD.');
        }
        if (count($stops) < 2) {
            throw new RuleViolation('Indiquez au moins un départ et une arrivée.');
        }
        foreach ($stops as $i => $s) {
            if ($s['departure'] < $s['arrival']) {
                throw new RuleViolation('Un arrêt ne peut pas partir avant son arrivée.');
            }
            if (isset($stops[$i + 1])) {
                if ($stops[$i + 1]['city'] === $s['city']) {
                    throw new RuleViolation('Deux arrêts consécutifs ne peuvent pas être dans la même ville.');
                }
                if ($stops[$i + 1]['arrival'] <= $s['departure']) {
                    throw new RuleViolation("Les horaires doivent être strictement croissants d'un arrêt au suivant.");
                }
            }
        }
        if ($stops[0]['departure'] <= $now) {
            throw new RuleViolation('Le départ doit être dans le futur.');
        }

        $route = $this->previewRoute(array_map(fn ($s) => $s['city'], $stops));
        $trip = new Trip($driver, $vehicle, $seatsTotal, number_format($ratePerKm, 2, '.', ''));
        $trip->setNote($note);
        $trip->setRouteGeometry($route['geometry']);
        $created = [];
        foreach ($stops as $s) {
            $created[] = $trip->addStop($s['city'], $s['arrival'], $s['departure'], $s['meeting']);
        }
        foreach ($route['legsKm'] as $i => $km) {
            $trip->addLeg($created[$i], $created[$i + 1], number_format($km, 2, '.', ''), $this->pricing->legPrice($km, $ratePerKm));
        }
        $trip->publish();
        $this->em->persist($trip);
        $this->em->flush();

        return $trip;
    }

    /** Le conducteur clôture le trajet une fois arrivé ; les réservations confirmées passent à « terminée ». */
    public function complete(Trip $trip, User $actor): void
    {
        if (!$trip->getDriver()->sameAs($actor)) {
            throw new RuleViolation('Seul le conducteur peut terminer ce trajet.');
        }
        $this->locker->transactional(function () use ($trip): void {
            $this->locker->lock($trip);
            if (TripStatus::PUBLISHED !== $trip->getStatus()) {
                throw new RuleViolation('Ce trajet ne peut pas être terminé.');
            }
            if ($this->clock->now() < $trip->arrivalAt()) {
                throw new RuleViolation("Le trajet n'a pas encore atteint son arrivée prévue.");
            }
            $trip->complete();
            foreach ($trip->getBookings() as $booking) {
                if (BookingStatus::CONFIRMED === $booking->getStatus()) {
                    $booking->complete();
                }
            }
        });
    }

    /** Une fois des réservations confirmées, horaires et arrêts sont figés : il faut annuler le trajet. */
    public function assertStructureEditable(Trip $trip): void
    {
        foreach ($trip->getBookings() as $booking) {
            if (BookingStatus::CONFIRMED === $booking->getStatus()) {
                throw new RuleViolation('Des passagers ont réservé : horaires et arrêts ne sont plus modifiables. Annulez le trajet pour le republier.');
            }
        }
    }
}
