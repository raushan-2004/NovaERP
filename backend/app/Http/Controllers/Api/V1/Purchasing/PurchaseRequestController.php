<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\ConvertToPrRequest;
use App\Http\Requests\Purchasing\PurchaseRequestRequest;
use App\Models\PurchaseRequest;
use App\Services\Purchasing\PurchaseRequestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class PurchaseRequestController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PurchaseRequestService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:purchase_requests.view',    only: ['index', 'show']),
            new Middleware('permission:purchase_requests.create',  only: ['store']),
            new Middleware('permission:purchase_requests.update',  only: ['update', 'submit']),
            new Middleware('permission:purchase_requests.delete',  only: ['destroy']),
            new Middleware('permission:purchase_requests.approve', only: ['approve', 'reject', 'convertToPo']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = PurchaseRequest::with('company', 'branch', 'requestedBy', 'lines');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'Purchase requests retrieved successfully'
        );
    }

    public function store(PurchaseRequestRequest $request): JsonResponse
    {
        $pr = $this->service->create($request->validated(), $request->user());
        return ApiResponse::created($pr, 'Purchase request created successfully');
    }

    public function show(PurchaseRequest $purchaseRequest): JsonResponse
    {
        Gate::authorize('view', $purchaseRequest);
        $purchaseRequest->load('company', 'branch', 'requestedBy', 'lines.product', 'lines.unit');
        return ApiResponse::success($purchaseRequest, 'Purchase request retrieved successfully');
    }

    public function update(PurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        Gate::authorize('update', $purchaseRequest);

        $purchaseRequest->update([
            'required_date' => $request->validated()['required_date'] ?? $purchaseRequest->required_date,
            'notes'         => $request->validated()['notes'] ?? $purchaseRequest->notes,
        ]);

        return ApiResponse::success($purchaseRequest->fresh(), 'Purchase request updated successfully');
    }

    public function destroy(PurchaseRequest $purchaseRequest): JsonResponse
    {
        Gate::authorize('delete', $purchaseRequest);
        $purchaseRequest->delete();
        return ApiResponse::success(null, 'Purchase request deleted successfully');
    }

    public function submit(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        Gate::authorize('update', $purchaseRequest);
        $pr = $this->service->submit($purchaseRequest, $request->user());
        return ApiResponse::success($pr, 'Purchase request submitted successfully');
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        Gate::authorize('approve', $purchaseRequest);
        $pr = $this->service->approve($purchaseRequest, $request->user());
        return ApiResponse::success($pr, 'Purchase request approved successfully');
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        Gate::authorize('approve', $purchaseRequest);
        $pr = $this->service->reject($purchaseRequest, $request->user());
        return ApiResponse::success($pr, 'Purchase request rejected successfully');
    }

    public function convertToPo(ConvertToPrRequest $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        Gate::authorize('approve', $purchaseRequest);
        $po = $this->service->convertToPo($purchaseRequest, $request->validated(), $request->user());
        return ApiResponse::created($po, 'Purchase order created from purchase request successfully');
    }
}

