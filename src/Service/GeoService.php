<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Nominatim (géocodage) et OSRM (itinéraire). Respect des conditions d'utilisation :
 *  - User-Agent identifiant l'application (variable GEO_USER_AGENT) ;
 *  - résultats mis en cache (Nominatim : 30 jours) pour ne jamais répéter une requête ;
 *  - volume très faible (une requête quand un conducteur prévisualise un trajet) ;
 *  - serveurs de démonstration publics, adaptés à ce challenge, pas à de la production.
 * En cas d'erreur réseau, on retombe sur une estimation à vol d'oiseau (haversine × 1,25).
 */
class GeoService
{
    public const ROAD_FACTOR = 1.25;
    private const NOMINATIM = 'https://nominatim.openstreetmap.org/search';
    private const OSRM = 'https://router.project-osrm.org/route/v1/driving/';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CacheInterface $cache,
        private readonly string $userAgent,
    ) {
    }

    /** @return array{lat: float, lon: float}|null */
    public function geocode(string $city): ?array
    {
        return $this->cache->get('nominatim_'.md5(mb_strtolower($city)), function (ItemInterface $item) use ($city) {
            $item->expiresAfter(30 * 86400);
            try {
                $data = $this->http->request('GET', self::NOMINATIM, [
                    'query' => ['q' => $city, 'countrycodes' => 'ma', 'format' => 'jsonv2', 'limit' => 1],
                    'headers' => ['User-Agent' => $this->userAgent],
                    'timeout' => 5,
                ])->toArray();
            } catch (\Throwable) {
                $item->expiresAfter(60);

                return null;
            }

            return isset($data[0]) ? ['lat' => (float) $data[0]['lat'], 'lon' => (float) $data[0]['lon']] : null;
        });
    }

    /**
     * @param list<array{0: float, 1: float}> $points [lat, lon] dans l'ordre du trajet
     *
     * @return array{legsKm: list<float>, geometry: array<string, mixed>}|null null si OSRM est indisponible
     */
    public function route(array $points): ?array
    {
        if (count($points) < 2) {
            return null;
        }
        $coords = implode(';', array_map(fn (array $p) => sprintf('%.6F,%.6F', $p[1], $p[0]), $points));

        return $this->cache->get('osrm_'.md5($coords), function (ItemInterface $item) use ($coords) {
            $item->expiresAfter(7 * 86400);
            try {
                $data = $this->http->request('GET', self::OSRM.$coords, [
                    'query' => ['overview' => 'full', 'geometries' => 'geojson'],
                    'headers' => ['User-Agent' => $this->userAgent],
                    'timeout' => 8,
                ])->toArray();
            } catch (\Throwable) {
                $item->expiresAfter(30);

                return null;
            }
            $route = $data['routes'][0] ?? null;
            if (null === $route) {
                return null;
            }

            return [
                'legsKm' => array_map(fn (array $l) => round($l['distance'] / 1000, 2), $route['legs']),
                'geometry' => $route['geometry'],
            ];
        });
    }

    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
