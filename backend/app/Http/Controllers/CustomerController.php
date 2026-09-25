<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\ElasticsearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    private ElasticsearchService $elasticsearch;

    public function __construct(ElasticsearchService $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Display a listing of customers, optionally filtered by a search query.
     * When a ?search= parameter is provided, the query is executed against
     * Elasticsearch (searcher) first, falling back to a MySQL LIKE search
     * if Elasticsearch is unavailable.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->query('search', '');

        if ($query === '' || $query === null) {
            return response()->json(Customer::all());
        }

        $ids = $this->elasticsearch->search($query);

        if (!empty($ids)) {
            $idList      = implode(',', $ids);
            $customers   = Customer::whereIn('id', $ids)
                ->orderByRaw("FIELD(id, {$idList})")
                ->get();

            return response()->json($customers);
        }

        // Fallback: MySQL LIKE search when Elasticsearch is unavailable
        $like      = '%' . $query . '%';
        $customers = Customer::where('first_name', 'LIKE', $like)
            ->orWhere('last_name', 'LIKE', $like)
            ->orWhere('email', 'LIKE', $like)
            ->orWhere('contact_number', 'LIKE', $like)
            ->get();

        return response()->json($customers);
    }

    /**
     * Store a newly created customer in MySQL and sync to Elasticsearch.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'email'          => 'required|email|unique:customers,email',
            'contact_number' => 'required|string|max:255',
        ]);

        $customer = Customer::create($validated);

        $this->elasticsearch->index($customer->id, $customer->toArray());

        return response()->json($customer, 201);
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        return response()->json($customer);
    }

    /**
     * Update the specified customer in MySQL and re-sync to Elasticsearch.
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'email'          => 'required|email|unique:customers,email,' . $customer->id,
            'contact_number' => 'required|string|max:255',
        ]);

        $customer->update($validated);

        $this->elasticsearch->index($customer->id, $customer->fresh()->toArray());

        return response()->json($customer);
    }

    /**
     * Remove the specified customer from MySQL and delete from Elasticsearch.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $id = $customer->id;
        $customer->delete();

        $this->elasticsearch->delete($id);

        return response()->json(null, 204);
    }
}
