<?php

namespace App\Controller;

use App\Entity\City;
use App\Repository\TripRepository;
use App\Service\PricingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, EntityManagerInterface $em, TripRepository $trips, PricingService $pricing, ClockInterface $clock): Response
    {
        $cities = $em->getRepository(City::class)->findBy([], ['name' => 'ASC']);
        $from = $em->find(City::class, $request->query->getInt('from'));
        $to = $em->find(City::class, $request->query->getInt('to'));
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('date'));
        $results = null;

        if ($from && $to && $day) {
            $results = [];
            foreach ($trips->search($from, $to, $day, \DateTimeImmutable::createFromInterface($clock->now())) as [$a, $b]) {
                $trip = $a->getTrip();
                $results[] = [
                    'trip' => $trip, 'from' => $a, 'to' => $b,
                    'seats' => $trip->availableSeats($a->getPosition(), $b->getPosition()),
                    'price' => $pricing->portionPrice($trip, $a->getPosition(), $b->getPosition()),
                ];
            }
        }

        return $this->render('home/index.html.twig', [
            'cities' => $cities, 'results' => $results, 'from' => $from, 'to' => $to,
            'date' => $day?->format('Y-m-d') ?? $clock->now()->format('Y-m-d'),
        ]);
    }
}
