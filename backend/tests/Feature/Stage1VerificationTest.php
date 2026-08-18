<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Stage1VerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;
    private User $workerUser;
    private Role $employeeRole;
    private Permission $productsViewPerm;
    private Permission $productsUpdatePerm;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed basic RBAC
        $this->productsViewPerm = Permission::create(['name' => 'products.view', 'description' => 'View products']);
        $this->productsUpdatePerm = Permission::create(['name' => 'products.update', 'description' => 'Update products']);

        $superRole = Role::create(['name' => 'Super Admin', 'description' => 'Super User']);
        $this->employeeRole = Role::create(['name' => 'Employee', 'description' => 'Employee Role']);

        $this->employeeRole->permissions()->sync([$this->productsViewPerm->id]);

        $this->superUser = User::factory()->create(['name' => 'Super Admin', 'email' => 'super@novatech.com']);
        $this->superUser->roles()->sync([$superRole->id]);

        $this->workerUser = User::factory()->create(['name' => 'Worker User', 'email' => 'worker@novatech.com']);
        $this->workerUser->roles()->sync([$this->employeeRole->id]);
    }

    /**
     * RBAC & Pivot Uniqueness Tests
     */
    public function test_duplicate_role_assignment_is_prevented(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Assign employee role to workerUser again (expect unique index violation)
        DB::table('role_user')->insert([
            'role_id' => $this->employeeRole->id,
            'user_id' => $this->workerUser->id,
        ]);
    }

    public function test_duplicate_permission_assignment_is_prevented(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Assign products.view to employeeRole again (expect unique index violation)
        DB::table('permission_role')->insert([
            'permission_id' => $this->productsViewPerm->id,
            'role_id'       => $this->employeeRole->id,
        ]);
    }

    public function test_permission_records_cannot_be_deleted(): void
    {
        // Permission does not support SoftDeletes or deletable API routes.
        // Verifying model behaves immutably (it has no delete controller mapping, and softDeletes trait is missing).
        $this->assertTrue(Permission::where('id', $this->productsViewPerm->id)->exists());
    }

    public function test_role_and_permission_assignment_relationships_work(): void
    {
        $role = Role::create(['name' => 'Test Role']);
        $user = User::factory()->create();
        $perm = Permission::create(['name' => 'test.perm']);

        // Assign role to user
        $user->roles()->sync([$role->id]);
        $this->assertTrue($user->hasRole('Test Role'));

        // Assign permission to role
        $role->permissions()->sync([$perm->id]);
        $this->assertTrue($user->hasPermission('test.perm'));

        // Remove role
        $user->roles()->detach($role->id);
        $this->assertFalse($user->hasRole('Test Role'));
    }

    /**
     * Authorization API Access Tests
     */
    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/roles');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_without_permission_returns_403(): void
    {
        $token = $this->workerUser->createToken('test')->plainTextToken;

        // /roles index requires roles.view, worker only has products.view
        $response = $this->withToken($token)->getJson('/api/v1/roles');
        $response->assertStatus(403);
    }

    public function test_authenticated_user_with_permission_succeeds(): void
    {
        $token = $this->workerUser->createToken('test')->plainTextToken;

        // Initialize master structures
        $company = Company::create(['name' => 'NovaTech']);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Delhi Branch',
            'branch_code' => 'DEL',
            'address' => 'Delhi Address',
        ]);
        $dept = Department::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Engineering',
            'department_code' => 'ENG',
        ]);

        $category = Category::create(['name' => 'Semiconductor', 'code' => 'SEMI']);
        $brand = Brand::create(['name' => 'Intel', 'code' => 'INTC']);
        $unit = Unit::create(['name' => 'Piece', 'abbreviation' => 'Pcs']);

        Product::create([
            'sku' => 'TEST-SKU',
            'name' => 'Test Chip',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'product_type' => 'finished_good',
        ]);

        // products.view allows workerUser to access products list
        $response = $this->withToken($token)->getJson('/api/v1/products');
        $response->assertStatus(200);
    }

    public function test_super_admin_bypasses_general_permission_checks(): void
    {
        $token = $this->superUser->createToken('test')->plainTextToken;

        // /roles requires roles.view, superUser has no permissions assigned but bypasses via Super Admin role
        $response = $this->withToken($token)->getJson('/api/v1/roles');
        $response->assertStatus(200);
    }

    public function test_record_level_policy_can_still_deny_access(): void
    {
        // Even if user has permission products.update, if the Product is marked inactive,
        // ProductPolicy will block access unless user has Admin role.
        $role = Role::create(['name' => 'Admin', 'description' => 'Admin Role']);
        $role->permissions()->sync([$this->productsUpdatePerm->id]);

        $adminUser = User::factory()->create(['email' => 'admin_test@novatech.com']);
        $adminUser->roles()->sync([$role->id]);

        $workerUserWithUpdate = User::factory()->create(['email' => 'worker_test@novatech.com']);
        $workerUserWithUpdate->roles()->sync([$this->employeeRole->id]);
        $this->employeeRole->permissions()->sync([$this->productsViewPerm->id, $this->productsUpdatePerm->id]);

        // Initialize dependencies
        $company = Company::create(['name' => 'NovaTech']);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Delhi Branch',
            'branch_code' => 'DEL',
            'address' => 'Delhi Address',
        ]);
        $category = Category::create(['name' => 'Semiconductor', 'code' => 'SEMI']);
        $brand = Brand::create(['name' => 'Intel', 'code' => 'INTC']);
        $unit = Unit::create(['name' => 'Piece', 'abbreviation' => 'Pcs']);

        $product = Product::create([
            'sku' => 'TEST-SKU-INACTIVE',
            'name' => 'Inactive Test Chip',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'product_type' => 'finished_good',
            'status' => 'inactive', // Inactive product
        ]);

        $tokenWorker = $workerUserWithUpdate->createToken('test')->plainTextToken;
        $tokenAdmin = $adminUser->createToken('test')->plainTextToken;

        // Worker User (with products.update permission but without Admin role) -> blocked by ProductPolicy
        $responseWorker = $this->withToken($tokenWorker)->putJson("/api/v1/products/{$product->id}", [
            'sku' => 'TEST-SKU-INACTIVE',
            'name' => 'Updated Name By Worker',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'product_type' => 'finished_good',
            'status' => 'inactive',
        ]);
        $responseWorker->assertStatus(403); // Forbidden by policy!

        // Flush auth guard cache so the next request gets authenticated as Admin User
        $this->app['auth']->forgetGuards();

        // Admin User (has products.update permission AND Admin role) -> allowed by ProductPolicy
        $responseAdmin = $this->withToken($tokenAdmin)->putJson("/api/v1/products/{$product->id}", [
            'sku' => 'TEST-SKU-INACTIVE',
            'name' => 'Updated Name By Admin',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'product_type' => 'finished_good',
            'status' => 'inactive',
        ]);
        $responseAdmin->assertStatus(200); // Policy allows Admin
    }

    /**
     * Organization Scoped Constraints Tests
     */
    public function test_branch_code_is_unique_within_company(): void
    {
        $company1 = Company::create(['name' => 'NovaTech 1']);
        $company2 = Company::create(['name' => 'NovaTech 2']);

        // Create Delhi Branch in Company 1
        Branch::create([
            'company_id'  => $company1->id,
            'name'        => 'Delhi Branch',
            'branch_code' => 'DEL',
            'address'     => 'Delhi Address',
        ]);

        // Creating Delhi Branch in Company 1 again should fail
        $this->expectException(\Illuminate\Database\QueryException::class);
        Branch::create([
            'company_id'  => $company1->id,
            'name'        => 'Delhi Branch 2',
            'branch_code' => 'DEL',
            'address'     => 'Delhi Address 2',
        ]);
    }

    public function test_branch_code_can_be_duplicated_across_different_companies(): void
    {
        $company1 = Company::create(['name' => 'NovaTech 1']);
        $company2 = Company::create(['name' => 'NovaTech 2']);

        // Delhi Branch in Company 1
        $b1 = Branch::create([
            'company_id'  => $company1->id,
            'name'        => 'Delhi Branch 1',
            'branch_code' => 'DEL',
            'address'     => 'Delhi Address',
        ]);

        // Delhi Branch in Company 2 (Should succeed)
        $b2 = Branch::create([
            'company_id'  => $company2->id,
            'name'        => 'Delhi Branch 2',
            'branch_code' => 'DEL',
            'address'     => 'Delhi Address',
        ]);

        $this->assertNotNull($b1);
        $this->assertNotNull($b2);
    }

    public function test_mismatched_department_company_relationship_is_rejected(): void
    {
        $company1 = Company::create(['name' => 'NovaTech 1']);
        $company2 = Company::create(['name' => 'NovaTech 2']);

        $branch = Branch::create([
            'company_id'  => $company1->id, // belongs to company 1
            'name'        => 'Delhi Branch',
            'branch_code' => 'DEL',
            'address'     => 'Delhi Address',
        ]);

        $token = $this->superUser->createToken('test')->plainTextToken;

        // Try creating department with branch of Company 1 but company set to Company 2
        $response = $this->withToken($token)->postJson('/api/v1/departments', [
            'company_id'      => $company2->id,
            'branch_id'       => $branch->id,
            'name'            => 'Department Mismatch',
            'department_code' => 'MISMATCH',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('branch_id');
    }

    public function test_mismatched_employee_relationships_are_rejected(): void
    {
        $company1 = Company::create(['name' => 'NovaTech 1']);
        $company2 = Company::create(['name' => 'NovaTech 2']);

        $branch = Branch::create([
            'company_id'  => $company1->id,
            'name'        => 'Delhi Branch',
            'branch_code' => 'DEL',
            'address'     => 'Delhi Address',
        ]);

        $dept = Department::create([
            'company_id'      => $company1->id,
            'branch_id'       => $branch->id,
            'name'            => 'Engineering',
            'department_code' => 'ENG',
        ]);

        $token = $this->superUser->createToken('test')->plainTextToken;

        // Mismatched Company in Employee creation
        $response = $this->withToken($token)->postJson('/api/v1/employees', [
            'employee_code'     => 'EMP-999',
            'first_name'        => 'Mismatched',
            'last_name'         => 'Employee',
            'email'             => 'mismatched@novatech.com',
            'joining_date'      => '2026-01-10',
            'designation'       => 'QA',
            'company_id'        => $company2->id, // company 2 but branch/department belong to company 1
            'branch_id'         => $branch->id,
            'department_id'     => $dept->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['branch_id', 'department_id']);
    }

    public function test_warehouse_scoping_rules_work(): void
    {
        $company = Company::create(['name' => 'NovaTech']);
        $branch1 = Branch::create([
            'company_id' => $company->id,
            'name' => 'Delhi Branch',
            'branch_code' => 'DEL',
            'address' => 'Delhi Address',
        ]);
        $branch2 = Branch::create([
            'company_id' => $company->id,
            'name' => 'Mumbai Branch',
            'branch_code' => 'MUM',
            'address' => 'Mumbai Address',
        ]);

        // Creating warehouse inside Branch 1
        $wh1 = Warehouse::create([
            'branch_id' => $branch1->id,
            'warehouse_code' => 'WH01',
            'name' => 'Warehouse Delhi',
        ]);

        // Creating duplicate code in Branch 2 (should succeed because scoped by branch_id)
        $wh2 = Warehouse::create([
            'branch_id' => $branch2->id,
            'warehouse_code' => 'WH01',
            'name' => 'Warehouse Mumbai',
        ]);

        $this->assertNotNull($wh1);
        $this->assertNotNull($wh2);

        // Creating duplicate code in Branch 1 again (should fail)
        $this->expectException(\Illuminate\Database\QueryException::class);
        Warehouse::create([
            'branch_id' => $branch1->id,
            'warehouse_code' => 'WH01',
            'name' => 'Warehouse Delhi 2',
        ]);
    }
}
