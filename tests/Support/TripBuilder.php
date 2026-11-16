<?php

namespace App\Tests\Support;

use App\Entity\City;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Service\PricingService;

/** Fabrique des trajets en mémoire (tests unitaires) ou prêts à persister (tests d'intégration). */
final class TripBuilder
{
    public static int $n = 0;

    public static function user(string $first = 'Test'): User
    {
        ++self::$n;

        return new User(strtolower($first).self::$n.'@test.local', $first, 'User'.self::$n);
    }

    /**
     * Trajet A → B → C… ; un tronçon par entrée de $legKm (prix = 1 MAD/km arrondi).
     *
     * @param list<float> $legKm
     */
    public static function trip(array $legKm = [100.0, 50.0], int $seats = 3, ?\DateTimeImmutable $start = null, ?User $driver = null): Trip
    {
        $driver ??= self::user('Driver');
        $vehicle = new Vehicle($driver, 'Dacia', 'Logan', 'Blanche', 'PLATE-'.(++self::$n), 5);
        $trip = new Trip($driver, $vehicle, $seats, '1.00');
        $at = $start ?? new \DateTimeImmutable('+3 days');
        $stops = [];
        foreach (array_merge([0.0], $legKm) as $i => $km) {
            $city = new City('Ville'.self::$n.'_'.$i, 'Région', '30.000000', '-9.000000');
            $arrive = 0 === $i ? $at : $at->modify('+1 hour');
            $leave = $i === count($legKm) ? $arrive : $arrive->modify('+10 minutes');
            $stops[] = $trip->addStop($city, $arrive, $leave);
            $at = $leave;
        }
        $pricing = new PricingService();
        foreach ($legKm as $i => $km) {
            $trip->addLeg($stops[$i], $stops[$i + 1], (string) $km, $pricing->legPrice($km, 1.0));
        }
        $trip->publish();

        return $trip;
    }
}
