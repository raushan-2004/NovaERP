// ─── API Type Definitions ────────────────────────────────────────────────────
// Mirrors the response envelope defined in docs/API_CONTRACT.md.
// Do not redefine these structures elsewhere.

export interface ApiSuccessResponse<T = unknown> {
  success: true;
  message: string;
  data: T;
}

export interface ApiErrorResponse {
  success: false;
  message: string;
  errors: Record<string, string[]> | null;
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
}

export interface PaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface ApiPaginatedResponse<T = unknown> {
  success: true;
  message: string;
  data: T[];
  meta: PaginationMeta;
  links: PaginationLinks;
}

// ─── Auth Types ───────────────────────────────────────────────────────────────

// ─── Auth, RBAC, Org, and Master Data Types ────────────────────────────────────

export interface Permission {
  id: number;
  name: string;
  description: string;
  status: string;
  created_at: string;
}

export interface Role {
  id: number;
  name: string;
  description: string;
  status: string;
  permissions?: Permission[];
  created_at: string;
}

export interface Company {
  id: number;
  name: string;
  registration_number?: string | null;
  address?: string | null;
  phone?: string | null;
  email?: string | null;
  website?: string | null;
  status: string;
  created_at: string;
}

export interface Branch {
  id: number;
  company_id: number;
  name: string;
  branch_code: string;
  address?: string | null;
  phone?: string | null;
  email?: string | null;
  status: string;
  company?: Company;
  created_at: string;
}

export interface Department {
  id: number;
  company_id: number;
  branch_id: number;
  name: string;
  department_code: string;
  status: string;
  company?: Company;
  branch?: Branch;
  created_at: string;
}

export interface Employee {
  id: number;
  user_id?: number | null;
  company_id: number;
  branch_id: number;
  department_id?: number | null;
  first_name: string;
  last_name: string;
  employee_code: string;
  email: string;
  phone?: string | null;
  status: string;
  user?: AuthUser | null;
  company?: Company;
  branch?: Branch;
  department?: Department | null;
  created_at: string;
}

export interface Category {
  id: number;
  name: string;
  code: string;
  description?: string | null;
  status: string;
  created_at: string;
}

export interface Brand {
  id: number;
  name: string;
  code: string;
  description?: string | null;
  status: string;
  created_at: string;
}

export interface Unit {
  id: number;
  name: string;
  abbreviation: string;
  status: string;
  created_at: string;
}

export interface Product {
  id: number;
  sku: string;
  name: string;
  barcode?: string | null;
  description?: string | null;
  category_id: number;
  brand_id: number;
  unit_id: number;
  product_type: string;
  status: string;
  category?: Category;
  brand?: Brand;
  unit?: Unit;
  created_at: string;
}

export interface Customer {
  id: number;
  company_id: number;
  customer_code: string;
  name: string;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  tax_number?: string | null;
  status: string;
  company?: Company;
  created_at: string;
}

export interface Supplier {
  id: number;
  company_id: number;
  supplier_code: string;
  name: string;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  tax_number?: string | null;
  status: string;
  company?: Company;
  created_at: string;
}

export interface Warehouse {
  id: number;
  branch_id: number;
  warehouse_code: string;
  name: string;
  address?: string | null;
  status: string;
  branch?: Branch;
  created_at: string;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  roles: Role[];
  permissions: string[];
  employee: Employee | null;
  created_at: string;
}

