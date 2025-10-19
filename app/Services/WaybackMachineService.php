<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WaybackMachineService
{
    protected Client $client;

    protected array $config;

    protected string $baseUrl;

    protected int $requestCount = 0;

    protected int $lastRequestTime = 0;

    public function __construct()
    {
        $this->config = config('verifysource.apis.wayback_machine');
        $this->baseUrl = rtrim($this->config['base_url'], '/');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->config['timeout'],
            'headers' => [
                'User-Agent' => $this->config['user_agent'],
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Check if a URL was available on a specific date
     */
    public function checkUrlAvailability(string $url, Carbon $date): array
    {
        $cacheKey = 'wayback:availability:'.hash('sha256', $url.$date->format('Y-m-d'));

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            // Rate limiting
            $this->enforceRateLimit();

            $timestamp = $date->format('YmdHis');
            $endpoint = $this->config['availability_api'];

            $response = $this->client->get($endpoint, [
                'query' => [
                    'url' => $url,
                    'timestamp' => $timestamp,
                    'callback' => '', // Disable JSONP
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            $result = $this->processAvailabilityResponse($data, $url, $date);

            // Cache for 24 hours
            Cache::put($cacheKey, $result, now()->addHours(24));

            return $result;

        } catch (RequestException $e) {
            Log::warning('Wayback Machine availability check failed', [
                'url' => $url,
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'url' => $url,
                'requested_date' => $date->format('Y-m-d'),
            ];
        }
    }

    /**
     * Search for snapshots of a URL within a date range
     */
    public function searchSnapshots(string $url, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $cacheKey = 'wayback:snapshots:'.hash('sha256', $url.($startDate?->format('Y-m-d') ?? '').($endDate?->format('Y-m-d') ?? ''));

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            // Rate limiting
            $this->enforceRateLimit();

            $params = [
                'url' => $url,
                'output' => 'json',
                'fl' => 'timestamp,original,statuscode,digest',
                'collapse' => 'digest', // Collapse duplicate content
                'limit' => 1000,
            ];

            if ($startDate) {
                $params['from'] = $startDate->format('YmdHis');
            }

            if ($endDate) {
                $params['to'] = $endDate->format('YmdHis');
            }

            $response = $this->client->get($this->config['cdx_api'], [
                'query' => $params,
            ]);

            $content = $response->getBody()->getContents();
            $lines = explode("\n", trim($content));

            $result = $this->processSnapshotResponse($lines, $url);

            // Cache for 6 hours
            Cache::put($cacheKey, $result, now()->addHours(6));

            return $result;

        } catch (RequestException $e) {
            Log::warning('Wayback Machine snapshot search failed', [
                'url' => $url,
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'url' => $url,
                'snapshots' => [],
                'total_snapshots' => 0,
            ];
        }
    }

    /**
     * Find the earliest snapshot of a URL
     */
    public function findEarliestSnapshot(string $url): array
    {
        $cacheKey = 'wayback:earliest:'.hash('sha256', $url);

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            // Rate limiting
            $this->enforceRateLimit();

            $response = $this->client->get($this->config['cdx_api'], [
                'query' => [
                    'url' => $url,
                    'output' => 'json',
                    'fl' => 'timestamp,original,statuscode',
                    'limit' => 1,
                    'sort' => 'timestamp', // Earliest first
                ],
            ]);

            $content = $response->getBody()->getContents();
            $lines = explode("\n", trim($content));

            $result = $this->processEarliestSnapshotResponse($lines, $url);

            // Cache for 24 hours
            Cache::put($cacheKey, $result, now()->addHours(24));

            return $result;

        } catch (RequestException $e) {
            Log::warning('Wayback Machine earliest snapshot search failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'url' => $url,
                'earliest_snapshot' => null,
            ];
        }
    }

    /**
     * Verify timestamp accuracy by checking Internet Archive data
     */
    public function verifyPublicationTimestamp(string $url, Carbon $publishedDate, int $toleranceHours = 24): array
    {
        // Check if URL was available around the claimed publication date
        $startDate = $publishedDate->copy()->subHours($toleranceHours);
        $endDate = $publishedDate->copy()->addHours($toleranceHours);

        $snapshots = $this->searchSnapshots($url, $startDate, $endDate);

        if (! $snapshots['success']) {
            return [
                'verified' => false,
                'confidence' => 0.0,
                'error' => $snapshots['error'],
                'evidence' => 'Could not retrieve Wayback Machine data',
            ];
        }

        $snapshotData = $snapshots['snapshots'];

        if (empty($snapshotData)) {
            // No snapshots found in the tolerance window
            // Check if there are any snapshots before the claimed date
            $earliestSnapshot = $this->findEarliestSnapshot($url);

            if ($earliestSnapshot['success'] && $earliestSnapshot['earliest_snapshot']) {
                $earliestDate = Carbon::createFromFormat('YmdHis', $earliestSnapshot['earliest_snapshot']['timestamp']);

                if ($earliestDate->gt($publishedDate)) {
                    return [
                        'verified' => false,
                        'confidence' => 0.9,
                        'evidence' => "Earliest Internet Archive snapshot is from {$earliestDate->format('Y-m-d H:i:s')}, ".
                                    "which is after the claimed publication date of {$publishedDate->format('Y-m-d H:i:s')}",
                        'earliest_available' => $earliestDate->format('Y-m-d H:i:s'),
                    ];
                }
            }

            return [
                'verified' => false,
                'confidence' => 0.5,
                'evidence' => 'No Internet Archive snapshots found within the specified time range',
            ];
        }

        // Find the snapshot closest to the claimed publication date
        $closestSnapshot = $this->findClosestSnapshot($snapshotData, $publishedDate);
        $snapshotDate = Carbon::createFromFormat('YmdHis', $closestSnapshot['timestamp']);
        $timeDifferenceHours = abs($publishedDate->diffInHours($snapshotDate));

        // Calculate confidence based on how close the snapshot is to the claimed date
        $confidence = $this->calculateTimestampConfidence($timeDifferenceHours, $toleranceHours);

        return [
            'verified' => $confidence > 0.5,
            'confidence' => $confidence,
            'evidence' => "Internet Archive snapshot found on {$snapshotDate->format('Y-m-d H:i:s')}, ".
                        "{$timeDifferenceHours} hours from claimed publication date",
            'closest_snapshot' => [
                'timestamp' => $snapshotDate->format('Y-m-d H:i:s'),
                'url' => $this->buildWaybackUrl($closestSnapshot['timestamp'], $url),
                'time_difference_hours' => $timeDifferenceHours,
            ],
            'total_snapshots_in_range' => count($snapshotData),
        ];
    }

    /**
     * Get comprehensive URL history
     */
    public function getUrlHistory(string $url): array
    {
        $snapshots = $this->searchSnapshots($url);

        if (! $snapshots['success']) {
            return [
                'success' => false,
                'error' => $snapshots['error'],
            ];
        }

        $snapshotData = $snapshots['snapshots'];

        if (empty($snapshotData)) {
            return [
                'success' => true,
                'url' => $url,
                'total_snapshots' => 0,
                'first_seen' => null,
                'last_seen' => null,
                'snapshot_years' => [],
                'summary' => 'No snapshots found in Internet Archive',
            ];
        }

        // Analyze snapshot distribution
        $years = [];
        $firstSnapshot = null;
        $lastSnapshot = null;

        foreach ($snapshotData as $snapshot) {
            $date = Carbon::createFromFormat('YmdHis', $snapshot['timestamp']);
            $year = $date->year;

            if (! isset($years[$year])) {
                $years[$year] = 0;
            }
            $years[$year]++;

            if (! $firstSnapshot || $snapshot['timestamp'] < $firstSnapshot['timestamp']) {
                $firstSnapshot = $snapshot;
            }

            if (! $lastSnapshot || $snapshot['timestamp'] > $lastSnapshot['timestamp']) {
                $lastSnapshot = $snapshot;
            }
        }

        return [
            'success' => true,
            'url' => $url,
            'total_snapshots' => count($snapshotData),
            'first_seen' => $firstSnapshot ? Carbon::createFromFormat('YmdHis', $firstSnapshot['timestamp'])->format('Y-m-d H:i:s') : null,
            'last_seen' => $lastSnapshot ? Carbon::createFromFormat('YmdHis', $lastSnapshot['timestamp'])->format('Y-m-d H:i:s') : null,
            'snapshot_years' => $years,
            'snapshots' => array_slice($snapshotData, 0, 10), // Return first 10 snapshots
            'summary' => $this->generateHistorySummary($snapshotData, $years),
        ];
    }

    /**
     * Process availability API response
     */
    protected function processAvailabilityResponse(array $data, string $url, Carbon $date): array
    {
        if (! isset($data['archived_snapshots']['closest']['available'])) {
            return [
                'success' => true,
                'available' => false,
                'timestamp_verified' => false,
                'confidence' => 0.0,
                'url' => $url,
                'requested_date' => $date->format('Y-m-d H:i:s'),
                'evidence' => 'No archived snapshots found',
            ];
        }

        $closest = $data['archived_snapshots']['closest'];
        $snapshotTimestamp = $closest['timestamp'];
        $snapshotDate = Carbon::createFromFormat('YmdHis', $snapshotTimestamp);

        $timeDifferenceHours = abs($date->diffInHours($snapshotDate));
        $confidence = $this->calculateTimestampConfidence($timeDifferenceHours, 72); // 3-day tolerance

        return [
            'success' => true,
            'available' => $closest['available'],
            'timestamp_verified' => $confidence > 0.5,
            'confidence' => $confidence,
            'url' => $url,
            'requested_date' => $date->format('Y-m-d H:i:s'),
            'closest_snapshot' => [
                'timestamp' => $snapshotDate->format('Y-m-d H:i:s'),
                'url' => $closest['url'],
                'status' => $closest['status'] ?? 'unknown',
                'time_difference_hours' => $timeDifferenceHours,
            ],
            'evidence' => "Closest snapshot found on {$snapshotDate->format('Y-m-d H:i:s')}, ".
                         "{$timeDifferenceHours} hours from requested date",
        ];
    }

    /**
     * Process snapshot search response
     */
    protected function processSnapshotResponse(array $lines, string $url): array
    {
        if (empty($lines) || count($lines) < 1) {
            return [
                'success' => true,
                'url' => $url,
                'snapshots' => [],
                'total_snapshots' => 0,
            ];
        }

        $snapshots = [];

        // Skip the first line if it's a header
        $startIndex = (count($lines) > 1 && strpos($lines[0], 'timestamp') !== false) ? 1 : 0;

        for ($i = $startIndex; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                continue;
            }

            $parts = explode(' ', $line);
            if (count($parts) >= 4) {
                $snapshots[] = [
                    'timestamp' => $parts[0],
                    'original' => $parts[1],
                    'statuscode' => $parts[2],
                    'digest' => $parts[3] ?? null,
                ];
            }
        }

        return [
            'success' => true,
            'url' => $url,
            'snapshots' => $snapshots,
            'total_snapshots' => count($snapshots),
        ];
    }

    /**
     * Process earliest snapshot response
     */
    protected function processEarliestSnapshotResponse(array $lines, string $url): array
    {
        $snapshots = $this->processSnapshotResponse($lines, $url);

        if (! $snapshots['success'] || empty($snapshots['snapshots'])) {
            return [
                'success' => false,
                'url' => $url,
                'earliest_snapshot' => null,
            ];
        }

        return [
            'success' => true,
            'url' => $url,
            'earliest_snapshot' => $snapshots['snapshots'][0],
        ];
    }

    /**
     * Find the snapshot closest to a given date
     */
    protected function findClosestSnapshot(array $snapshots, Carbon $targetDate): array
    {
        $closestSnapshot = null;
        $smallestDifference = null;

        foreach ($snapshots as $snapshot) {
            $snapshotDate = Carbon::createFromFormat('YmdHis', $snapshot['timestamp']);
            $difference = abs($targetDate->diffInSeconds($snapshotDate));

            if ($smallestDifference === null || $difference < $smallestDifference) {
                $smallestDifference = $difference;
                $closestSnapshot = $snapshot;
            }
        }

        return $closestSnapshot;
    }

    /**
     * Calculate confidence score based on time difference
     */
    protected function calculateTimestampConfidence(int $timeDifferenceHours, int $toleranceHours): float
    {
        if ($timeDifferenceHours <= 1) {
            return 1.0; // Perfect match
        } elseif ($timeDifferenceHours <= 6) {
            return 0.9; // Very good match
        } elseif ($timeDifferenceHours <= 24) {
            return 0.8; // Good match
        } elseif ($timeDifferenceHours <= $toleranceHours) {
            return 0.6; // Acceptable match
        } else {
            return max(0.1, 1 - ($timeDifferenceHours / ($toleranceHours * 2))); // Poor match
        }
    }

    /**
     * Build Wayback Machine URL for a specific snapshot
     */
    protected function buildWaybackUrl(string $timestamp, string $originalUrl): string
    {
        return "{$this->baseUrl}/web/{$timestamp}/{$originalUrl}";
    }

    /**
     * Generate history summary
     */
    protected function generateHistorySummary(array $snapshots, array $years): string
    {
        $totalSnapshots = count($snapshots);
        $yearCount = count($years);
        $yearRange = $yearCount > 0 ? min(array_keys($years)).'-'.max(array_keys($years)) : 'unknown';

        return "Found {$totalSnapshots} snapshots across {$yearCount} years ({$yearRange})";
    }

    /**
     * Enforce rate limiting
     */
    protected function enforceRateLimit(): void
    {
        $now = time();

        // Reset counter every minute
        if ($now - $this->lastRequestTime >= 60) {
            $this->requestCount = 0;
            $this->lastRequestTime = $now;
        }

        // Check if we've exceeded the rate limit
        if ($this->requestCount >= $this->config['rate_limit']) {
            $sleepTime = 60 - ($now - $this->lastRequestTime);
            if ($sleepTime > 0) {
                sleep($sleepTime + 1);
                $this->requestCount = 0;
                $this->lastRequestTime = time();
            }
        }

        $this->requestCount++;
    }

    /**
     * Check if Wayback Machine service is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->client->get('/', ['timeout' => 5]);

            return $response->getStatusCode() === 200;
        } catch (Exception $e) {
            return false;
        }
    }
}
