<?php

namespace App\Service;

use App\Entity\Trip;
use App\Entity\TripLeg;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use App\Exception\RuleViolation;

/**
 * Sérialise toute opération qui modifie les places d'UN trajet : verrou pessimiste
 * (SELECT … FOR UPDATE) sur la ligne du trajet, puis relecture de l'état réel.
 * Deux réservations simultanées sur le même trajet s'exécutent donc l'une après l'autre,
 * et la seconde voit les places déjà prises par la première.
 */
final class TripLocker
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Transaction qui NE ferme PAS l'EntityManager quand une règle métier est violée
     * (contrairement à EntityManager::wrapInTransaction, qui le ferme à la moindre exception).
     * Toute vérification métier se fait avant la première modification en mémoire ; on peut donc
     * simplement annuler la transaction SQL. Pour toute autre erreur, l'état en mémoire est jeté.
     *
     * @template T
     *
     * @param callable():T $fn
     *
     * @return T
     */
    public function transactional(callable $fn): mixed
    {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            $result = $fn();
            $this->em->flush();
            $conn->commit();

            return $result;
        } catch (\Throwable $e) {
            $conn->rollBack();
            if (!$e instanceof RuleViolation) {
                $this->em->clear();
            }
            throw $e;
        }
    }

    /** À appeler dans une transaction (transactional). */
    public function lock(Trip $trip): void
    {
        $conn = $this->em->getConnection();
        if (!$conn->isTransactionActive()) {
            throw new \LogicException('TripLocker::lock() exige une transaction active.');
        }
        $conn->fetchOne('SELECT id FROM trip WHERE id = ? FOR UPDATE', [$trip->getId()]);
        $this->em->refresh($trip);
    }

    /** @return list<TripLeg> tronçons [fromPos, toPos[ relus depuis la base et verrouillés */
    public function legs(Trip $trip, int $fromPosition, int $toPosition): array
    {
        /** @var list<TripLeg> */
        return $this->em->createQuery(
            'SELECT l FROM '.TripLeg::class.' l WHERE l.trip = :t AND l.position >= :a AND l.position < :b ORDER BY l.position'
        )
            ->setParameter('t', $trip)
            ->setParameter('a', $fromPosition)
            ->setParameter('b', $toPosition)
            ->setHint(Query::HINT_REFRESH, true)
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }
}
