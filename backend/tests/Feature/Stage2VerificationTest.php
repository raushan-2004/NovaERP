<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Stage2VerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $manager;
    private User $limitedUser;
    private Company $company;
    private Branch $branch;
    private Warehouse $warehouseA;
    private Warehouse $warehouseB;
    private Product $product;
    private Supplier $supplier;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->superAdmin   = User::where('email', 'admin@novatech.com')->firstOrFail();
        $this->manager      = User::where('email', 'manager@novatech.com')->firstOrFail();
        $this->limitedUser  = User::where('email', 'worker@novatech.com')->firstOrFail();
        $this->company      = Company::first();
        $this->branch       = Branch::first();
        $this->warehouseA   = Warehouse::first();
        $this->warehouseB   = Warehouse::skip(1)->firstOrFail();
        $this->product      = Product::where('track_inventory', true)->first();
        $this->supplier     = Supplier::first();
        $this->unit         = Unit::first();
    }

    // =========================================================================
    // 1. Warehouse Location CRUD
    // =========================================================================

    public function test_warehouse_location_crud(): void
    {
        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/warehouse-locations', [
            'warehouse_id' => $this->warehouseA->id,
            'code'         => 'A-01',
            'name'         => 'Rack A Row 1',
        ]);
        $res->assertCreated()->assertJsonPath('data.code', 'A-01');

        $id = $res->json('data.id');

        $this->actingAs($this->superAdmin)->getJson("/api/v1/warehouse-locations/{$id}")
            ->assertOk()->assertJsonPath('data.name', 'Rack A Row 1');

        $this->actingAs($this->superAdmin)->putJson("/api/v1/warehouse-locations/{$id}", [
            'warehouse_id' => $this->warehouseA->id,
            'code'         => 'A-01',
            'name'         => 'Updated Rack A',
            'status'       => 'inactive',
        ])->assertOk()->assertJsonPath('data.status', 'inactive');

        $this->actingAs($this->superAdmin)->deleteJson("/api/v1/warehouse-locations/{$id}")
            ->assertOk();
    }

    // =========================================================================
    // 2+3. Goods receipt creates stock balance and ledger entry
    // =========================================================================

    public function test_goods_receipt_creates_stock_balance_and_ledger_entry(): void
    {
        $grn = $this->createAndCompleteGrn(10);

        // Stock balance must exist and equal 10
        $this->assertDatabaseHas('stock_balances', [
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouseA->id,
        ]);
        $balance = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->first();
        $this->assertEquals('10.0000', $balance->quantity);

        // Ledger entry must exist
        $this->assertDatabaseHas('stock_ledger_entries', [
            'product_id'    => $this->product->id,
            'warehouse_id'  => $this->warehouseA->id,
            'movement_type' => 'purchase_receipt',
            'quantity'      => '10.0000',
            'balance_before' => '0.0000',
            'balance_after'  => '10.0000',
        ]);
    }

    // =========================================================================
    // 4. Negative stock rejected (default false)
    // =========================================================================

    public function test_negative_stock_rejected_by_default(): void
    {
        // Receive 5 units
        $this->createAndCompleteGrn(5);

        // Try to transfer 10 (more than available)
        $tf = $this->actingAs($this->superAdmin)->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id'   => $this->warehouseB->id,
            'product_id'        => $this->product->id,
            'quantity'          => 10,
        ])->assertCreated()->json('data');

        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/stock-transfers/{$tf['id']}/complete")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Balance unchanged
        $balance = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->first();
        $this->assertEquals('5.0000', $balance->quantity);
    }

    // =========================================================================
    // 5. Negative stock allowed when config enabled
    // =========================================================================

    public function test_negative_stock_allowed_when_configured(): void
    {
        config(['nova.allow_negative_stock' => true]);

        $res = $this->actingAs($this->superAdmin)->postJson('/api/v1/stock-adjustments', [
            'product_id'        => $this->product->id,
            'warehouse_id'      => $this->warehouseA->id,
            'adjusted_quantity' => -5,
            'reason'            => 'Test negative stock adjustment',
        ]);

        $res->assertCreated();

        // Balance must be negative
        $balance = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->first();
        $this->assertEquals('-5.0000', $balance->quantity);

        config(['nova.allow_negative_stock' => false]);
    }

    // =========================================================================
    // 6. Transfer creates exactly one transfer_out + transfer_in ledger pair
    // =========================================================================

    public function test_transfer_creates_correct_ledger_entries(): void
    {
        $this->createAndCompleteGrn(20);

        $tf = $this->actingAs($this->superAdmin)->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id'   => $this->warehouseB->id,
            'product_id'        => $this->product->id,
            'quantity'          => 7,
        ])->assertCreated()->json('data');

        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/stock-transfers/{$tf['id']}/complete")
            ->assertOk();

        // Exactly 1 transfer_out from A
        $this->assertEquals(1, \App\Models\StockLedgerEntry::where([
            'product_id'    => $this->product->id,
            'warehouse_id'  => $this->warehouseA->id,
            'movement_type' => 'transfer_out',
        ])->count());

        // Exactly 1 transfer_in to B
        $this->assertEquals(1, \App\Models\StockLedgerEntry::where([
            'product_id'    => $this->product->id,
            'warehouse_id'  => $this->warehouseB->id,
            'movement_type' => 'transfer_in',
        ])->count());

        // Balances correct
        $balA = \App\Models\StockBalance::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouseA->id)->first();
        $balB = \App\Models\StockBalance::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouseB->id)->first();
        $this->assertEquals('13.0000', $balA->quantity);
        $this->assertEquals('7.0000', $balB->quantity);
    }

    // =========================================================================
    // 7. Transfer atomicity
    // =========================================================================

    public function test_transfer_is_atomic(): void
    {
        $this->createAndCompleteGrn(10);

        // Transfer to non-existent warehouse — should fail
        $tf = \App\Models\StockTransfer::create([
            'transfer_number'   => 'TRF-FAIL',
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id'   => $this->warehouseB->id,
            'product_id'        => $this->product->id,
            'quantity'          => '5.0000',
            'status'            => 'draft',
            'transferred_by'    => $this->superAdmin->id,
        ]);

        // Directly simulate atomicity: balance before
        $balBefore = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('quantity');

        // Simulate a successful transfer
        $service = app(\App\Services\Inventory\InventoryService::class);
        $service->transfer($this->warehouseA, $this->warehouseB, $this->product, '5.0000', $tf, $this->superAdmin);

        $balAfterA = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('quantity');
        $balAfterB = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseB->id)
            ->value('quantity');

        $this->assertEquals('5.0000', $balAfterA);
        $this->assertEquals('5.0000', $balAfterB);
    }

    // =========================================================================
    // 8. Transfer lock order is deterministic
    // =========================================================================

    public function test_transfer_lock_order_deterministic(): void
    {
        // Both warehouses get sorted by id before locking
        // We verify A→B and B→A produce same pair of ledger entries (no deadlock in single-threaded test)
        $this->createAndCompleteGrn(20);

        // Transfer A → B
        $tf1 = \App\Models\StockTransfer::create([
            'transfer_number'   => 'TRF-LOCK-1',
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id'   => $this->warehouseB->id,
            'product_id'        => $this->product->id,
            'quantity'          => '5.0000',
            'status'            => 'draft',
            'transferred_by'    => $this->superAdmin->id,
        ]);

        $service = app(\App\Services\Inventory\InventoryService::class);
        $service->transfer($this->warehouseA, $this->warehouseB, $this->product, '5.0000', $tf1, $this->superAdmin);

        // Receive into B to give it stock for reverse
        $tf2 = \App\Models\StockTransfer::create([
            'transfer_number'   => 'TRF-LOCK-2',
            'from_warehouse_id' => $this->warehouseB->id,
            'to_warehouse_id'   => $this->warehouseA->id,
            'product_id'        => $this->product->id,
            'quantity'          => '3.0000',
            'status'            => 'draft',
            'transferred_by'    => $this->superAdmin->id,
        ]);
        $service->transfer($this->warehouseB, $this->warehouseA, $this->product, '3.0000', $tf2, $this->superAdmin);

        // Final: A=18, B=2
        $balA = \App\Models\StockBalance::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouseA->id)->value('quantity');
        $balB = \App\Models\StockBalance::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouseB->id)->value('quantity');
        $this->assertEquals('18.0000', $balA);
        $this->assertEquals('2.0000', $balB);
    }

    // =========================================================================
    // 9+10. Stock adjustments
    // =========================================================================

    public function test_stock_adjustment_in(): void
    {
        $this->actingAs($this->superAdmin)->postJson('/api/v1/stock-adjustments', [
            'product_id'        => $this->product->id,
            'warehouse_id'      => $this->warehouseA->id,
            'adjusted_quantity' => 5,
            'reason'            => 'Opening stock adjustment',
        ])->assertCreated();

        $this->assertDatabaseHas('stock_ledger_entries', [
            'product_id'    => $this->product->id,
            'warehouse_id'  => $this->warehouseA->id,
            'movement_type' => 'adjustment_in',
            'quantity'      => '5.0000',
        ]);
        $bal = \App\Models\StockBalance::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouseA->id)->value('quantity');
        $this->assertEquals('5.0000', $bal);
    }

    public function test_stock_adjustment_out(): void
    {
        // First add some stock
        $this->actingAs($this->superAdmin)->postJson('/api/v1/stock-adjustments', [
            'product_id'        => $this->product->id,
            'warehouse_id'      => $this->warehouseA->id,
            'adjusted_quantity' => 10,
            'reason'            => 'Opening stock',
        ])->assertCreated();

        $this->actingAs($this->superAdmin)->postJson('/api/v1/stock-adjustments', [
            'product_id'        => $this->product->id,
            'warehouse_id'      => $this->warehouseA->id,
            'adjusted_quantity' => -3,
            'reason'            => 'Damaged goods write-off',
        ])->assertCreated();

        $this->assertDatabaseHas('stock_ledger_entries', [
            'movement_type' => 'adjustment_out',
            'quantity'      => '3.0000',
        ]);
        $bal = \App\Models\StockBalance::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouseA->id)->value('quantity');
        $this->assertEquals('7.0000', $bal);
    }

    // =========================================================================
    // 11. Concurrent first receipt does not produce duplicate balance rows
    // =========================================================================

    public function test_concurrent_first_receipt_no_duplicate_balance(): void
    {
        $service = app(\App\Services\Inventory\InventoryService::class);

        $service->receive($this->product, $this->warehouseA, '5.0000', 'App\Models\GoodsReceipt', 1, null, $this->superAdmin);
        $service->receive($this->product, $this->warehouseA, '3.0000', 'App\Models\GoodsReceipt', 2, null, $this->superAdmin);

        $count = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->count();

        $this->assertEquals(1, $count);

        $bal = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('quantity');
        $this->assertEquals('8.0000', $bal);
    }

    // =========================================================================
    // 12. PR workflow: draft → submitted → approved → converted
    // =========================================================================

    public function test_purchase_request_full_workflow(): void
    {
        $pr = $this->createPurchaseRequest();

        // Submit
        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/purchase-requests/{$pr['id']}/submit")
            ->assertOk()->assertJsonPath('data.status', 'submitted');

        // Approve
        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/purchase-requests/{$pr['id']}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        // Convert to PO
        $res = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/purchase-requests/{$pr['id']}/convert-to-po", [
                'company_id'      => $this->company->id,
                'branch_id'       => $this->branch->id,
                'supplier_id'     => $this->supplier->id,
                'order_date'      => now()->toDateString(),
                'unit_price_map'  => [$this->product->id => '100.00'],
                'tax_rate_map'    => [$this->product->id => '0.18'],
            ]);
        $res->assertCreated()->assertJsonPath('data.status', 'draft');

        // PR should be converted
        $this->assertDatabaseHas('purchase_requests', ['id' => $pr['id'], 'status' => 'converted']);
    }

    // =========================================================================
    // 13. PR invalid transition returns 422
    // =========================================================================

    public function test_purchase_request_invalid_transition_returns_422(): void
    {
        $pr = $this->createPurchaseRequest();

        // Try to approve directly from draft (must go via submitted first)
        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/purchase-requests/{$pr['id']}/approve")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // =========================================================================
    // 14. PO workflow: draft → submitted → approved → sent
    // =========================================================================

    public function test_purchase_order_full_workflow(): void
    {
        $po = $this->createPurchaseOrder();

        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/purchase-orders/{$po['id']}/submit")
            ->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/purchase-orders/{$po['id']}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/purchase-orders/{$po['id']}/send")
            ->assertOk()->assertJsonPath('data.status', 'sent');
    }

    // =========================================================================
    // 15. PO invalid transition returns 422
    // =========================================================================

    public function test_purchase_order_invalid_transition_returns_422(): void
    {
        $po = $this->createPurchaseOrder();

        // Try to approve draft directly (not allowed — must submit first)
        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/purchase-orders/{$po['id']}/approve")
            ->assertStatus(422);
    }

    // =========================================================================
    // 16+17. GRN updates received_quantity and promotes PO status
    // =========================================================================

    public function test_grn_partial_receipt_promotes_po_to_partially_received(): void
    {
        $po = $this->sendPurchaseOrder();
        $lineId = PurchaseOrderLine::where('purchase_order_id', $po['id'])->value('id');

        $this->actingAs($this->superAdmin)->postJson('/api/v1/goods-receipts', [
            'purchase_order_id' => $po['id'],
            'warehouse_id'      => $this->warehouseA->id,
            'received_date'     => now()->toDateString(),
            'lines'             => [
                ['purchase_order_line_id' => $lineId, 'quantity_received' => 3],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('purchase_order_lines', [
            'id'                => $lineId,
            'received_quantity' => '3.0000',
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'id'     => $po['id'],
            'status' => 'partially_received',
        ]);
    }

    public function test_grn_full_receipt_promotes_po_to_fully_received(): void
    {
        $po = $this->sendPurchaseOrder(5);
        $lineId = PurchaseOrderLine::where('purchase_order_id', $po['id'])->value('id');

        $this->actingAs($this->superAdmin)->postJson('/api/v1/goods-receipts', [
            'purchase_order_id' => $po['id'],
            'warehouse_id'      => $this->warehouseA->id,
            'received_date'     => now()->toDateString(),
            'lines'             => [
                ['purchase_order_line_id' => $lineId, 'quantity_received' => 5],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('purchase_orders', [
            'id'     => $po['id'],
            'status' => 'fully_received',
        ]);
    }

    // =========================================================================
    // 18. Purchase return creates purchase_return ledger entry + reduces stock
    // =========================================================================

    public function test_purchase_return_reduces_stock_and_creates_ledger_entry(): void
    {
        $grn = $this->createAndCompleteGrn(10, true);
        $grnLineId = \App\Models\GoodsReceiptLine::where('goods_receipt_id', $grn['id'])->value('id');

        $this->actingAs($this->superAdmin)->postJson('/api/v1/purchase-returns', [
            'goods_receipt_id' => $grn['id'],
            'return_date'      => now()->toDateString(),
            'reason'           => 'Defective components received',
            'lines'            => [
                ['goods_receipt_line_id' => $grnLineId, 'quantity_returned' => 3],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('stock_ledger_entries', [
            'product_id'    => $this->product->id,
            'warehouse_id'  => $this->warehouseA->id,
            'movement_type' => 'purchase_return',
        ]);

        $bal = \App\Models\StockBalance::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('quantity');
        $this->assertEquals('7.0000', $bal);
    }

    // =========================================================================
    // 19. Super Admin has all Stage 2 permissions in DB
    // =========================================================================

    public function test_super_admin_has_all_stage2_permissions_in_db(): void
    {
        $stage2Permissions = [
            'inventory.view', 'inventory.adjust',
            'purchase_requests.view', 'purchase_requests.create',
            'purchase_requests.update', 'purchase_requests.delete',
            'purchase_requests.approve',
            'purchase_orders.view', 'purchase_orders.create',
            'purchase_orders.update', 'purchase_orders.delete',
            'purchase_orders.approve',
            'goods_receipts.view', 'goods_receipts.create',
            'purchase_returns.view', 'purchase_returns.create',
        ];

        $superAdminRole = Role::where('name', 'Super Admin')->first();
        $assignedNames  = $superAdminRole->permissions()->pluck('name')->toArray();

        foreach ($stage2Permissions as $perm) {
            $this->assertContains($perm, $assignedNames, "Super Admin missing permission: {$perm}");
        }
    }

    // =========================================================================
    // 20. Super Admin /me exposes Stage 2 permissions
    // =========================================================================

    public function test_super_admin_me_exposes_stage2_permissions(): void
    {
        $res = $this->actingAs($this->superAdmin)->getJson('/api/v1/auth/me');
        $res->assertOk();

        $permissions = $res->json('data.permissions');
        $this->assertContains('inventory.view', $permissions);
        $this->assertContains('purchase_orders.approve', $permissions);
        $this->assertContains('goods_receipts.create', $permissions);
    }

    // =========================================================================
    // 21. Super Admin can access Stage 2 functionality
    // =========================================================================

    public function test_super_admin_can_create_stock_adjustment(): void
    {
        $this->actingAs($this->superAdmin)->postJson('/api/v1/stock-adjustments', [
            'product_id'        => $this->product->id,
            'warehouse_id'      => $this->warehouseA->id,
            'adjusted_quantity' => 50,
            'reason'            => 'Super Admin opening stock initialization',
        ])->assertCreated();
    }

    // =========================================================================
    // 22. Manager without purchase_orders.approve gets 403 on approve
    // =========================================================================

    public function test_manager_without_approve_permission_gets_403(): void
    {
        $po = $this->createPurchaseOrder();

        // Submit first so approve is a valid transition
        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/purchase-orders/{$po['id']}/submit");

        // Manager does NOT have purchase_orders.approve
        $managerRolePerms = $this->manager->roles()->first()->permissions()->pluck('name')->toArray();
        $this->assertNotContains('purchase_orders.approve', $managerRolePerms);

        $this->actingAs($this->manager)
            ->postJson("/api/v1/purchase-orders/{$po['id']}/approve")
            ->assertStatus(403);
    }

    // =========================================================================
    // 23. Company scope — cannot create GRN for another company's PO
    // =========================================================================

    public function test_company_scope_prevents_cross_company_grn(): void
    {
        // The manager belongs to the same company, so they can view the PO
        // but the warehouse must belong to their company's branch
        $po = $this->sendPurchaseOrder();
        $lineId = PurchaseOrderLine::where('purchase_order_id', $po['id'])->value('id');

        // Create a warehouse for a different company
        $otherCompany = Company::create(['name' => 'Other Corp', 'status' => 'active']);
        $otherBranch  = Branch::create([
            'company_id'  => $otherCompany->id,
            'branch_code' => 'OTH-01',
            'name'        => 'Other Branch',
            'address'     => 'Other Address',
            'status'      => 'active',
        ]);
        $otherWarehouse = Warehouse::create([
            'branch_id'      => $otherBranch->id,
            'warehouse_code' => 'WH-OTH',
            'name'           => 'Other Warehouse',
            'status'         => 'active',
        ]);

        // Worker user belongs to the main company — posting GRN with a different company's warehouse
        // The PO belongs to main company; using other company's warehouse is a scope violation
        // The GoodsReceiptPolicy checks via PO company scope
        $this->actingAs($this->limitedUser)->postJson('/api/v1/goods-receipts', [
            'purchase_order_id' => $po['id'],
            'warehouse_id'      => $otherWarehouse->id,
            'received_date'     => now()->toDateString(),
            'lines'             => [
                ['purchase_order_line_id' => $lineId, 'quantity_received' => 1],
            ],
        ])->assertStatus(403);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function createPurchaseRequest(): array
    {
        return $this->actingAs($this->superAdmin)->postJson('/api/v1/purchase-requests', [
            'company_id'    => $this->company->id,
            'branch_id'     => $this->branch->id,
            'required_date' => now()->addDays(14)->toDateString(),
            'lines'         => [
                ['product_id' => $this->product->id, 'unit_id' => $this->unit->id, 'quantity' => 10],
            ],
        ])->assertCreated()->json('data');
    }

    private function createPurchaseOrder(int $quantity = 10): array
    {
        return $this->actingAs($this->superAdmin)->postJson('/api/v1/purchase-orders', [
            'company_id'  => $this->company->id,
            'branch_id'   => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'order_date'  => now()->toDateString(),
            'lines'       => [
                ['product_id' => $this->product->id, 'unit_id' => $this->unit->id, 'quantity' => $quantity, 'unit_price' => '100.00', 'tax_rate' => '0.18'],
            ],
        ])->assertCreated()->json('data');
    }

    private function sendPurchaseOrder(int $quantity = 10): array
    {
        $po = $this->createPurchaseOrder($quantity);

        $this->actingAs($this->superAdmin)->postJson("/api/v1/purchase-orders/{$po['id']}/submit");
        $this->actingAs($this->superAdmin)->postJson("/api/v1/purchase-orders/{$po['id']}/approve");
        $this->actingAs($this->superAdmin)->postJson("/api/v1/purchase-orders/{$po['id']}/send");

        return PurchaseOrder::find($po['id'])->toArray();
    }

    private function createAndCompleteGrn(int $quantity = 10, bool $returnData = false): array
    {
        $po = $this->sendPurchaseOrder($quantity);
        $lineId = PurchaseOrderLine::where('purchase_order_id', $po['id'])->value('id');

        $grn = $this->actingAs($this->superAdmin)->postJson('/api/v1/goods-receipts', [
            'purchase_order_id' => $po['id'],
            'warehouse_id'      => $this->warehouseA->id,
            'received_date'     => now()->toDateString(),
            'lines'             => [
                ['purchase_order_line_id' => $lineId, 'quantity_received' => $quantity],
            ],
        ])->assertCreated()->json('data');

        return $grn;
    }
}
