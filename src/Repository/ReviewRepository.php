<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\User;
use App\Enum\ReviewRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Review> */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /** @return array{average: ?float, count: int} */
    public function summary(User $target, ReviewRole $role): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) AS avg_rating', 'COUNT(r.id) AS cnt')
            ->where('r.target = :t AND r.role = :role')
            ->setParameter('t', $target)
            ->setParameter('role', $role)
            ->getQuery()->getSingleResult();

        return ['average' => null === $row['avg_rating'] ? null : round((float) $row['avg_rating'], 1), 'count' => (int) $row['cnt']];
    }

    /** @return list<Review> */
    public function latestFor(User $target, int $limit = 20): array
    {
        return $this->findBy(['target' => $target], ['createdAt' => 'DESC'], $limit);
    }
}
