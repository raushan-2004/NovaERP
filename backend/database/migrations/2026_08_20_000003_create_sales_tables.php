<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sales Quotations
        Schema::create('sales_quotations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('quotation_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('company_id')->constrained('companies')->onDelete('restrict');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->string('status')->default('draft'); // draft, sent, accepted, rejected, expired
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount', 15, 4)->default(0);
            $table->decimal('tax', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        // 2. Sales Quotation Lines
        Schema::create('sales_quotation_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('sales_quotation_id')->constrained('sales_quotations')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->decimal('quantity', 15, 4);
            $table->foreignId('unit_id')->constrained('units')->onDelete('restrict');
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount', 15, 4)->default(0);
            $table->decimal('tax_rate', 5, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4)->default(0);
            $table->timestamps();
        });

        // 3. Sales Orders
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('company_id')->constrained('companies')->onDelete('restrict');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->foreignId('sales_quotation_id')->nullable()->constrained('sales_quotations')->onDelete('restrict');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status')->default('draft'); // draft, submitted, approved, partially_delivered, fully_delivered, cancelled
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount', 15, 4)->default(0);
            $table->decimal('tax', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        // 4. Sales Order Lines
        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->decimal('quantity', 15, 4); // ordered_quantity
            $table->foreignId('unit_id')->constrained('units')->onDelete('restrict');
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount', 15, 4)->default(0);
            $table->decimal('tax_rate', 5, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4)->default(0);
            $table->decimal('delivered_quantity', 15, 4)->default(0);
            $table->decimal('invoiced_quantity', 15, 4)->default(0);
            $table->timestamps();
        });

        // 5. Deliveries (Shipments)
        Schema::create('deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('delivery_number')->unique();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('restrict');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('delivered_by')->constrained('users')->onDelete('restrict');
            $table->date('delivery_date');
            $table->string('status')->default('draft'); // draft, completed, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sales_order_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        // 6. Delivery Lines
        Schema::create('delivery_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('delivery_id')->constrained('deliveries')->onDelete('cascade');
            $table->foreignId('sales_order_line_id')->constrained('sales_order_lines')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->decimal('ordered_quantity', 15, 4);
            $table->decimal('delivered_quantity', 15, 4);
            $table->timestamps();
        });

        // 7. Sales Returns
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('return_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('returned_by')->constrained('users')->onDelete('restrict');
            $table->date('returned_date');
            $table->string('reason');
            $table->string('status')->default('draft'); // draft, approved, completed, cancelled
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sales_order_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        // 8. Sales Return Lines
        Schema::create('sales_return_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('sales_return_id')->constrained('sales_returns')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->decimal('quantity', 15, 4);
            $table->foreignId('delivery_line_id')->nullable()->constrained('delivery_lines')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 9. Sales Invoices
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('company_id')->constrained('companies')->onDelete('restrict');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->onDelete('restrict');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('status')->default('draft'); // draft, issued, partially_paid, paid, overdue
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount', 15, 4)->default(0);
            $table->decimal('tax', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->decimal('amount_paid', 15, 4)->default(0);
            $table->decimal('amount_due', 15, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        // 10. Sales Invoice Lines
        Schema::create('sales_invoice_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->decimal('quantity', 15, 4);
            $table->foreignId('unit_id')->constrained('units')->onDelete('restrict');
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount', 15, 4)->default(0);
            $table->decimal('tax_rate', 5, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4)->default(0);
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->onDelete('restrict');
            $table->foreignId('delivery_line_id')->nullable()->constrained('delivery_lines')->onDelete('restrict');
            $table->timestamps();
        });

        // 11. Customer Payments
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('payment_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->onDelete('restrict');
            $table->foreignId('recorded_by')->constrained('users')->onDelete('restrict');
            $table->date('payment_date');
            $table->decimal('amount', 15, 4);
            $table->string('payment_method'); // cash, bank_transfer, card, UPI, other
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'sales_invoice_id']);
        });

        // 12. CRM Activities
        Schema::create('customer_activities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('type'); // call, email, meeting, note, follow-up
            $table->string('subject');
            $table->text('description')->nullable();
            $table->date('activity_date');
            $table->date('follow_up_date')->nullable();
            $table->string('status')->default('completed'); // scheduled, completed
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_activities');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('sales_invoice_lines');
        Schema::dropIfExists('sales_invoices');
        Schema::dropIfExists('sales_return_lines');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('delivery_lines');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('sales_quotation_lines');
        Schema::dropIfExists('sales_quotations');
    }
};
