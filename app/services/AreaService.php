<?php
/**
 * Area Service
 * N.A Fresh Fruits & Coconuts
 * Handles delivery area polygon boundary parsing, ray-casting point-in-polygon
 * spatial detection, active area lookups, and delivery charge calculations.
 */

class AreaService {
    /**
     * Verified Jaora Delivery Zone Polygon Boundary Coordinates (Jaora, MP - 457226)
     * Vertices enclosing Jaora municipal area and shop location (23.635784, 75.124683)
     */
    public const DEFAULT_JAORA_BOUNDARY = [
        ['lat' => 23.6300, 'lng' => 75.1050],
        ['lat' => 23.6500, 'lng' => 75.1100],
        ['lat' => 23.6580, 'lng' => 75.1250],
        ['lat' => 23.6550, 'lng' => 75.1400],
        ['lat' => 23.6420, 'lng' => 75.1480],
        ['lat' => 23.6220, 'lng' => 75.1450],
        ['lat' => 23.6120, 'lng' => 75.1320],
        ['lat' => 23.6150, 'lng' => 75.1150],
        ['lat' => 23.6300, 'lng' => 75.1050]
    ];

    /**
     * Get Jaora boundary points
     *
     * @return array<array{lat: float, lng: float}>
     */
    public static function getJaoraBoundary(): array {
        return self::DEFAULT_JAORA_BOUNDARY;
    }

    /**
     * Parse stored boundary GeoJSON or JSON points into normalized array of [lat, lng] points
     *
     * @param string|null $rawJson
     * @return array<array{lat: float, lng: float}>|null
     */
    public static function parsePolygonPoints(?string $rawJson): ?array {
        if (empty($rawJson)) {
            return null;
        }

        $decoded = json_decode($rawJson, true);
        if (!is_array($decoded)) {
            return null;
        }

        // 1. Standard GeoJSON FeatureCollection or Feature
        if (isset($decoded['type']) && $decoded['type'] === 'FeatureCollection' && !empty($decoded['features'][0]['geometry'])) {
            $decoded = $decoded['features'][0]['geometry'];
        } else if (isset($decoded['type']) && $decoded['type'] === 'Feature' && !empty($decoded['geometry'])) {
            $decoded = $decoded['geometry'];
        }

        // 2. Standard GeoJSON Polygon: coordinates = [ [ [lng, lat], [lng, lat], ... ] ]
        if (isset($decoded['type']) && strtolower($decoded['type']) === 'polygon' && !empty($decoded['coordinates'][0])) {
            $ring = $decoded['coordinates'][0];
            $points = [];
            foreach ($ring as $pt) {
                if (is_array($pt) && count($pt) >= 2) {
                    // GeoJSON format is [longitude, latitude]
                    $points[] = [
                        'lat' => (float)$pt[1],
                        'lng' => (float)$pt[0]
                    ];
                }
            }
            return count($points) >= 3 ? $points : null;
        }

        // 3. MultiPolygon: use primary outer ring of first polygon
        if (isset($decoded['type']) && strtolower($decoded['type']) === 'multipolygon' && !empty($decoded['coordinates'][0][0])) {
            $ring = $decoded['coordinates'][0][0];
            $points = [];
            foreach ($ring as $pt) {
                if (is_array($pt) && count($pt) >= 2) {
                    $points[] = [
                        'lat' => (float)$pt[1],
                        'lng' => (float)$pt[0]
                    ];
                }
            }
            return count($points) >= 3 ? $points : null;
        }

        // 4. Raw array of objects: [ {"lat": 23.89, "lng": 75.13}, ... ]
        if (isset($decoded[0]['lat']) && isset($decoded[0]['lng'])) {
            $points = [];
            foreach ($decoded as $pt) {
                $points[] = [
                    'lat' => (float)$pt['lat'],
                    'lng' => (float)$pt['lng']
                ];
            }
            return count($points) >= 3 ? $points : null;
        }

        // 5. Raw array of coordinate pairs: [ [23.89, 75.13], ... ]
        if (isset($decoded[0]) && is_array($decoded[0]) && count($decoded[0]) >= 2) {
            $points = [];
            foreach ($decoded as $pt) {
                // Determine whether [lat, lng] or [lng, lat].
                // In India, lat is ~8-37 and lng is ~68-97.
                $v0 = (float)$pt[0];
                $v1 = (float)$pt[1];
                if ($v0 > 60 && $v1 < 40) {
                    // [lng, lat]
                    $points[] = ['lat' => $v1, 'lng' => $v0];
                } else {
                    // [lat, lng]
                    $points[] = ['lat' => $v0, 'lng' => $v1];
                }
            }
            return count($points) >= 3 ? $points : null;
        }

        return null;
    }

