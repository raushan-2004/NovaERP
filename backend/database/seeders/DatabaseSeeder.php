<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Creates:
     * - System Permissions (Immutable)
     * - System Roles (Super Admin, Admin, Manager, Employee)
     * - Super Admin User account
     * - Company: NovaTech Industries
     * - Branches (2)
     * - Departments (3)
     * - Employees (linked to Users where appropriate)
     * - Categories (3), Brands (3), Units (5)
     * - Products (6), Customers (3), Suppliers (3), Warehouses (3)
     */
    public function run(): void
    {
        $adminPassword = config('nova.seed_admin_password');

        if (empty($adminPassword)) {
            $this->command->warn(
                'NOVA_ADMIN_PASSWORD is not set. Using fallback password "NovaAdmin@2026" for seeder.'
            );
            $adminPassword = 'NovaAdmin@2026';
        }

        // 1. Seed Permissions (system-defined capabilities, no soft-delete)
        $permissionsList = [
            // RBAC
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            // Organization
            'organization.view', 'organization.create', 'organization.update', 'organization.delete',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            // Master Data
            'products.view', 'products.create', 'products.update', 'products.delete',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            // Stage 2 — Inventory
            'inventory.view', 'inventory.adjust',
            // Stage 2 — Purchasing
            'purchase_requests.view', 'purchase_requests.create', 'purchase_requests.update', 'purchase_requests.delete',
            'purchase_requests.approve',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.delete',
            'purchase_orders.approve',
            'goods_receipts.view', 'goods_receipts.create',
            'purchase_returns.view', 'purchase_returns.create',
            // Stage 3 — Sales
            'quotations.view', 'quotations.create', 'quotations.update', 'quotations.approve', 'quotations.delete',
            'sales_orders.view', 'sales_orders.create', 'sales_orders.update', 'sales_orders.approve', 'sales_orders.delete',
            'deliveries.view', 'deliveries.create', 'deliveries.complete',
            'sales_returns.view', 'sales_returns.create', 'sales_returns.approve',
            'sales_invoices.view', 'sales_invoices.create', 'sales_invoices.issue',
            'customer_payments.view', 'customer_payments.create',
            // Stage 3 — CRM
            'crm.view', 'crm.create', 'crm.update',
        ];

        $permissionsMap = [];
        foreach ($permissionsList as $name) {
            $permissionsMap[$name] = Permission::updateOrCreate(
                ['name' => $name],
                ['description' => 'Can perform ' . str_replace('.', ' ', $name)]
            );
        }

        // 2. Seed Roles
        $superAdminRole = Role::updateOrCreate(
            ['name' => 'Super Admin'],
            ['description' => 'Super Administrator with absolute capabilities.', 'status' => 'active']
        );

        $adminRole = Role::updateOrCreate(
            ['name' => 'Admin'],
            ['description' => 'Administrator with broad management capabilities.', 'status' => 'active']
        );

        $managerRole = Role::updateOrCreate(
            ['name' => 'Manager'],
            ['description' => 'Manager with operational capabilities.', 'status' => 'active']
        );

        $employeeRole = Role::updateOrCreate(
            ['name' => 'Employee'],
            ['description' => 'Standard employee with limited lookup capabilities.', 'status' => 'active']
        );

        // Assign Permissions to Roles (Super Admin bypasses all checks, but sync for safety)
        $superAdminRole->permissions()->sync(array_values(array_map(fn($p) => $p->id, $permissionsMap)));

        $adminRole->permissions()->sync(
            Permission::whereIn('name', [
                'users.view', 'users.create', 'users.update',
                'roles.view',
                'organization.view', 'organization.create', 'organization.update',
                'employees.view', 'employees.create', 'employees.update',
                'products.view', 'products.create', 'products.update', 'products.delete',
                'customers.view', 'customers.create', 'customers.update',
                'suppliers.view', 'suppliers.create', 'suppliers.update',
                'warehouses.view', 'warehouses.create', 'warehouses.update',
                // Stage 2 — Inventory
                'inventory.view', 'inventory.adjust',
                // Stage 2 — Purchasing
                'purchase_requests.view', 'purchase_requests.create', 'purchase_requests.update', 'purchase_requests.delete',
                'purchase_requests.approve',
                'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.delete',
                'purchase_orders.approve',
                'goods_receipts.view', 'goods_receipts.create',
                'purchase_returns.view', 'purchase_returns.create',
                // Stage 3 — Sales
                'quotations.view', 'quotations.create', 'quotations.update', 'quotations.approve', 'quotations.delete',
                'sales_orders.view', 'sales_orders.create', 'sales_orders.update', 'sales_orders.approve', 'sales_orders.delete',
                'deliveries.view', 'deliveries.create', 'deliveries.complete',
                'sales_returns.view', 'sales_returns.create', 'sales_returns.approve',
                'sales_invoices.view', 'sales_invoices.create', 'sales_invoices.issue',
                'customer_payments.view', 'customer_payments.create',
                // Stage 3 — CRM
                'crm.view', 'crm.create', 'crm.update',
            ])->pluck('id')->toArray()
        );

        $managerRole->permissions()->sync(
            Permission::whereIn('name', [
                'organization.view',
                'employees.view',
                'products.view', 'products.create', 'products.update',
                'customers.view', 'customers.create', 'customers.update',
                'suppliers.view', 'suppliers.create', 'suppliers.update',
                'warehouses.view', 'warehouses.create', 'warehouses.update',
                // Stage 2 — Inventory (view only, can adjust)
                'inventory.view', 'inventory.adjust',
                // Stage 2 — Purchasing (view + create, NO approve)
                'purchase_requests.view', 'purchase_requests.create', 'purchase_requests.update',
                'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update',
                'goods_receipts.view', 'goods_receipts.create',
                'purchase_returns.view', 'purchase_returns.create',
                // Stage 3 — Sales (view + create + update, NO approve)
                'quotations.view', 'quotations.create', 'quotations.update', 'quotations.delete',
                'sales_orders.view', 'sales_orders.create', 'sales_orders.update', 'sales_orders.delete',
                'deliveries.view', 'deliveries.create', 'deliveries.complete',
                'sales_returns.view', 'sales_returns.create',
                'sales_invoices.view', 'sales_invoices.create', 'sales_invoices.issue',
                'customer_payments.view', 'customer_payments.create',
                // Stage 3 — CRM
                'crm.view', 'crm.create', 'crm.update',
            ])->pluck('id')->toArray()
        );

        $employeeRole->permissions()->sync(
            Permission::whereIn('name', [
                'products.view',
                'customers.view',
                'suppliers.view',
                'warehouses.view',
                'inventory.view',
                'purchase_requests.view',
                'purchase_orders.view',
                'goods_receipts.view',
                'purchase_returns.view',
            ])->pluck('id')->toArray()
        );

        // 3. Seed Users
        $superUser = User::updateOrCreate(
            ['email' => 'admin@novatech.com'],
            [
                'name'     => 'Nova Admin',
                'password' => Hash::make($adminPassword),
            ]
        );
        $superUser->roles()->sync([$superAdminRole->id]);

        $managerUser = User::updateOrCreate(
            ['email' => 'manager@novatech.com'],
            [
                'name'     => 'Sarah Manager',
                'password' => Hash::make('NovaManager@2026'),
            ]
        );
        $managerUser->roles()->sync([$managerRole->id]);

        $employeeUser = User::updateOrCreate(
            ['email' => 'worker@novatech.com'],
            [
                'name'     => 'John Worker',
                'password' => Hash::make('NovaWorker@2026'),
            ]
        );
        $employeeUser->roles()->sync([$employeeRole->id]);

        // 4. Seed Organization
        $company = Company::updateOrCreate(
            ['name' => 'NovaTech Industries'],
            ['status' => 'active']
        );

        $delhiBranch = Branch::updateOrCreate(
            ['company_id' => $company->id, 'branch_code' => 'DEL01'],
            [
                'name'    => 'New Delhi Headquarters',
                'address' => 'Phase 3, Okhla Industrial Area, New Delhi',
                'phone'   => '+91 11 4555 8899',
                'email'   => 'delhi@novatech.com',
                'status'  => 'active',
            ]
        );

        $mumbaiBranch = Branch::updateOrCreate(
            ['company_id' => $company->id, 'branch_code' => 'MUM02'],
            [
                'name'    => 'Mumbai Logistics Hub',
                'address' => 'Saki Naka, Andheri East, Mumbai',
                'phone'   => '+91 22 2888 7766',
                'email'   => 'mumbai@novatech.com',
                'status'  => 'active',
            ]
        );

        $engineeringDept = Department::updateOrCreate(
            ['branch_id' => $delhiBranch->id, 'department_code' => 'ENG-DEL'],
            [
                'company_id' => $company->id,
                'name'       => 'Engineering Division',
                'status'     => 'active',
            ]
        );

        $operationsDept = Department::updateOrCreate(
            ['branch_id' => $delhiBranch->id, 'department_code' => 'OPS-DEL'],
            [
                'company_id' => $company->id,
                'name'       => 'Operations Division',
                'status'     => 'active',
            ]
        );

        $qcDept = Department::updateOrCreate(
            ['branch_id' => $mumbaiBranch->id, 'department_code' => 'QC-MUM'],
            [
                'company_id' => $company->id,
                'name'       => 'Quality Control',
                'status'     => 'active',
            ]
        );

        // Seed Employees (linked to Users where appropriate)
        Employee::updateOrCreate(
            ['employee_code' => 'EMP-001'],
            [
                'first_name'        => 'Nova',
                'last_name'         => 'Admin',
                'email'             => 'admin@novatech.com',
                'phone'             => '+91 99999 11111',
                'joining_date'      => '2026-01-10',
                'designation'       => 'Chief Executive Officer',
                'employment_status' => 'active',
                'company_id'        => $company->id,
                'branch_id'         => $delhiBranch->id,
                'department_id'     => $engineeringDept->id,
                'user_id'           => $superUser->id,
            ]
        );

        Employee::updateOrCreate(
            ['employee_code' => 'EMP-002'],
            [
                'first_name'        => 'Sarah',
                'last_name'         => 'Manager',
                'email'             => 'manager@novatech.com',
                'phone'             => '+91 99999 22222',
                'joining_date'      => '2026-02-15',
                'designation'       => 'Logistics Operations Lead',
                'employment_status' => 'active',
                'company_id'        => $company->id,
                'branch_id'         => $mumbaiBranch->id,
                'department_id'     => $qcDept->id,
                'user_id'           => $managerUser->id,
            ]
        );

        Employee::updateOrCreate(
            ['employee_code' => 'EMP-003'],
            [
                'first_name'        => 'John',
                'last_name'         => 'Worker',
                'email'             => 'worker@novatech.com',
                'phone'             => '+91 99999 33333',
                'joining_date'      => '2026-03-01',
                'designation'       => 'Quality Assurance Inspector',
                'employment_status' => 'active',
                'company_id'        => $company->id,
                'branch_id'         => $mumbaiBranch->id,
                'department_id'     => $qcDept->id,
                'user_id'           => $employeeUser->id,
            ]
        );

        // 5. Seed Master Data (Global & Scoped)
        $semiCategory = Category::updateOrCreate(
            ['code' => 'SEMICON'],
            ['name' => 'Semiconductors', 'description' => 'Integrated Circuits, Microcontrollers and discrete ICs.', 'status' => 'active']
        );

        $passiveCategory = Category::updateOrCreate(
            ['code' => 'PASSIVE'],
            ['name' => 'Passive Components', 'description' => 'Resistors, Capacitors, and Inductors.', 'status' => 'active']
        );

        $powerCategory = Category::updateOrCreate(
            ['code' => 'POWER'],
            ['name' => 'Power Supplies', 'description' => 'AC-DC converters, adapters, and batteries.', 'status' => 'active']
        );

        $microBrand = Brand::updateOrCreate(
            ['code' => 'NOVAMICRO'],
            ['name' => 'NovaMicro', 'description' => 'In-house high performance silicon.', 'status' => 'active']
        );

        $circuitBrand = Brand::updateOrCreate(
            ['code' => 'CIRCUITTECH'],
            ['name' => 'CircuitTech', 'description' => 'Quality passives manufacturer.', 'status' => 'active']
        );

        $voltBrand = Brand::updateOrCreate(
            ['code' => 'POWERVOLT'],
            ['name' => 'PowerVolt', 'description' => 'Reliable power regulation products.', 'status' => 'active']
        );

        $pcsUnit = Unit::updateOrCreate(
            ['abbreviation' => 'Pcs'],
            ['name' => 'Piece', 'status' => 'active']
        );

        $boxUnit = Unit::updateOrCreate(
            ['abbreviation' => 'Box'],
            ['name' => 'Box of 100', 'status' => 'active']
        );

        $kgUnit = Unit::updateOrCreate(
            ['abbreviation' => 'Kg'],
            ['name' => 'Kilogram', 'status' => 'active']
        );

        $meterUnit = Unit::updateOrCreate(
            ['abbreviation' => 'Mtr'],
            ['name' => 'Meter', 'status' => 'active']
        );

        $literUnit = Unit::updateOrCreate(
            ['abbreviation' => 'Ltr'],
            ['name' => 'Liter', 'status' => 'active']
        );

        // Seed Products (Global)
        Product::updateOrCreate(
            ['sku' => 'NVM-MCU-8BIT'],
            [
                'name'            => 'NovaMicro 8-Bit MCU',
                'description'     => 'Low power microcontroller for standard electronic assemblies.',
                'category_id'     => $semiCategory->id,
                'brand_id'        => $microBrand->id,
                'unit_id'         => $pcsUnit->id,
                'product_type'    => 'finished_good',
                'status'          => 'active',
                'track_inventory' => true,
            ]
        );

        Product::updateOrCreate(
            ['sku' => 'CAP-CER-10UF'],
            [
                'name'            => '10uF Ceramic Capacitor',
                'description'     => 'Slices high-frequency noise from DC lines.',
                'category_id'     => $passiveCategory->id,
                'brand_id'        => $circuitBrand->id,
                'unit_id'         => $boxUnit->id,
                'product_type'    => 'raw_material',
                'status'          => 'active',
                'track_inventory' => true,
            ]
        );

        Product::updateOrCreate(
            ['sku' => 'PWR-ADAPT-12V'],
            [
                'name'            => '12V 2A DC Power Adapter',
                'description'     => 'External power adapter with standard barrel jack.',
                'category_id'     => $powerCategory->id,
                'brand_id'        => $voltBrand->id,
                'unit_id'         => $pcsUnit->id,
                'product_type'    => 'finished_good',
                'status'          => 'active',
                'track_inventory' => true,
            ]
        );

        // Seed Customers (Company-scoped)
        Customer::updateOrCreate(
            ['company_id' => $company->id, 'customer_code' => 'CUST-001'],
            [
                'name'             => 'VoltRetailers India',
                'email'            => 'procure@voltretailers.com',
                'phone'            => '+91 11 5566 7788',
                'billing_address'  => 'Sector 62, Noida, UP',
                'shipping_address' => 'Sector 63, Noida, UP',
                'status'           => 'active',
            ]
        );

        Customer::updateOrCreate(
            ['company_id' => $company->id, 'customer_code' => 'CUST-002'],
            [
                'name'             => 'ElectroDistribution Corp',
                'email'            => 'orders@electrodist.com',
                'phone'            => '+91 22 6677 8899',
                'billing_address'  => 'Nariman Point, Mumbai',
                'shipping_address' => 'Vashi, Navi Mumbai',
                'status'           => 'active',
            ]
        );

        // Seed Suppliers (Company-scoped)
        Supplier::updateOrCreate(
            ['company_id' => $company->id, 'supplier_code' => 'SUPP-001'],
            [
                'name'    => 'ComponentWorld Shenzhen',
                'email'   => 'sales@componentworld.cn',
                'phone'   => '+86 755 8320 0000',
                'address' => 'Huaqiangbei, Futian District, Shenzhen',
                'status'  => 'active',
            ]
        );

        Supplier::updateOrCreate(
            ['company_id' => $company->id, 'supplier_code' => 'SUPP-002'],
            [
                'name'    => 'SiliconSupplies Singapore',
                'email'   => 'orders@siliconsupplies.sg',
                'phone'   => '+65 6744 1234',
                'address' => 'Changi North Way, Singapore',
                'status'  => 'active',
            ]
        );

        // Seed Warehouses (Branch-scoped)
        Warehouse::updateOrCreate(
            ['branch_id' => $delhiBranch->id, 'warehouse_code' => 'WH-DEL-01'],
            [
                'name'    => 'Main Storage A',
                'address' => 'Okhla Phase 3, Delhi',
                'status'  => 'active',
            ]
        );

        Warehouse::updateOrCreate(
            ['branch_id' => $mumbaiBranch->id, 'warehouse_code' => 'WH-MUM-02'],
            [
                'name'    => 'Mumbai Transit Warehouse B',
                'address' => 'Andheri East, Mumbai',
                'status'  => 'active',
            ]
        );

        $this->command->info('Database seeding completed successfully.');
    }
}
