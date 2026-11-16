<?php

namespace App\Controller;

use App\Entity\City;
use App\Entity\Trip;
use App\Entity\TripStop;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\BookingStatus;
use App\Enum\TripStatus;
use App\Exception\RuleViolation;
use App\Service\BookingService;
use App\Service\PricingService;
use App\Service\ReviewService;
use App\Service\TripService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TripController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/trips/{id}', name: 'app_trip_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Trip $trip, Request $request, ReviewService $reviews): Response
    {
        $user = $this->getUser();
        $legs = array_map(fn ($l) => ['p' => $l->getPosition(), 'price' => $l->getPriceMad(), 'free' => $l->remaining()], $trip->getLegs()->toArray());

        return $this->render('trip/show.html.twig', [
            'trip' => $trip,
            'legsJson' => json_encode($legs),
            'preFrom' => $request->query->getInt('from', 0),
            'preTo' => $request->query->getInt('to', $trip->getStops()->count() - 1),
            'pending' => $user instanceof User ? $reviews->pendingTargets($user, $trip) : [],
            'passengers' => array_filter($trip->getBookings()->toArray(), fn ($b) => BookingStatus::CONFIRMED === $b->getStatus() || BookingStatus::COMPLETED === $b->getStatus()),
        ]);
    }

    #[Route('/trips/{id}/book', name: 'app_trip_book', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function book(Trip $trip, Request $request, BookingService $bookings): Response
    {
        if (!$this->isCsrfTokenValid('book'.$trip->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $from = $this->em->find(TripStop::class, $request->request->getInt('from'));
        $to = $this->em->find(TripStop::class, $request->request->getInt('to'));
        try {
            if (!$from || !$to) {
                throw new RuleViolation('Choisissez un arrêt de montée et de descente.');
            }
            /** @var User $user */
            $user = $this->getUser();
            $booking = $bookings->book($trip, $user, $from, $to, $request->request->getInt('seats', 1));
            $this->addFlash('success', sprintf('Réservation %s confirmée — %d MAD (paiement simulé).', $booking->getReference(), $booking->getTotalPriceMad()));

            return $this->redirectToRoute('app_my_bookings');
        } catch (RuleViolation $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
        }
    }

    #[Route('/driver/vehicles/new', name: 'app_vehicle_new', methods: ['GET', 'POST'])]
    public function newVehicle(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('vehicle', (string) $request->request->get('_token'))) {
            $plate = strtoupper(trim((string) $request->request->get('plate')));
            $seats = $request->request->getInt('seats');
            if ('' === $plate || $seats < 2 || $seats > 9 || $this->em->getRepository(Vehicle::class)->findOneBy(['plate' => $plate])) {
                $this->addFlash('error', 'Données invalides ou plaque déjà enregistrée (2 à 9 places).');
            } else {
                $this->em->persist(new Vehicle($user, trim((string) $request->request->get('make')), trim((string) $request->request->get('model')), trim((string) $request->request->get('color')), $plate, $seats));
                $this->em->flush();
                $this->addFlash('success', 'Véhicule enregistré : vous pouvez publier un trajet.');

                return $this->redirectToRoute('app_trip_new');
            }
        }

        return $this->render('driver/vehicle_new.html.twig');
    }

    #[Route('/driver/trips/new', name: 'app_trip_new', methods: ['GET', 'POST'])]
    public function new(Request $request, TripService $trips, PricingService $pricing): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getVehicles()->isEmpty()) {
            $this->addFlash('error', "Enregistrez d'abord un véhicule pour publier un trajet.");

            return $this->redirectToRoute('app_vehicle_new');
        }
        if ($request->isMethod('POST')) {
            try {
                if (!$this->isCsrfTokenValid('trip', (string) $request->request->get('_token'))) {
                    throw new RuleViolation('Formulaire expiré, réessayez.');
                }
                $vehicle = $this->em->find(Vehicle::class, $request->request->getInt('vehicle'));
                if (!$vehicle) {
                    throw new RuleViolation('Véhicule introuvable.');
                }
                $stops = [];
                foreach ((array) $request->request->all('stops') as $row) {
                    $city = $this->em->find(City::class, (int) ($row['city'] ?? 0));
                    if (!$city) {
                        continue;
                    }
                    $arr = $this->parseDate($row['arrival'] ?? '');
                    $dep = $this->parseDate($row['departure'] ?? '');
                    if (!$arr && !$dep) {
                        throw new RuleViolation('Chaque arrêt a besoin d\'un horaire.');
                    }
                    $stops[] = ['city' => $city, 'arrival' => $arr ?? $dep, 'departure' => $dep ?? $arr, 'meeting' => trim((string) ($row['meeting'] ?? '')) ?: null];
                }
                $rate = (float) str_replace(',', '.', (string) $request->request->get('rate', PricingService::DEFAULT_RATE_PER_KM));
                $trip = $trips->create($user, $vehicle, $request->request->getInt('seats'), $rate, $stops, trim((string) $request->request->get('note')) ?: null);
                $this->addFlash('success', 'Trajet publié.');

                return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
            } catch (RuleViolation $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('driver/trip_new.html.twig', [
            'cities' => $this->em->getRepository(City::class)->findBy([], ['name' => 'ASC']),
            'defaultRate' => PricingService::DEFAULT_RATE_PER_KM,
        ]);
    }

    /** Niveau 4 : distance routière réelle (OSRM) → prix suggéré + tracé pour la carte. */
    #[Route('/driver/trips/suggest', name: 'app_trip_suggest', methods: ['GET'])]
    public function suggest(Request $request, TripService $trips, PricingService $pricing): JsonResponse
    {
        $cities = [];
        foreach ((array) $request->query->all('cities') as $id) {
            if ($c = $this->em->find(City::class, (int) $id)) {
                $cities[] = $c;
            }
        }
        if (count($cities) < 2) {
            return new JsonResponse(['error' => 'Choisissez au moins deux villes.'], 400);
        }
        $rate = (float) str_replace(',', '.', (string) $request->query->get('rate', PricingService::DEFAULT_RATE_PER_KM));
        $route = $trips->previewRoute($cities);

        return new JsonResponse($route + [
            'totalKm' => round(array_sum($route['legsKm']), 1),
            'suggestedPrice' => $pricing->suggestedTotal($route['legsKm'], $rate > 0 ? $rate : PricingService::DEFAULT_RATE_PER_KM),
            'points' => array_map(fn (City $c) => ['name' => $c->getName(), 'lat' => (float) $c->getLatitude(), 'lon' => (float) $c->getLongitude()], $cities),
        ]);
    }

    #[Route('/driver/trips', name: 'app_driver_trips', methods: ['GET'])]
    public function mine(): Response
    {
        $trips = $this->em->getRepository(Trip::class)->findBy(['driver' => $this->getUser()], ['id' => 'DESC']);

        return $this->render('driver/trips.html.twig', [
            'upcoming' => array_filter($trips, fn (Trip $t) => TripStatus::PUBLISHED === $t->getStatus()),
            'history' => array_filter($trips, fn (Trip $t) => TripStatus::PUBLISHED !== $t->getStatus()),
        ]);
    }

    #[Route('/driver/trips/{id}/cancel', name: 'app_trip_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Trip $trip, Request $request, BookingService $bookings): Response
    {
        return $this->act($request, 'cancel'.$trip->getId(), function () use ($trip, $bookings) {
            $bookings->cancelByDriver($trip, $this->getUser());

            return 'Trajet annulé : tous les passagers sont remboursés intégralement.';
        });
    }

    #[Route('/driver/trips/{id}/complete', name: 'app_trip_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function complete(Trip $trip, Request $request, TripService $trips): Response
    {
        return $this->act($request, 'complete'.$trip->getId(), function () use ($trip, $trips) {
            $trips->complete($trip, $this->getUser());

            return 'Trajet terminé : les avis sont maintenant ouverts.';
        });
    }

    /** @param callable():string $do */
    private function act(Request $request, string $tokenId, callable $do): Response
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $this->addFlash('success', $do());
        } catch (RuleViolation $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_driver_trips');
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) $value);

        return false === $d ? null : $d;
    }
}
