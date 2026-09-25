<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElasticsearchService
{
    private string $baseUrl;
    private string $index;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.elasticsearch.url', 'http://searcher:9200'), '/');
        $this->index   = config('services.elasticsearch.index', 'customers');
    }

    /**
     * Index or replace a customer document in Elasticsearch.
     */
    public function index(int $id, array $data): void
    {
        try {
            Http::timeout(5)->put("{$this->baseUrl}/{$this->index}/_doc/{$id}", $data);
        } catch (\Throwable $e) {
            Log::warning("Elasticsearch index failed for customer {$id}: " . $e->getMessage());
        }
    }

    /**
     * Delete a customer document from Elasticsearch.
     */
    public function delete(int $id): void
    {
        try {
            Http::timeout(5)->delete("{$this->baseUrl}/{$this->index}/_doc/{$id}");
        } catch (\Throwable $e) {
            Log::warning("Elasticsearch delete failed for customer {$id}: " . $e->getMessage());
        }
    }

    /**
     * Search customers in Elasticsearch by name or email.
     * Returns an array of customer IDs ordered by relevance.
     */
    public function search(string $query): array
    {
        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/{$this->index}/_search", [
                'query' => [
                    'multi_match' => [
                        'query'  => $query,
                        'fields' => ['first_name', 'last_name', 'email', 'contact_number'],
                        'type'   => 'best_fields',
                        'fuzziness' => 'AUTO',
                    ],
                ],
                'size' => 1000,
            ]);

            if ($response->failed()) {
                return [];
            }

            $hits = $response->json('hits.hits', []);

            return array_map(fn($hit) => (int) $hit['_id'], $hits);
        } catch (\Throwable $e) {
            Log::warning('Elasticsearch search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check whether the Elasticsearch service is reachable.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/_cluster/health");
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
