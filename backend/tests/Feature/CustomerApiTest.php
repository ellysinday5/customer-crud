<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\ElasticsearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Elasticsearch so tests don't depend on a live searcher container
        $esMock = Mockery::mock(ElasticsearchService::class);
        $esMock->shouldReceive('index')->andReturn(null);
        $esMock->shouldReceive('delete')->andReturn(null);
        $esMock->shouldReceive('search')->andReturn([]);
        $esMock->shouldReceive('isAvailable')->andReturn(false);

        $this->app->instance(ElasticsearchService::class, $esMock);
    }

    public function test_can_list_all_customers(): void
    {
        Customer::factory()->count(3)->create();

        $response = $this->getJson('/api/customers');

        $response->assertOk()->assertJsonCount(3);
    }

    public function test_can_create_a_customer(): void
    {
        $payload = [
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane.doe@example.com',
            'contact_number' => '09171234567',
        ];

        $response = $this->postJson('/api/customers', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['email' => 'jane.doe@example.com']);

        $this->assertDatabaseHas('customers', ['email' => 'jane.doe@example.com']);
    }

    public function test_create_requires_all_fields(): void
    {
        $response = $this->postJson('/api/customers', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'contact_number']);
    }

    public function test_create_requires_unique_email(): void
    {
        Customer::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->postJson('/api/customers', [
            'first_name'     => 'John',
            'last_name'      => 'Smith',
            'email'          => 'duplicate@example.com',
            'contact_number' => '09179999999',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_can_view_a_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->getJson("/api/customers/{$customer->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $customer->id]);
    }

    public function test_can_update_a_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->putJson("/api/customers/{$customer->id}", [
            'first_name'     => 'Updated',
            'last_name'      => 'Name',
            'email'          => 'updated@example.com',
            'contact_number' => '09170000001',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['first_name' => 'Updated']);

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'first_name' => 'Updated']);
    }

    public function test_update_allows_same_email_for_same_customer(): void
    {
        $customer = Customer::factory()->create(['email' => 'same@example.com']);

        $response = $this->putJson("/api/customers/{$customer->id}", [
            'first_name'     => $customer->first_name,
            'last_name'      => $customer->last_name,
            'email'          => 'same@example.com',
            'contact_number' => $customer->contact_number,
        ]);

        $response->assertOk();
    }

    public function test_can_delete_a_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->deleteJson("/api/customers/{$customer->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_search_falls_back_to_mysql_when_elasticsearch_unavailable(): void
    {
        Customer::factory()->create(['first_name' => 'Alice', 'email' => 'alice@example.com']);
        Customer::factory()->create(['first_name' => 'Bob', 'email' => 'bob@example.com']);

        $response = $this->getJson('/api/customers?search=alice');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['first_name' => 'Alice']);
    }

    public function test_search_returns_all_when_query_is_empty(): void
    {
        Customer::factory()->count(5)->create();

        $response = $this->getJson('/api/customers?search=');

        $response->assertOk()->assertJsonCount(5);
    }
}
