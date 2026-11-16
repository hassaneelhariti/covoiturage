<?php

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\City;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\BookingStatus;
use App\Enum\ReviewRole;
use App\Service\GeoService;
use App\Service\PricingService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Données de démonstration réalistes. Mot de passe de tous les comptes : « password123 » (admin : « admin123 »). */
class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $om): void
    {
        if ($om->getRepository(City::class)->count([]) > 0) {
            return; // idempotent : le conteneur Docker peut relancer le chargement
        }
        $pricing = new PricingService();

        $c = [];
        foreach ([
            ['Agadir', 'Souss-Massa', '30.427755', '-9.598107'], ['Taroudant', 'Souss-Massa', '30.472678', '-8.877028'],
            ['Tiznit', 'Souss-Massa', '29.697400', '-9.731300'], ['Essaouira', 'Marrakech-Safi', '31.508500', '-9.759500'],
            ['Safi', 'Marrakech-Safi', '32.299400', '-9.237200'], ['Marrakech', 'Marrakech-Safi', '31.629472', '-7.981084'],
            ['El Jadida', 'Casablanca-Settat', '33.254900', '-8.506100'], ['Casablanca', 'Casablanca-Settat', '33.573110', '-7.589843'],
            ['Rabat', 'Rabat-Salé-Kénitra', '34.020882', '-6.841650'],
        ] as [$n, $r, $lat, $lon]) {
            $om->persist($c[$n] = new City($n, $r, $lat, $lon));
        }

        $mk = function (string $mail, string $first, string $last, string $phone, string $pwd = 'password123', array $roles = []) use ($om): User {
            $u = new User($mail, $first, $last);
            $u->setPhone($phone);
            $u->setRoles($roles);
            $u->setPassword($this->hasher->hashPassword($u, $pwd));
            $om->persist($u);

            return $u;
        };
        $admin = $mk('admin@covoiturage.test', 'Admin', 'Plateforme', '0600000000', 'admin123', ['ROLE_ADMIN']);
        $youssef = $mk('youssef@covoiturage.test', 'Youssef', 'Benali', '0661000001');
        $salma = $mk('salma@covoiturage.test', 'Salma', 'El Idrissi', '0661000002');
        $hamza = $mk('hamza@covoiturage.test', 'Hamza', 'Ouazzani', '0661000003');
        $imane = $mk('imane@covoiturage.test', 'Imane', 'Tazi', '0661000004');
        $karim = $mk('karim@covoiturage.test', 'Karim', 'Amrani', '0661000005');

        $v1 = new Vehicle($youssef, 'Dacia', 'Logan', 'Blanche', '12345-A-1', 5);
        $v2 = new Vehicle($salma, 'Renault', 'Clio', 'Grise', '67890-B-6', 5);
        $v3 = new Vehicle($hamza, 'Peugeot', '508', 'Noire', '24680-D-2', 5); // Hamza est conducteur ET passager
        foreach ([$v1, $v2, $v3] as $v) {
            $om->persist($v);
        }

        $trip = function (User $driver, Vehicle $v, array $names, \DateTimeImmutable $start, int $seats, float $rate = 0.60) use ($om, $c, $pricing): Trip {
            $t = new Trip($driver, $v, $seats, number_format($rate, 2, '.', ''));
            $at = $start;
            $stops = [];
            foreach ($names as $i => $name) {
                $arrive = $at;
                if ($i > 0) {
                    $prev = $c[$names[$i - 1]];
                    $km = GeoService::haversineKm((float) $prev->getLatitude(), (float) $prev->getLongitude(), (float) $c[$name]->getLatitude(), (float) $c[$name]->getLongitude()) * GeoService::ROAD_FACTOR;
                    $arrive = $at->modify('+'.(int) round($km / 75 * 60).' minutes');
                }
                $leave = $i === count($names) - 1 ? $arrive : $arrive->modify('+10 minutes');
                $stops[] = $t->addStop($c[$name], $arrive, $leave, 0 === $i ? 'Gare routière' : null);
                $at = $leave;
            }
            for ($i = 0; $i < count($stops) - 1; ++$i) {
                $a = $c[$names[$i]];
                $b = $c[$names[$i + 1]];
                $km = round(GeoService::haversineKm((float) $a->getLatitude(), (float) $a->getLongitude(), (float) $b->getLatitude(), (float) $b->getLongitude()) * GeoService::ROAD_FACTOR, 2);
                $t->addLeg($stops[$i], $stops[$i + 1], (string) $km, $pricing->legPrice($km, $rate));
            }
            $t->publish();
            $om->persist($t);

            return $t;
        };
        $book = function (Trip $t, User $p, int $from, int $to, int $seats, string $ref, ?\DateTimeImmutable $at = null) use ($om, $pricing): Booking {
            $unit = $pricing->portionPrice($t, $from, $to);
            foreach ($t->getLegs() as $leg) {
                if ($leg->getPosition() >= $from && $leg->getPosition() < $to) {
                    $leg->take($seats);
                }
            }
            $b = new Booking($ref, $t, $p, $t->getStop($from), $t->getStop($to), $seats, $unit * $seats, $at ?? new \DateTimeImmutable());
            $om->persist($b);

            return $b;
        };

        $d = new \DateTimeImmutable('tomorrow 08:00');
        // Trajet avec étapes : réservations sur des portions différentes
        $t1 = $trip($youssef, $v1, ['Agadir', 'Essaouira', 'Marrakech'], $d->modify('+1 day'), 3);
        $book($t1, $imane, 0, 1, 2, 'CV-DEMO0001'); // Agadir → Essaouira
        $book($t1, $karim, 1, 2, 1, 'CV-DEMO0002'); // Essaouira → Marrakech
        $trip($youssef, $v1, ['Marrakech', 'Agadir'], $d->modify('+2 days'), 3, 0.55);
        $trip($salma, $v2, ['Agadir', 'Taroudant', 'Marrakech'], $d->modify('+1 day 2 hours'), 2);
        $trip($salma, $v2, ['Casablanca', 'Rabat'], $d->modify('+3 days'), 3, 0.70);
        $trip($hamza, $v3, ['Agadir', 'Tiznit'], $d->modify('+4 days'), 3);
        $trip($salma, $v2, ['Casablanca', 'El Jadida', 'Safi', 'Essaouira'], $d->modify('+5 days'), 3);

        // Trajet passé et terminé, avec avis
        $past = $trip($youssef, $v1, ['Agadir', 'Marrakech'], new \DateTimeImmutable('-3 days 09:00'), 3);
        $b1 = $book($past, $hamza, 0, 1, 1, 'CV-DEMO0003', new \DateTimeImmutable('-6 days'));
        $b2 = $book($past, $imane, 0, 1, 2, 'CV-DEMO0004', new \DateTimeImmutable('-6 days'));
        $past->complete();
        $b1->complete();
        $b2->complete();
        $om->persist(new Review($past, $hamza, $youssef, ReviewRole::DRIVER, 5, 'Conduite agréable, ponctuel.', new \DateTimeImmutable('-2 days')));
        $om->persist(new Review($past, $imane, $youssef, ReviewRole::DRIVER, 4, 'Trajet confortable.', new \DateTimeImmutable('-2 days')));
        $om->persist(new Review($past, $youssef, $hamza, ReviewRole::PASSENGER, 5, 'Passager sympa.', new \DateTimeImmutable('-2 days')));

        // Trajet annulé par le conducteur
        $cancelled = $trip($salma, $v2, ['Agadir', 'Essaouira'], new \DateTimeImmutable('+1 day 07:00'), 3);
        $b = $book($cancelled, $karim, 0, 1, 1, 'CV-DEMO0005');
        foreach ($cancelled->getLegs() as $leg) {
            $leg->release(1);
        }
        $b->cancel(\App\Enum\Actor::DRIVER, new \DateTimeImmutable('-1 hour'));
        $b->getPayment()->refund(1.0);
        $cancelled->cancel();
        $salma->incrementDriverCancellations();

        $om->flush();
    }
}
