<?php

namespace App\Repository;

use App\Entity\City;
use App\Entity\Trip;
use App\Entity\TripStop;
use App\Enum\TripStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Trip> */
class TripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trip::class);
    }

    /**
     * Trajets publiés passant par $from puis par $to (dans cet ordre), partant $from le jour $day.
     *
     * @return list<array{0: TripStop, 1: TripStop}> paires [arrêt de montée, arrêt de descente]
     */
    public function search(City $from, City $to, \DateTimeImmutable $day, \DateTimeImmutable $now): array
    {
        $dql = 'SELECT a, b FROM '.TripStop::class.' a
                JOIN '.TripStop::class.' b WITH b.trip = a.trip
                JOIN a.trip t
                WHERE t.status = :published
                  AND a.city = :from AND b.city = :to AND a.position < b.position
                  AND a.departureAt >= :start AND a.departureAt < :end AND a.departureAt > :now
                ORDER BY a.departureAt ASC';

        /** @var list<array{0: TripStop, 1: TripStop}> */
        return $this->getEntityManager()->createQuery($dql)
            ->setParameter('published', TripStatus::PUBLISHED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('start', $day->setTime(0, 0))
            ->setParameter('end', $day->setTime(0, 0)->modify('+1 day'))
            ->setParameter('now', $now)
            ->getResult();
    }
}