export interface LoginResponse {
  token: string;
  user: AuthUser;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

// ─── API Error ────────────────────────────────────────────────────────────────

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly message: string,
    public readonly errors: Record<string, string[]> | null = null,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

// ─── Stage 2 — Inventory Types ───────────────────────────────────────────────

export interface WarehouseLocation {
  id: number;
  warehouse_id: number;
  code: string;
  name: string;
  status: string;
  warehouse?: Warehouse;
  created_at: string;
}

export interface StockBalance {
  id: number;
  product_id: number;
  warehouse_id: number;
  quantity: string;
  product?: Product;
  warehouse?: Warehouse;
  updated_at: string;
}

export interface StockLedgerEntry {
  id: number;
  product_id: number;
  warehouse_id: number;
  movement_type: string;
  quantity: string;
  balance_before: string;
  balance_after: string;
  reference_type: string | null;
  reference_id: number | null;
  notes: string | null;
  created_by: number;
  occurred_at: string;
  product?: Product;
  warehouse?: Warehouse;
}

export interface StockTransfer {
  id: number;
  transfer_number: string;
  from_warehouse_id: number;
  to_warehouse_id: number;
  product_id: number;
  quantity: string;
  status: string;
  transferred_by: number;
  transferred_at: string | null;
  notes: string | null;
  from_warehouse?: Warehouse;
  to_warehouse?: Warehouse;
  product?: Product;
  created_at: string;
}

export interface StockAdjustment {
  id: number;
  product_id: number;
  warehouse_id: number;
  adjusted_quantity: string;
  reason: string;
  adjusted_by: number;
  adjusted_at: string;
  product?: Product;
  warehouse?: Warehouse;
  created_at: string;
}

// ─── Stage 2 — Purchasing Types ──────────────────────────────────────────────

export interface PurchaseRequestLine {
  id: number;
  purchase_request_id: number;
  product_id: number;
  unit_id: number;
  quantity: string;
  notes: string | null;
  product?: Product;
  unit?: Unit;
}

export interface PurchaseRequest {
  id: number;
  request_number: string;
  company_id: number;
  branch_id: number;
  requested_by: number;
  required_date: string | null;
  status: string;
  notes: string | null;
  company?: Company;
  branch?: Branch;
  lines?: PurchaseRequestLine[];
  created_at: string;
}

export interface PurchaseOrderLine {
  id: number;
  purchase_order_id: number;
  product_id: number;
  unit_id: number;
  quantity: string;
  unit_price: string;
  tax_rate: string;
  tax_amount: string;
  line_total: string;
  received_quantity: string;
  product?: Product;
  unit?: Unit;
}

export interface PurchaseOrder {
  id: number;
  po_number: string;
  company_id: number;
  branch_id: number;
  supplier_id: number;
  purchase_request_id: number | null;
  created_by: number;
  order_date: string;
  expected_delivery_date: string | null;
  status: string;
  notes: string | null;
  subtotal: string;
  tax_amount: string;
  total_amount: string;
  company?: Company;
  branch?: Branch;
  supplier?: Supplier;
  lines?: PurchaseOrderLine[];
  created_at: string;
}

export interface GoodsReceiptLine {
  id: number;
  goods_receipt_id: number;
  purchase_order_line_id: number;
  product_id: number;
  quantity_received: string;
  notes: string | null;
  product?: Product;
}

export interface GoodsReceipt {
  id: number;
  grn_number: string;
  purchase_order_id: number;
  warehouse_id: number;
  received_by: number;
  received_date: string;
  status: string;
  notes: string | null;
  purchase_order?: PurchaseOrder;
  warehouse?: Warehouse;
  lines?: GoodsReceiptLine[];
  created_at: string;
}

export interface PurchaseReturnLine {
  id: number;
  purchase_return_id: number;
  goods_receipt_line_id: number;
  product_id: number;
  quantity_returned: string;
  notes: string | null;
  product?: Product;
}

export interface PurchaseReturn {
  id: number;
  return_number: string;
  goods_receipt_id: number;
  supplier_id: number;
  returned_by: number;
  return_date: string;
  reason: string;
  status: string;
  goods_receipt?: GoodsReceipt;
  supplier?: Supplier;
  lines?: PurchaseReturnLine[];
  created_at: string;
}

// ─── Stage 3 — Sales & CRM Types ─────────────────────────────────────────────

export interface SalesQuotationLine {
  id: number;
  sales_quotation_id: number;
  product_id: number;
  unit_id: number;
  quantity: string;
  unit_price: string;
  discount: string;
  tax_rate: string;
  tax_amount: string;
  line_total: string;
  product?: Product;
  unit?: Unit;
}

export interface SalesQuotation {
  id: number;
  quotation_number: string;
  company_id: number;
  branch_id: number;
  customer_id: number;
  created_by: number;
  quotation_date: string;
  expiry_date: string | null;
  status: string;
  notes: string | null;
  subtotal: string;
  discount: string;
  tax: string;
  total: string;
  company?: Company;
  branch?: Branch;
  customer?: Customer;
  lines?: SalesQuotationLine[];
  created_at: string;
}

export interface SalesOrderLine {
  id: number;
  sales_order_id: number;
  product_id: number;
  unit_id: number;
  quantity: string;
  unit_price: string;
  discount: string;
  tax_rate: string;
  tax_amount: string;
  line_total: string;
  delivered_quantity: string;
  invoiced_quantity: string;
  product?: Product;
  unit?: Unit;
}

export interface SalesOrder {
  id: number;
  order_number: string;
  company_id: number;
  branch_id: number;
  customer_id: number;
  sales_quotation_id: number | null;
  created_by: number;
  order_date: string;
  expected_delivery_date: string | null;
  status: string;
  notes: string | null;
  subtotal: string;
  discount: string;
  tax: string;
  total: string;
  company?: Company;
  branch?: Branch;
  customer?: Customer;
  lines?: SalesOrderLine[];
  created_at: string;
}

export interface DeliveryLine {
  id: number;
  delivery_id: number;
  sales_order_line_id: number;
  product_id: number;
  ordered_quantity: string;
  delivered_quantity: string;
  product?: Product;
}

export interface Delivery {
  id: number;
  delivery_number: string;
  sales_order_id: number;
  customer_id: number;
  warehouse_id: number;
  delivered_by: number;
  delivery_date: string;
  status: string;
  notes: string | null;
  sales_order?: SalesOrder;
  customer?: Customer;
  warehouse?: Warehouse;
  lines?: DeliveryLine[];
  created_at: string;
}

export interface SalesReturnLine {
  id: number;
  sales_return_id: number;
  product_id: number;
  quantity: string;
  delivery_line_id: number;
  notes: string | null;
  product?: Product;
}

export interface SalesReturn {
  id: number;
  return_number: string;
  customer_id: number;
  sales_order_id: number;
  warehouse_id: number;
  returned_by: number;
  returned_date: string;
  reason: string;
  status: string;
  customer?: Customer;
  sales_order?: SalesOrder;
  warehouse?: Warehouse;
  lines?: SalesReturnLine[];
  created_at: string;
}

export interface SalesInvoiceLine {
  id: number;
  sales_invoice_id: number;
  product_id: number;
  quantity: string;
  unit_id: number;
  unit_price: string;
  discount: string;
  tax_rate: string;
  tax_amount: string;
  line_total: string;
  sales_order_line_id: number | null;
  delivery_line_id: number | null;
  product?: Product;
  unit?: Unit;
}

export interface SalesInvoice {
  id: number;
  invoice_number: string;
  customer_id: number;
  company_id: number;
  branch_id: number;
  sales_order_id: number | null;
  created_by: number;
  invoice_date: string;
  due_date: string;
  status: string;
  notes: string | null;
  subtotal: string;
  discount: string;
  tax: string;
  total: string;
  amount_paid: string;
  amount_due: string;
  customer?: Customer;
  company?: Company;
  branch?: Branch;
  sales_order?: SalesOrder;
  lines?: SalesInvoiceLine[];
  created_at: string;
}

export interface CustomerPayment {
  id: number;
  payment_number: string;
  customer_id: number;
  sales_invoice_id: number;
  recorded_by: number;
  payment_date: string;
  amount: string;
  payment_method: string;
  reference: string | null;
  notes: string | null;
  customer?: Customer;
  sales_invoice?: SalesInvoice;
  created_at: string;
}

export interface CustomerActivity {
  id: number;
  customer_id: number;
  user_id: number;
  activity_type: string;
  activity_date: string;
  description: string;
  notes: string | null;
  customer?: Customer;
  created_at: string;
}
