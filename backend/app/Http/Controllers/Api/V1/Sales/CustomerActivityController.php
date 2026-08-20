<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CustomerActivityRequest;
use App\Models\CustomerActivity;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class CustomerActivityController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:crm.view',   only: ['index', 'show']),
            new Middleware('permission:crm.create', only: ['store']),
            new Middleware('permission:crm.update', only: ['update', 'destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = CustomerActivity::with('customer', 'user')
            ->whereHas('customer', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        return ApiResponse::paginated(
            $query->orderByDesc('activity_date')->paginate($request->integer('per_page', 25)),
            'Customer activities retrieved successfully'
        );
    }

    public function store(CustomerActivityRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        
        $customer = Customer::findOrFail($data['customer_id']);
        if ($customer->company_id !== $user->company_id) {
            return response()->json(['message' => 'Unauthorized customer access'], 403);
        }

        $data['user_id'] = $user->id;
        $activity = CustomerActivity::create($data);

        return ApiResponse::created($activity, 'Customer activity created successfully');
    }

    public function show(CustomerActivity $customerActivity): JsonResponse
    {
        Gate::authorize('view', $customerActivity);
        $customerActivity->load('customer', 'user');
        return ApiResponse::success($customerActivity, 'Customer activity retrieved successfully');
    }

    public function update(CustomerActivityRequest $request, CustomerActivity $customerActivity): JsonResponse
    {
        Gate::authorize('update', $customerActivity);
        $customerActivity->update($request->validated());
        return ApiResponse::success($customerActivity, 'Customer activity updated successfully');
    }

    public function destroy(CustomerActivity $customerActivity): JsonResponse
    {
        Gate::authorize('update', $customerActivity);
        $customerActivity->delete();
        return ApiResponse::success(null, 'Customer activity deleted successfully');
    }
}
