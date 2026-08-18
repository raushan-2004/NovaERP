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

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  created_at: string;
}

export interface LoginResponse {
  token: string;
  user: Pick<AuthUser, 'id' | 'name' | 'email'>;
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
