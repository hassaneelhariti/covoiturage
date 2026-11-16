<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use App\Exception\RuleViolation;
use App\Repository\ReviewRepository;
use App\Enum\ReviewRole;
use App\Service\BookingService;
use App\Service\ReviewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/my/bookings', name: 'app_my_bookings', methods: ['GET'])]
    public function mine(ReviewService $reviews): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $bookings = $this->em->getRepository(Booking::class)->findBy(['passenger' => $user], ['id' => 'DESC']);
        $pending = [];
        foreach ($bookings as $b) {
            $pending[$b->getId()] = $reviews->pendingTargets($user, $b->getTrip());
        }

        return $this->render('booking/mine.html.twig', ['bookings' => $bookings, 'pending' => $pending]);
    }

    #[Route('/my/bookings/{id}/cancel', name: 'app_booking_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Booking $booking, Request $request, BookingService $bookings): Response
    {
        if (!$this->isCsrfTokenValid('bcancel'.$booking->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $bookings->cancelByPassenger($booking, $this->getUser());
            $p = $booking->getPayment();
            $this->addFlash('success', sprintf('Réservation annulée — remboursement : %d MAD sur %d MAD.', $p->getRefundedMad(), $p->getAmountMad()));
        } catch (RuleViolation $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_my_bookings');
    }

    #[Route('/my/reviews/{tripId}/{targetId}', name: 'app_review_new', requirements: ['tripId' => '\d+', 'targetId' => '\d+'], methods: ['GET', 'POST'])]
    public function review(int $tripId, int $targetId, Request $request, ReviewService $reviews): Response
    {
        $trip = $this->em->find(Trip::class, $tripId);
        $target = $this->em->find(User::class, $targetId);
        if (!$trip || !$target) {
            throw $this->createNotFoundException();
        }
        if ($request->isMethod('POST')) {
            try {
                if (!$this->isCsrfTokenValid('review', (string) $request->request->get('_token'))) {
                    throw new RuleViolation('Formulaire expiré, réessayez.');
                }
                $reviews->submit($this->getUser(), $trip, $target, $request->request->getInt('rating'), (string) $request->request->get('comment'));
                $this->addFlash('success', 'Merci pour votre avis !');

                return $this->redirectToRoute('app_profile', ['id' => $target->getId()]);
            } catch (RuleViolation $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('review/new.html.twig', ['trip' => $trip, 'target' => $target]);
    }

    #[Route('/users/{id}', name: 'app_profile', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function profile(User $profile, ReviewRepository $reviews): Response
    {
        return $this->render('profile/show.html.twig', [
            'profile' => $profile,
            'asDriver' => $reviews->summary($profile, ReviewRole::DRIVER),
            'asPassenger' => $reviews->summary($profile, ReviewRole::PASSENGER),
            'reviews' => $reviews->latestFor($profile),
        ]);
    }
}
