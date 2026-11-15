<?php

namespace App\Service;

use App\Entity\Trip;

/**
 * Prix = distance routière × tarif au km, arrondi au dirham PAR TRONÇON. Le prix d'une portion
 * est la somme des tronçons parcourus : la somme des portions d'un trajet égale toujours
 * le prix du trajet complet (aucun écart d'arrondi à l'arrivée).
 */
final class PricingService
{
    public const DEFAULT_RATE_PER_KM = 0.60;

    public function legPrice(float $km, float $ratePerKm): int
    {
        return max(1, (int) round($km * $ratePerKm));
    }

    public function portionPrice(Trip $trip, int $fromPosition, int $toPosition): int
    {
        $total = 0;
        foreach ($trip->getLegs() as $leg) {
            if ($leg->getPosition() >= $fromPosition && $leg->getPosition() < $toPosition) {
                $total += $leg->getPriceMad();
            }
        }

        return $total;
    }

    /** @param list<float> $legsKm */
    public function suggestedTotal(array $legsKm, float $ratePerKm = self::DEFAULT_RATE_PER_KM): int
    {
        return array_sum(array_map(fn (float $km) => $this->legPrice($km, $ratePerKm), $legsKm));
    }
}
