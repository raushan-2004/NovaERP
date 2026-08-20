<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\DeliveryRequest;
use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Services\Sales\DeliveryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class DeliveryController extends Controller implements HasMiddleware
{
    public function __construct(protected DeliveryService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:deliveries.view',     only: ['index', 'show']),
            new Middleware('permission:deliveries.create',   only: ['store']),
            new Middleware('permission:deliveries.complete', only: ['complete']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Delivery::with('salesOrder', 'customer', 'warehouse', 'deliverer', 'lines')
            ->whereHas('customer', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'Deliveries retrieved successfully'
        );
    }

    public function store(DeliveryRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        
        $order = SalesOrder::findOrFail($data['sales_order_id']);
        Gate::authorize('view', $order); // Ensures order belongs to user's company

        $delivery = $this->service->create(
            $order,
            (int) $data['warehouse_id'],
            $data['delivery_date'],
            $data['lines'],
            $user
        );

        return ApiResponse::created($delivery, 'Delivery created successfully');
    }

    public function show(Delivery $delivery): JsonResponse
    {
        Gate::authorize('view', $delivery);
        $delivery->load('salesOrder', 'customer', 'warehouse', 'deliverer', 'lines.product');
        return ApiResponse::success($delivery, 'Delivery retrieved successfully');
    }

    public function complete(Request $request, Delivery $delivery): JsonResponse
    {
        Gate::authorize('complete', $delivery);
        $delivery = $this->service->complete($delivery, $request->user());
        return ApiResponse::success($delivery, 'Delivery completed successfully');
    }
}