    /**
     * Ray-casting Point-In-Polygon Algorithm
     *
     * @param float $lat
     * @param float $lng
     * @param array<array{lat: float, lng: float}> $polygonPoints
     * @return bool
     */
    public static function isPointInPolygon(float $lat, float $lng, array $polygonPoints): bool {
        $count = count($polygonPoints);
        if ($count < 3) {
            return false;
        }

        $inside = false;
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $polygonPoints[$i]['lat'];
            $yi = $polygonPoints[$i]['lng'];
            $xj = $polygonPoints[$j]['lat'];
            $yj = $polygonPoints[$j]['lng'];

            $denom = ($yj - $yi);
            if (abs($denom) < 0.000000001) {
                $denom = ($denom < 0 ? -0.000000001 : 0.000000001);
            }

            $intersect = (($yi > $lng) !== ($yj > $lng))
                && ($lat < ($xj - $xi) * ($lng - $yi) / $denom + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Find active area containing given coordinates
     * Handles potential boundary overlaps by choosing the smallest enclosing boundary
     *
     * @param float $lat
     * @param float $lng
     * @return array|null
     */
    public static function findAreaForCoordinates(float $lat, float $lng): ?array {
        $activeAreas = DeliveryArea::getActiveAreas();
        $matches = [];

        foreach ($activeAreas as $area) {
            if (empty($area['boundary_geojson'])) {
                continue;
            }

            $points = self::parsePolygonPoints($area['boundary_geojson']);
            if (!$points) {
                continue;
            }

            if (self::isPointInPolygon($lat, $lng, $points)) {
                // Calculate bounding box area as a tie-breaker (more specific area wins)
                $lats = array_column($points, 'lat');
                $lngs = array_column($points, 'lng');
                $bboxArea = (max($lats) - min($lats)) * (max($lngs) - min($lngs));
                
                $matches[] = [
                    'area' => $area,
                    'bbox_size' => $bboxArea
                ];
            }
        }

        if (empty($matches)) {
            // Check verified Jaora municipal polygon boundary
            if (self::isPointInPolygon($lat, $lng, self::DEFAULT_JAORA_BOUNDARY)) {
                $charge = (float)Setting::get('delivery_charge', '40.00');
                return [
                    'id' => 1,
                    'name' => 'Jaora City (PIN 457226)',
                    'slug' => 'jaora-city',
                    'delivery_charge' => $charge,
                    'status' => 'active'
                ];
            }
            return null;
        }

        // Sort by smallest bounding box first
        usort($matches, function ($a, $b) {
            return $a['bbox_size'] <=> $b['bbox_size'];
        });

        return $matches[0]['area'];
    }

    /**
     * Compute delivery charge and verify service availability for given location or manual area fallback
     *
     * @param float|null $lat
     * @param float|null $lng
     * @param int|null $areaId
     * @return array
     */
    public static function checkDeliveryEligibility(?float $lat, ?float $lng, ?int $areaId = null): array {
        $defaultCharge = (float)Setting::get('delivery_charge', '40.00');

        // Check if any active areas exist
        $activeAreas = DeliveryArea::getActiveAreas();
        if (empty($activeAreas)) {
            // When no active areas exist or configured yet, fall back to global delivery setting
            return [
                'deliverable' => true,
                'area_id' => null,
                'area_name' => 'General Delivery Service',
                'delivery_charge' => $defaultCharge,
                'message' => 'Delivery is available at standard rate.'
            ];
        }

        // 1. If GPS coordinates provided, perform spatial matching
        if ($lat !== null && $lng !== null && $lat != 0 && $lng != 0) {
            $matchedArea = self::findAreaForCoordinates($lat, $lng);
            if ($matchedArea) {
                return [
                    'deliverable' => true,
                    'area_id' => (int)$matchedArea['id'],
                    'area_name' => $matchedArea['name'],
                    'delivery_charge' => (float)$matchedArea['delivery_charge'],
                    'message' => 'Delivery available in ' . $matchedArea['name'] . '!'
                ];
            } else {
                return [
                    'deliverable' => false,
                    'area_id' => null,
                    'area_name' => null,
                    'delivery_charge' => $defaultCharge,
                    'message' => 'Sorry, delivery is not available. Currently we deliver only within Jaora, Madhya Pradesh - 457226.'
                ];
            }
        }

        // 2. Active area selection by ID
        if ($areaId) {
            $area = DeliveryArea::getById($areaId);
            if ($area && $area['status'] === 'active') {
                return [
                    'deliverable' => true,
                    'area_id' => (int)$area['id'],
                    'area_name' => $area['name'],
                    'delivery_charge' => (float)$area['delivery_charge'],
                    'message' => 'Delivery available in ' . $area['name'] . '!'
                ];
            }
        }

        // 3. Fallback to active Jaora area if inside Jaora
        $jaoraArea = null;
        foreach ($activeAreas as $a) {
            if (!empty($a['boundary_geojson']) || stripos($a['name'], 'Jaora') !== false) {
                $jaoraArea = $a;
                break;
            }
        }
        $selected = $jaoraArea ?: reset($activeAreas);
        $charge = $selected ? (float)$selected['delivery_charge'] : $defaultCharge;
        $areaName = $selected ? $selected['name'] : 'Jaora Delivery Area';
        $selectedId = $selected ? (int)$selected['id'] : null;

        return [
            'deliverable' => true,
            'area_id' => $selectedId,
            'area_name' => $areaName,
            'delivery_charge' => $charge,
            'message' => 'Delivery available in ' . $areaName . '!'
        ];
    }

    /**
     * Strict Jaora-Only Address and Location Verification
     * Validates that home delivery is strictly inside Jaora, Madhya Pradesh (PIN 457226).
     *
     * @param string $address
     * @param float|null $lat
     * @param float|null $lng
     * @return array{valid: bool, error: ?string}
     */
    public static function validateJaoraDelivery(string $address, ?float $lat, ?float $lng): array {
        $restrictedMsg = 'Sorry, currently we deliver only within Jaora, Madhya Pradesh - 457226.';

        // 1. PIN code check in written address: if 6-digit PIN code is present and != 457226, reject
        if (preg_match('/\b([1-9][0-9]{5})\b/', $address, $pinMatches)) {
            $pin = $pinMatches[1];
            if ($pin !== '457226') {
                return ['valid' => false, 'error' => $restrictedMsg];
            }
        }

        // 2. Coordinates spatial point-in-polygon check
        if ($lat !== null && $lng !== null && $lat != 0 && $lng != 0) {
            $matched = self::findAreaForCoordinates($lat, $lng);
            if (!$matched) {
                return ['valid' => false, 'error' => $restrictedMsg];
            }
            return ['valid' => true, 'error' => null, 'area' => $matched];
        }

        // 3. If no coordinates were supplied, verify address mentions Jaora
        if (stripos($address, 'Jaora') === false && stripos($address, '457226') === false) {
            return ['valid' => false, 'error' => $restrictedMsg];
        }

        return ['valid' => true, 'error' => null];
    }
}
