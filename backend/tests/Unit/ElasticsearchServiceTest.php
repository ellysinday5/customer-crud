<?php

namespace Tests\Unit;

use App\Services\ElasticsearchService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ElasticsearchServiceTest extends TestCase
{
    private ElasticsearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ElasticsearchService();
    }

    public function test_index_sends_put_request_to_elasticsearch(): void
    {
        Http::fake([
            'searcher:9200/*' => Http::response(['result' => 'created'], 201),
        ]);

        $this->service->index(1, [
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane@example.com',
            'contact_number' => '09171234567',
        ]);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'customers/_doc/1')
                && $request->method() === 'PUT';
        });
    }

    public function test_delete_sends_delete_request_to_elasticsearch(): void
    {
        Http::fake([
            'searcher:9200/*' => Http::response(['result' => 'deleted'], 200),
        ]);

        $this->service->delete(42);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'customers/_doc/42')
                && $request->method() === 'DELETE';
        });
    }

    public function test_search_returns_ids_from_hits(): void
    {
        Http::fake([
            'searcher:9200/*' => Http::response([
                'hits' => [
                    'total' => ['value' => 2],
                    'hits'  => [
                        ['_id' => '1', '_score' => 1.5, '_source' => []],
                        ['_id' => '7', '_score' => 1.0, '_source' => []],
                    ],
                ],
            ], 200),
        ]);

        $ids = $this->service->search('jane');

        $this->assertEquals([1, 7], $ids);
    }

    public function test_search_returns_empty_array_on_failure(): void
    {
        Http::fake([
            'searcher:9200/*' => Http::response([], 500),
        ]);

        $ids = $this->service->search('test');

        $this->assertEmpty($ids);
    }

    public function test_index_does_not_throw_on_connection_error(): void
    {
        Http::fake(fn() => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));

        // Should not throw; errors are caught and logged
        $this->service->index(1, ['first_name' => 'Test']);

        $this->assertTrue(true);
    }

    public function test_is_available_returns_true_when_health_check_succeeds(): void
    {
        Http::fake([
            'searcher:9200/_cluster/health' => Http::response(['status' => 'green'], 200),
        ]);

        $this->assertTrue($this->service->isAvailable());
    }

    public function test_is_available_returns_false_when_connection_fails(): void
    {
        Http::fake(fn() => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));

        $this->assertFalse($this->service->isAvailable());
    }
}
