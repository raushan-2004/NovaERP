<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\SalesQuotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\CustomerPayment;
use App\Models\CustomerActivity;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Stage3VerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $manager;
    private User $limitedUser;
    private Company $company;
    private Company $otherCompany;
    private Branch $branch;
    private Warehouse $warehouseA;
    private Warehouse $warehouseB;
    private Product $product;
    private Customer $customer;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->superAdmin   = User::where('email', 'admin@novatech.com')->firstOrFail();
        $this->manager      = User::where('email', 'manager@novatech.com')->firstOrFail();
        $this->limitedUser  = User::where('email', 'worker@novatech.com')->firstOrFail();
        
        $this->company      = Company::first();
        $this->otherCompany = Company::create(['name' => 'Other Company', 'status' => 'active']);
        
        $this->branch       = Branch::first();
        $this->warehouseA   = Warehouse::first();
        $this->warehouseB   = Warehouse::skip(1)->firstOrFail();
        $this->product      = Product::where('track_inventory', true)->first();
        $this->customer     = Customer::first();
        $this->unit         = Unit::first();

        // Seed 100 units of product in warehouseA for sales fulfillment
        app(InventoryService::class)->receive(
            $this->product,
            $this->warehouseA,
            '100.0000',
            'test_setup',
            1,
            'Setup stock',
            $this->superAdmin
        );
    }

    // 1. Quotation Lifecycle (Draft -> Sent -> Accepted)
    public function test_quotation_lifecycle(): void
    {
        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/quotations', [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'company_id' => $this->company->id,
            'quotation_date' => '2026-08-20',
            'valid_until' => '2026-08-30',
            'notes' => 'Test quotation',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_id' => $this->unit->id,
                    'unit_price' => 150,
                    'discount' => 10,
                    'tax_rate' => 0.18,
                ]
            ]
        ]);

        $res->assertCreated();
        $id = $res->json('data.id');

        // Sent
        $this->actingAs($this->superAdmin)->postJson("/api/v1/quotations/{$id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        // Accepted
        $this->actingAs($this->superAdmin)->postJson("/api/v1/quotations/{$id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    // 2. Quotation Invalid Transitions
    public function test_quotation_invalid_transitions(): void
    {
        $quotation = SalesQuotation::create([
            'quotation_number' => 'QT-TEST-001',
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->superAdmin->id,
            'quotation_date' => '2026-08-20',
            'status' => 'draft',
            'total' => 1000,
        ]);

        // Try accepting directly from Draft (invalid, must be Sent first)
        $res = $this->actingAs($this->superAdmin)->postJson("/api/v1/quotations/{$quotation->id}/accept");
        $res->assertStatus(422);
    }

    // 3. Quotation Conversion
    public function test_quotation_conversion(): void
    {
        $quotation = SalesQuotation::create([
            'quotation_number' => 'QT-TEST-002',
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->superAdmin->id,
            'quotation_date' => '2026-08-20',
            'status' => 'accepted',
            'total' => 1500,
        ]);

        $quotation->lines()->create([
            'product_id' => $this->product->id,
            'quantity' => '10.0000',
            'unit_id' => $this->unit->id,
            'unit_price' => '150.0000',
            'discount' => '0.0000',
            'tax_rate' => '0.0000',
            'tax_amount' => '0.0000',
            'line_total' => '1500.0000',
        ]);

        $res = $this->actingAs($this->superAdmin)->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", [
            'expected_delivery_date' => '2026-08-25',
        ]);

        $res->assertCreated();
        $this->assertDatabaseHas('sales_orders', [
            'sales_quotation_id' => $quotation->id,
            'status' => 'draft',
        ]);
    }

    // 4. Duplicate Quotation Conversion Prevention
    public function test_duplicate_quotation_conversion_prevention(): void
    {
        $quotation = SalesQuotation::create([
            'quotation_number' => 'QT-TEST-003',
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->superAdmin->id,
            'quotation_date' => '2026-08-20',
            'status' => 'accepted',
            'total' => 1000,
        ]);

        // Convert 1st time
        $this->actingAs($this->superAdmin)->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so")
            ->assertCreated();

        // Convert 2nd time (should fail)
        $this->actingAs($this->superAdmin)->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so")
            ->assertStatus(422);
    }

    // 5. Sales Order Lifecycle (Draft -> Submitted -> Approved)
    public function test_sales_order_lifecycle(): void
    {
        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/sales-orders', [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'company_id' => $this->company->id,
            'order_date' => '2026-08-20',
            'notes' => 'Direct Sales Order',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 15,
                    'unit_id' => $this->unit->id,
                    'unit_price' => 200,
                    'discount' => 0,
                    'tax_rate' => 0.18,
                ]
            ]
        ]);

        $res->assertCreated();
        $id = $res->json('data.id');

        // Submit
        $this->actingAs($this->superAdmin)->postJson("/api/v1/sales-orders/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        // Approve
        $this->actingAs($this->superAdmin)->postJson("/api/v1/sales-orders/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    // 6. Sales Order Scope Validation
    public function test_sales_order_scope(): void
    {
        $otherCustomer = Customer::create([
            'company_id' => $this->otherCompany->id,
            'customer_code' => 'CUST-OTHER',
            'name' => 'Other Customer',
            'status' => 'active',
        ]);

        // Trying to create a Sales Order for other company's customer
        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/sales-orders', [
            'customer_id' => $otherCustomer->id,
            'branch_id' => $this->branch->id,
            'company_id' => $this->company->id,
            'order_date' => '2026-08-20',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'unit_id' => $this->unit->id,
                    'unit_price' => 100,
                ]
            ]
        ]);

        // Wait! We can either throw 422/403. Let's make sure it checks customer.company_id == company_id!
        // We will make sure our controller/request or policies rejects it.
        // Actually, we can check that it throws or rejects successfully.
        $res->assertStatus(403);
    }

    // 7. Delivery Creation (Draft)
    public function test_delivery_creation(): void
    {
        $order = $this->createApprovedSalesOrder(20);

        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/deliveries', [
            'sales_order_id' => $order->id,
            'warehouse_id' => $this->warehouseA->id,
            'delivery_date' => '2026-08-20',
            'lines' => [
                [
                    'sales_order_line_id' => $order->lines->first()->id,
                    'quantity' => 10,
                ]
            ]
        ]);

        $res->assertCreated()->assertJsonPath('data.status', 'draft');
    }

    // 8. Partial Delivery
    public function test_partial_delivery(): void
    {
        $order = $this->createApprovedSalesOrder(20);

        // Deliver 10 units first
        $del = $this->createAndCompleteDelivery($order, 10);

        $this->assertEquals('10.0000', $order->lines->first()->fresh()->delivered_quantity);
        $this->assertEquals('partially_delivered', $order->fresh()->status);
    }

    // 9. Full Delivery
    public function test_full_delivery(): void
    {
        $order = $this->createApprovedSalesOrder(20);

        // Deliver all 20 units
        $del = $this->createAndCompleteDelivery($order, 20);

        $this->assertEquals('20.0000', $order->lines->first()->fresh()->delivered_quantity);
        $this->assertEquals('fully_delivered', $order->fresh()->status);
    }

    // 10. Over-Delivery Rejection
    public function test_over_delivery_rejection(): void
    {
        $order = $this->createApprovedSalesOrder(20);

        // Try to deliver 25 units
        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/deliveries', [
            'sales_order_id' => $order->id,
            'warehouse_id' => $this->warehouseA->id,
            'delivery_date' => '2026-08-20',
            'lines' => [
                [
                    'sales_order_line_id' => $order->lines->first()->id,
                    'quantity' => 25,
                ]
            ]
        ]);

        $res->assertStatus(422);
    }

    // 11. Delivery Decreases Stock
    public function test_delivery_decreases_stock(): void
    {
        $order = $this->createApprovedSalesOrder(15);
        
        $balanceBefore = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('quantity');

        $this->createAndCompleteDelivery($order, 15);

        $balanceAfter = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('quantity');

        $this->assertEquals('15.0000', bcsub((string) $balanceBefore, (string) $balanceAfter, 4));
    }

    // 12. Delivery Creates Sale Ledger Entry
    public function test_delivery_creates_sale_ledger_entry(): void
    {
        $order = $this->createApprovedSalesOrder(5);
        $del = $this->createAndCompleteDelivery($order, 5);

        $this->assertDatabaseHas('stock_ledger_entries', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseA->id,
            'movement_type' => 'sale',
            'reference_type' => Delivery::class,
            'reference_id' => $del->id,
            'quantity' => '5.0000',
        ]);
    }

    // 13. Delivery Atomic Rollback
    public function test_delivery_atomic_rollback(): void
    {
        $order = $this->createApprovedSalesOrder(50); // requires 50 units

        // Currently we only have 100 units total. Let's make a delivery that requests 150 units.
        // It will fail because of InsufficientStockException on guardNegativeStock!
        $delivery = Delivery::create([
            'delivery_number' => 'DEL-ROLLBACK',
            'sales_order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'warehouse_id' => $this->warehouseA->id,
            'delivered_by' => $this->superAdmin->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $delivery->lines()->create([
            'sales_order_line_id' => $order->lines->first()->id,
            'product_id' => $this->product->id,
            'ordered_quantity' => 50,
            'delivered_quantity' => 150, // More than the 100 available stock!
        ]);

        // Attempting to complete delivery should fail and rollback
        try {
            app(\App\Services\Sales\DeliveryService::class)->complete($delivery, $this->superAdmin);
            $this->fail("Expected InsufficientStockException was not thrown.");
        } catch (InsufficientStockException $e) {
            // Expected
        }

        // Verify no ledger entries were created for this delivery
        $this->assertDatabaseMissing('stock_ledger_entries', [
            'reference_type' => Delivery::class,
            'reference_id' => $delivery->id,
        ]);

        // Verify status remains draft
        $this->assertEquals('draft', $delivery->fresh()->status);
    }

    // 14. Sales Return Lifecycle (Draft -> Approved -> Completed)
    public function test_sales_return_lifecycle(): void
    {
        $order = $this->createApprovedSalesOrder(10);
        $delivery = $this->createAndCompleteDelivery($order, 10);

        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/sales-returns', [
            'delivery_id' => $delivery->id,
            'warehouse_id' => $this->warehouseA->id,
            'returned_date' => '2026-08-20',
            'reason' => 'Defective parts',
            'lines' => [
                [
                    'delivery_line_id' => $delivery->lines->first()->id,
                    'quantity' => 2,
                ]
            ]
        ]);

        $res->assertCreated();
        $id = $res->json('data.id');

        // Approve
        $this->actingAs($this->superAdmin)->postJson("/api/v1/sales-returns/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        // Complete
        $this->actingAs($this->superAdmin)->postJson("/api/v1/sales-returns/{$id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    // 15. Over-Return Rejection
    public function test_over_return_rejection(): void
    {
        $order = $this->createApprovedSalesOrder(10);
        $delivery = $this->createAndCompleteDelivery($order, 10);

        // Try returning 12 units (exceeds 10 delivered)
        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/sales-returns', [
            'delivery_id' => $delivery->id,
            'warehouse_id' => $this->warehouseA->id,
            'returned_date' => '2026-08-20',
            'reason' => 'Defective parts',
            'lines' => [
                [
                    'delivery_line_id' => $delivery->lines->first()->id,
                    'quantity' => 12,
                ]
            ]
        ]);

        $res->assertStatus(422);
    }

    // 16. Sales Return Increases Stock
    public function test_sales_return_increases_stock(): void
    {
        $order = $this->createApprovedSalesOrder(10);
        $delivery = $this->createAndCompleteDelivery($order, 10);

        $balanceBefore = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('quantity');

        // Create return for 5 units
        $salesReturn = SalesReturn::create([
            'return_number' => 'SR-TEST-001',
            'customer_id' => $this->customer->id,
            'sales_order_id' => $order->id,
            'warehouse_id' => $this->warehouseA->id,
            'returned_by' => $this->superAdmin->id,
            'returned_date' => now()->toDateString(),
            'reason' => 'Customer return',
            'status' => 'approved',
        ]);

        $salesReturn->lines()->create([
            'product_id' => $this->product->id,
            'quantity' => '5.0000',
            'delivery_line_id' => $delivery->lines->first()->id,
        ]);

        // Complete return
        app(\App\Services\Sales\SalesReturnService::class)->transition($salesReturn, 'complete', $this->superAdmin);

        $balanceAfter = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('quantity');

        $this->assertEquals('5.0000', bcsub((string) $balanceAfter, (string) $balanceBefore, 4));
    }

    // 17. Sales Return Creates sale_return Ledger Entry
    public function test_sales_return_creates_sale_return_ledger_entry(): void
    {
        $order = $this->createApprovedSalesOrder(10);
        $delivery = $this->createAndCompleteDelivery($order, 10);

        $salesReturn = SalesReturn::create([
            'return_number' => 'SR-TEST-002',
            'customer_id' => $this->customer->id,
            'sales_order_id' => $order->id,
            'warehouse_id' => $this->warehouseA->id,
            'returned_by' => $this->superAdmin->id,
            'returned_date' => now()->toDateString(),
            'reason' => 'Customer return',
            'status' => 'approved',
        ]);

        $salesReturn->lines()->create([
            'product_id' => $this->product->id,
            'quantity' => '3.0000',
            'delivery_line_id' => $delivery->lines->first()->id,
        ]);

        app(\App\Services\Sales\SalesReturnService::class)->transition($salesReturn, 'complete', $this->superAdmin);

        $this->assertDatabaseHas('stock_ledger_entries', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseA->id,
            'movement_type' => 'sale_return',
            'reference_type' => SalesReturn::class,
            'reference_id' => $salesReturn->id,
            'quantity' => '3.0000',
        ]);
    }

    // 18. Invoice Cannot Exceed Delivered Quantity
    public function test_invoice_cannot_exceed_delivered_quantity(): void
    {
        $order = $this->createApprovedSalesOrder(20);
        $delivery = $this->createAndCompleteDelivery($order, 10); // Delivered = 10

        // Attempting to invoice 15 (should fail)
        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/sales-invoices', [
            'sales_order_id' => $order->id,
            'invoice_date' => '2026-08-20',
            'due_date' => '2026-09-20',
            'lines' => [
                [
                    'sales_order_line_id' => $order->lines->first()->id,
                    'quantity' => 15,
                    'delivery_line_id' => $delivery->lines->first()->id,
                ]
            ]
        ]);

        $res->assertStatus(422);
    }

    // 19. Partial Delivery + Partial Invoice
    public function test_partial_delivery_partial_invoice(): void
    {
        $order = $this->createApprovedSalesOrder(20);
        $delivery = $this->createAndCompleteDelivery($order, 12); // Delivered = 12

        // Invoice 10 of those 12
        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/sales-invoices', [
            'sales_order_id' => $order->id,
            'invoice_date' => '2026-08-20',
            'due_date' => '2026-09-20',
            'lines' => [
                [
                    'sales_order_line_id' => $order->lines->first()->id,
                    'quantity' => 10,
                    'delivery_line_id' => $delivery->lines->first()->id,
                ]
            ]
        ]);

        $res->assertCreated();
        $invoiceId = $res->json('data.id');

        // Issue invoice
        $this->actingAs($this->superAdmin)->postJson("/api/v1/sales-invoices/{$invoiceId}/issue")
            ->assertOk();

        $this->assertEquals('10.0000', $order->lines->first()->fresh()->invoiced_quantity);
    }

    // 20. Second Delivery + Remaining Invoice
    public function test_second_delivery_remaining_invoice(): void
    {
        $order = $this->createApprovedSalesOrder(20);

        // 1st delivery of 10 & full invoice of 10
        $del1 = $this->createAndCompleteDelivery($order, 10);
        $inv1 = $this->createAndIssueInvoice($order, 10, $del1->lines->first()->id);

        // 2nd delivery of 10
        $del2 = $this->createAndCompleteDelivery($order, 10);

        // Invoice remaining 10
        $inv2 = $this->createAndIssueInvoice($order, 10, $del2->lines->first()->id);

        $this->assertEquals('20.0000', $order->lines->first()->fresh()->invoiced_quantity);
    }

    // 21. Invoice Immutability
    public function test_invoice_immutability(): void
    {
        $order = $this->createApprovedSalesOrder(10);
        $del = $this->createAndCompleteDelivery($order, 10);
        $inv = $this->createAndIssueInvoice($order, 10, $del->lines->first()->id);

        // Verify status is issued
        $this->assertEquals('issued', $inv->status);

        // 1. Edit (PUT) is not routable -> 405 Method Not Allowed
        $res = $this->actingAs($this->superAdmin)->putJson("/api/v1/sales-invoices/{$inv->id}", [
            'notes' => 'trying to change',
        ]);
        $res->assertStatus(405);

        // 2. Issuing it again is rejected -> 422
        $this->actingAs($this->superAdmin)->postJson("/api/v1/sales-invoices/{$inv->id}/issue")
            ->assertStatus(422);
    }

    // 22. Payment Partial
    public function test_payment_partial(): void
    {
        $order = $this->createApprovedSalesOrder(10);
        $del = $this->createAndCompleteDelivery($order, 10);
        $inv = $this->createAndIssueInvoice($order, 10, $del->lines->first()->id);

        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/customer-payments', [
            'sales_invoice_id' => $inv->id,
            'amount' => 500, // half payment
            'payment_method' => 'cash',
        ]);

        $res->assertCreated();
        $this->assertEquals('partially_paid', $inv->fresh()->status);
        $this->assertEquals('500.0000', $inv->fresh()->amount_paid);
    }

    // 23. Payment Full
    public function test_payment_full(): void
    {
        $order = $this->createApprovedSalesOrder(10);
        $del = $this->createAndCompleteDelivery($order, 10);
        $inv = $this->createAndIssueInvoice($order, 10, $del->lines->first()->id);

        $total = $inv->total;

        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/customer-payments', [
            'sales_invoice_id' => $inv->id,
            'amount' => $total, // full payment
            'payment_method' => 'bank_transfer',
        ]);

        $res->assertCreated();
        $this->assertEquals('paid', $inv->fresh()->status);
        $this->assertEquals('0.0000', $inv->fresh()->amount_due);
    }

    // 24. Overpayment Rejection
    public function test_overpayment_rejection(): void
    {
        $order = $this->createApprovedSalesOrder(10);
        $del = $this->createAndCompleteDelivery($order, 10);
        $inv = $this->createAndIssueInvoice($order, 10, $del->lines->first()->id);

        $total = $inv->total;

        // Try to pay more than total
        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/customer-payments', [
            'sales_invoice_id' => $inv->id,
            'amount' => bcadd((string) $total, '10.0000', 4),
            'payment_method' => 'card',
        ]);

        $res->assertStatus(422);
    }

    // 25. Payment Atomicity
    public function test_payment_atomicity(): void
    {
        $order = $this->createApprovedSalesOrder(10);
        $del = $this->createAndCompleteDelivery($order, 10);
        $inv = $this->createAndIssueInvoice($order, 10, $del->lines->first()->id);

        // Try a negative payment
        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/customer-payments', [
            'sales_invoice_id' => $inv->id,
            'amount' => -100,
            'payment_method' => 'card',
        ]);

        $res->assertStatus(422);

        // Verify invoice totals were not mutated
        $this->assertEquals('0.0000', $inv->fresh()->amount_paid);
        $this->assertEquals('issued', $inv->fresh()->status);
    }

    // 26. Company Isolation
    public function test_company_isolation(): void
    {
        $order = $this->createApprovedSalesOrder(10);

        // Create a user in the other company
        $otherUser = User::create([
            'name' => 'Other Admin',
            'email' => 'other@novatech.com',
            'password' => bcrypt('password'),
            'company_id' => $this->otherCompany->id,
            'status' => 'active',
        ]);
        $otherUser->roles()->attach(\App\Models\Role::where('name', 'Admin')->firstOrFail());

        // Other user attempts to view the Sales Order
        $res = $this->actingAs($otherUser)->getJson("/api/v1/sales-orders/{$order->id}");
        $res->assertStatus(403);
    }

    // 27. Branch/Warehouse Isolation
    public function test_branch_warehouse_isolation(): void
    {
        $order = $this->createApprovedSalesOrder(10);

        // Create branch belonging to other company
        $otherBranch = Branch::create([
            'company_id' => $this->otherCompany->id,
            'branch_code' => 'OTH01',
            'name' => 'Other Branch',
            'address' => 'Other Address',
            'status' => 'active',
        ]);

        // Create warehouse belonging to other branch
        $otherWarehouse = Warehouse::create([
            'branch_id' => $otherBranch->id,
            'warehouse_code' => 'WH-OTHER',
            'name' => 'Other Warehouse',
            'status' => 'active',
        ]);

        // Attempting to deliver from this other warehouse should fail validation
        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/deliveries', [
            'sales_order_id' => $order->id,
            'warehouse_id' => $otherWarehouse->id,
            'delivery_date' => '2026-08-20',
            'lines' => [
                [
                    'sales_order_line_id' => $order->lines->first()->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $res->assertStatus(422);
    }

    // 28. Permission Enforcement
    public function test_permission_enforcement(): void
    {
        // Limited user has no quotations.create permission
        $this->actingAs($this->limitedUser)->postJson('/api/v1/quotations', [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'company_id' => $this->company->id,
            'quotation_date' => '2026-08-20',
            'lines' => []
        ])->assertStatus(403);
    }

    // 29. Invalid Status -> 422
    public function test_invalid_status_throws_422(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'SO-INVALID-STATUS',
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->superAdmin->id,
            'order_date' => '2026-08-20',
            'status' => 'draft',
            'total' => 1000,
        ]);

        // Try approving direct from Draft (invalid, must be submitted first)
        $res = $this->actingAs($this->superAdmin)->postJson("/api/v1/sales-orders/{$order->id}/approve");
        $res->assertStatus(422);
    }

    // 30. Complete End-to-End OTC Flow
    public function test_complete_end_to_end_otc_flow(): void
    {
        // 1. Create and accept quotation
        $quotation = SalesQuotation::create([
            'quotation_number' => 'QT-E2E-001',
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->superAdmin->id,
            'quotation_date' => '2026-08-20',
            'status' => 'accepted',
            'total' => 3000,
            'subtotal' => 3000,
        ]);

        $quotation->lines()->create([
            'product_id' => $this->product->id,
            'quantity' => '15.0000',
            'unit_id' => $this->unit->id,
            'unit_price' => '200.0000',
            'discount' => '0.0000',
            'tax_rate' => '0.0000',
            'tax_amount' => '0.0000',
            'line_total' => '3000.0000',
        ]);

        // 2. Convert to Sales Order
        $so = app(\App\Services\Sales\SalesQuotationService::class)->convertToSalesOrder($quotation, '2026-08-25', $this->superAdmin);
        $this->assertEquals('draft', $so->status);

        // 3. Submit and Approve SO
        $so = app(\App\Services\Sales\SalesOrderService::class)->transition($so, 'submit', $this->superAdmin);
        $so = app(\App\Services\Sales\SalesOrderService::class)->transition($so, 'approve', $this->superAdmin);
        $this->assertEquals('approved', $so->status);

        // 4. Delivery 1 (Partial - 10 units)
        $del1 = app(\App\Services\Sales\DeliveryService::class)->create($so, $this->warehouseA->id, '2026-08-20', [
            ['sales_order_line_id' => $so->lines->first()->id, 'quantity' => 10]
        ], $this->superAdmin);
        app(\App\Services\Sales\DeliveryService::class)->complete($del1, $this->superAdmin);

        $so = $so->fresh();
        $this->assertEquals('10.0000', $so->lines->first()->delivered_quantity);
        $this->assertEquals('partially_delivered', $so->status);

        // 5. Partial Invoice (10 units)
        $inv1 = app(\App\Services\Sales\SalesInvoiceService::class)->create($so, '2026-08-20', '2026-09-20', [
            [
                'sales_order_line_id' => $so->lines->first()->id,
                'quantity' => 10,
                'delivery_line_id' => $del1->lines->first()->id
            ]
        ], $this->superAdmin);
        app(\App\Services\Sales\SalesInvoiceService::class)->transition($inv1, 'issue', $this->superAdmin);

        $inv1 = $inv1->fresh();
        $this->assertEquals('issued', $inv1->status);
        $this->assertEquals('2000.0000', $inv1->total);

        // 6. Payment 1 (Full payment of Invoice 1)
        app(\App\Services\Sales\CustomerPaymentService::class)->record($inv1->id, '2000.0000', 'cash', null, null, $this->superAdmin);
        $this->assertEquals('paid', $inv1->fresh()->status);

        // 7. Delivery 2 (Remaining - 5 units)
        $del2 = app(\App\Services\Sales\DeliveryService::class)->create($so, $this->warehouseA->id, '2026-08-21', [
            ['sales_order_line_id' => $so->lines->first()->id, 'quantity' => 5]
        ], $this->superAdmin);
        app(\App\Services\Sales\DeliveryService::class)->complete($del2, $this->superAdmin);

        $so = $so->fresh();
        $this->assertEquals('15.0000', $so->lines->first()->delivered_quantity);
        $this->assertEquals('fully_delivered', $so->status);

        // 8. Remaining Invoice (5 units)
        $inv2 = app(\App\Services\Sales\SalesInvoiceService::class)->create($so, '2026-08-21', '2026-09-21', [
            [
                'sales_order_line_id' => $so->lines->first()->id,
                'quantity' => 5,
                'delivery_line_id' => $del2->lines->first()->id
            ]
        ], $this->superAdmin);
        app(\App\Services\Sales\SalesInvoiceService::class)->transition($inv2, 'issue', $this->superAdmin);

        $inv2 = $inv2->fresh();
        $this->assertEquals('1000.0000', $inv2->total);

        // 9. Final Payment (1000.0000)
        app(\App\Services\Sales\CustomerPaymentService::class)->record($inv2->id, '1000.0000', 'bank_transfer', null, null, $this->superAdmin);
        $this->assertEquals('paid', $inv2->fresh()->status);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createApprovedSalesOrder(int $quantity): SalesOrder
    {
        $so = SalesOrder::create([
            'order_number' => 'SO-TEST-' . uniqid(),
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->superAdmin->id,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'total' => bcmul((string)$quantity, '100.0000', 4),
            'subtotal' => bcmul((string)$quantity, '100.0000', 4),
        ]);

        $so->lines()->create([
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_id' => $this->unit->id,
            'unit_price' => '100.0000',
            'discount' => '0.0000',
            'tax_rate' => '0.0000',
            'tax_amount' => '0.0000',
            'line_total' => bcmul((string)$quantity, '100.0000', 4),
            'delivered_quantity' => '0.0000',
            'invoiced_quantity' => '0.0000',
        ]);

        return $so;
    }

    private function createAndCompleteDelivery(SalesOrder $order, int $quantity): Delivery
    {
        $delivery = Delivery::create([
            'delivery_number' => 'DEL-TEST-' . uniqid(),
            'sales_order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'warehouse_id' => $this->warehouseA->id,
            'delivered_by' => $this->superAdmin->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $delivery->lines()->create([
            'sales_order_line_id' => $order->lines->first()->id,
            'product_id' => $this->product->id,
            'ordered_quantity' => $order->lines->first()->quantity,
            'delivered_quantity' => $quantity,
        ]);

        return app(\App\Services\Sales\DeliveryService::class)->complete($delivery, $this->superAdmin);
    }

    private function createAndIssueInvoice(SalesOrder $order, int $quantity, int $deliveryLineId): SalesInvoice
    {
        $invoice = app(\App\Services\Sales\SalesInvoiceService::class)->create(
            $order,
            now()->toDateString(),
            now()->addMonth()->toDateString(),
            [
                [
                    'sales_order_line_id' => $order->lines->first()->id,
                    'quantity' => $quantity,
                    'delivery_line_id' => $deliveryLineId,
                ]
            ],
            $this->superAdmin
        );

        return app(\App\Services\Sales\SalesInvoiceService::class)->transition($invoice, 'issue', $this->superAdmin);
    }
}
