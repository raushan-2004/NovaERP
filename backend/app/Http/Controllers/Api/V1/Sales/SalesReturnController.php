<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesReturnRequest;
use App\Models\SalesReturn;
use App\Models\Delivery;
use App\Services\Sales\SalesReturnService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class SalesReturnController extends Controller implements HasMiddleware
{
    public function __construct(protected SalesReturnService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:sales_returns.view',    only: ['index', 'show']),
            new Middleware('permission:sales_returns.create',  only: ['store']),
            new Middleware('permission:sales_returns.approve', only: ['approve', 'complete', 'cancel']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = SalesReturn::with('customer', 'salesOrder', 'warehouse', 'returner', 'lines')
            ->whereHas('customer', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'Sales returns retrieved successfully'
        );
    }

    public function store(SalesReturnRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        
        $delivery = Delivery::findOrFail($data['delivery_id']);
        Gate::authorize('view', $delivery);

        $return = $this->service->create(
            $delivery,
            (int) $data['warehouse_id'],
            $data['returned_date'],
            $data['reason'],
            $data['lines'],
            $user
        );

        return ApiResponse::created($return, 'Sales return created successfully');
    }

    public function show(SalesReturn $salesReturn): JsonResponse
    {
        Gate::authorize('view', $salesReturn);
        $salesReturn->load('customer', 'salesOrder', 'warehouse', 'returner', 'lines.product', 'lines.deliveryLine');
        return ApiResponse::success($salesReturn, 'Sales return retrieved successfully');
    }

    public function approve(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        Gate::authorize('approve', $salesReturn);
        $salesReturn = $this->service->transition($salesReturn, 'approve', $request->user());
        return ApiResponse::success($salesReturn, 'Sales return approved successfully');
    }

    public function complete(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        Gate::authorize('approve', $salesReturn);
        $salesReturn = $this->service->transition($salesReturn, 'complete', $request->user());
        return ApiResponse::success($salesReturn, 'Sales return completed successfully');
    }

    public function cancel(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        Gate::authorize('approve', $salesReturn);
        $salesReturn = $this->service->transition($salesReturn, 'cancel', $request->user());
        return ApiResponse::success($salesReturn, 'Sales return cancelled successfully');
    }
}
