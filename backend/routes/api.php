<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Rbac\RoleController;
use App\Http\Controllers\Api\V1\Rbac\PermissionController;
use App\Http\Controllers\Api\V1\Rbac\UserController;
use App\Http\Controllers\Api\V1\Org\CompanyController;
use App\Http\Controllers\Api\V1\Org\BranchController;
use App\Http\Controllers\Api\V1\Org\DepartmentController;
use App\Http\Controllers\Api\V1\Org\EmployeeController;
use App\Http\Controllers\Api\V1\Master\CategoryController;
use App\Http\Controllers\Api\V1\Master\BrandController;
use App\Http\Controllers\Api\V1\Master\UnitController;
use App\Http\Controllers\Api\V1\Master\ProductController;
use App\Http\Controllers\Api\V1\Master\CustomerController;
use App\Http\Controllers\Api\V1\Master\SupplierController;
use App\Http\Controllers\Api\V1\Master\WarehouseController;
// Stage 2 — Inventory
use App\Http\Controllers\Api\V1\Inventory\WarehouseLocationController;
use App\Http\Controllers\Api\V1\Inventory\StockBalanceController;
use App\Http\Controllers\Api\V1\Inventory\StockLedgerController;
use App\Http\Controllers\Api\V1\Inventory\StockTransferController;
use App\Http\Controllers\Api\V1\Inventory\StockAdjustmentController;

// Stage 2 — Purchasing
use App\Http\Controllers\Api\V1\Purchasing\PurchaseRequestController;
use App\Http\Controllers\Api\V1\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Api\V1\Purchasing\GoodsReceiptController;
use App\Http\Controllers\Api\V1\Purchasing\PurchaseReturnController;

// Stage 3 — Sales & CRM
use App\Http\Controllers\Api\V1\Sales\SalesQuotationController;
use App\Http\Controllers\Api\V1\Sales\SalesOrderController;
use App\Http\Controllers\Api\V1\Sales\DeliveryController;
use App\Http\Controllers\Api\V1\Sales\SalesReturnController;
use App\Http\Controllers\Api\V1\Sales\SalesInvoiceController;
use App\Http\Controllers\Api\V1\Sales\CustomerPaymentController;
use App\Http\Controllers\Api\V1\Sales\CustomerActivityController;
use App\Http\Controllers\Api\V1\Sales\SalesDashboardController;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NovaERP API Routes — Version 1
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1 by the framework.
| Follow conventions defined in docs/API_CONTRACT.md.
|
|
*/

Route::prefix('v1')->group(function () {

    // Public endpoints
    Route::get('/health', HealthController::class);

    // Authentication & Core Session management
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    // Protected ERP Resource Endpoints
    Route::middleware('auth:sanctum')->group(function () {
        // RBAC Management
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions', PermissionController::class); // Read-only permissions flat list
        Route::apiResource('users', UserController::class);

        // Organization Management
        Route::apiResource('companies', CompanyController::class);
        Route::apiResource('branches', BranchController::class);
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('employees', EmployeeController::class);

        // Catalog Master Data
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('brands', BrandController::class);
        Route::apiResource('units', UnitController::class);
        Route::apiResource('products', ProductController::class);

        // Partner / Site Master Data
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('warehouses', WarehouseController::class);

        // ---------------------------------------------------------------
        // Stage 2 — Inventory
        // ---------------------------------------------------------------
        Route::apiResource('warehouse-locations', WarehouseLocationController::class);

        Route::get('stock-balances', [StockBalanceController::class, 'index']);
        Route::get('stock-ledger',   [StockLedgerController::class, 'index']);

        Route::apiResource('stock-transfers', StockTransferController::class)
            ->only(['index', 'store', 'show']);
        Route::post('stock-transfers/{stock_transfer}/complete', [StockTransferController::class, 'complete']);
        Route::post('stock-transfers/{stock_transfer}/cancel',   [StockTransferController::class, 'cancel']);

        Route::apiResource('stock-adjustments', StockAdjustmentController::class)
            ->only(['index', 'store', 'show']);

        // ---------------------------------------------------------------
        // Stage 2 — Purchasing
        // ---------------------------------------------------------------
        Route::apiResource('purchase-requests', PurchaseRequestController::class);
        Route::post('purchase-requests/{purchase_request}/submit',        [PurchaseRequestController::class, 'submit']);
        Route::post('purchase-requests/{purchase_request}/approve',       [PurchaseRequestController::class, 'approve']);
        Route::post('purchase-requests/{purchase_request}/reject',        [PurchaseRequestController::class, 'reject']);
        Route::post('purchase-requests/{purchase_request}/convert-to-po', [PurchaseRequestController::class, 'convertToPo']);

        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::post('purchase-orders/{purchase_order}/submit',  [PurchaseOrderController::class, 'submit']);
        Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve']);
        Route::post('purchase-orders/{purchase_order}/send',    [PurchaseOrderController::class, 'send']);
        Route::post('purchase-orders/{purchase_order}/close',   [PurchaseOrderController::class, 'close']);
        Route::post('purchase-orders/{purchase_order}/cancel',  [PurchaseOrderController::class, 'cancel']);

        Route::apiResource('goods-receipts', GoodsReceiptController::class)
            ->only(['index', 'store', 'show']);
        Route::post('goods-receipts/{goods_receipt}/complete', [GoodsReceiptController::class, 'complete']);

        Route::apiResource('purchase-returns', PurchaseReturnController::class)
            ->only(['index', 'store', 'show']);
        Route::post('purchase-returns/{purchase_return}/complete', [PurchaseReturnController::class, 'complete']);

        // ---------------------------------------------------------------
        // Stage 3 — Sales & CRM
        // ---------------------------------------------------------------
        Route::get('sales/dashboard', SalesDashboardController::class);

        Route::apiResource('quotations', SalesQuotationController::class);
        Route::post('quotations/{quotation}/send',           [SalesQuotationController::class, 'send']);
        Route::post('quotations/{quotation}/accept',         [SalesQuotationController::class, 'accept']);
        Route::post('quotations/{quotation}/reject',         [SalesQuotationController::class, 'reject']);
        Route::post('quotations/{quotation}/convert-to-so',  [SalesQuotationController::class, 'convertToSalesOrder']);

        Route::apiResource('sales-orders', SalesOrderController::class);
        Route::post('sales-orders/{sales_order}/submit',     [SalesOrderController::class, 'submit']);
        Route::post('sales-orders/{sales_order}/approve',    [SalesOrderController::class, 'approve']);
        Route::post('sales-orders/{sales_order}/cancel',     [SalesOrderController::class, 'cancel']);

        Route::apiResource('deliveries', DeliveryController::class)->only(['index', 'store', 'show']);
        Route::post('deliveries/{delivery}/complete',        [DeliveryController::class, 'complete']);

        Route::apiResource('sales-returns', SalesReturnController::class)->only(['index', 'store', 'show']);
        Route::post('sales-returns/{sales_return}/approve',  [SalesReturnController::class, 'approve']);
        Route::post('sales-returns/{sales_return}/complete', [SalesReturnController::class, 'complete']);
        Route::post('sales-returns/{sales_return}/cancel',   [SalesReturnController::class, 'cancel']);

        Route::apiResource('sales-invoices', SalesInvoiceController::class)->only(['index', 'store', 'show']);
        Route::post('sales-invoices/{sales_invoice}/issue',  [SalesInvoiceController::class, 'issue']);

        Route::apiResource('customer-payments', CustomerPaymentController::class)->only(['index', 'store', 'show']);

        Route::apiResource('customer-activities', CustomerActivityController::class);
    });

});

