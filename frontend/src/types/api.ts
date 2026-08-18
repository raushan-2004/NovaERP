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
