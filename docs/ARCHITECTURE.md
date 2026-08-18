# NovaERP — Architecture

## System Overview

```
React Frontend (localhost:5173)
        ↓  HTTP/JSON
REST API /api/v1
        ↓
Laravel 13 Backend (localhost:8000)
        ↓
Service Layer (business logic)
        ↓
Eloquent ORM
        ↓
PostgreSQL 18.6
```

---

## Backend Architecture

### Directory Layout (key paths)

```
backend/app/
├── Http/
│   ├── Controllers/Api/V1/     ← Thin controllers per domain
│   │   ├── Auth/AuthController.php
│   │   └── HealthController.php
│   └── Requests/               ← Form Request validation
│       └── Auth/LoginRequest.php
├── Services/                   ← Business logic layer
│   └── Auth/AuthService.php
├── Support/
│   └── ApiResponse.php         ← Shared response builder
└── Models/
    └── User.php                ← Eloquent model
```

### Request Lifecycle

```
HTTP Request
    ↓
HandleCors (framework middleware)
    ↓
withExceptions shouldRenderJsonWhen (all /api/* → JSON)
    ↓
FormRequest (input validation → 422 if invalid)
    ↓
Controller (dispatch only — no business logic)
    ↓
Service (all business logic lives here)
    ↓
Eloquent Model ↔ PostgreSQL
    ↓
ApiResponse (consistent JSON envelope)
```

### Exception Handling

Configured in `bootstrap/app.php` via `withExceptions()`. No `Handler.php` class.

- `ValidationException` → 422 (Laravel automatic)
- `AuthenticationException` → 401 (envelope override)
- `AuthorizationException` → 403 (Laravel automatic)
- `ModelNotFoundException` → 404 (envelope override)
- `Throwable` → 500 (message hidden in production)

### CORS

Laravel's built-in `HandleCors` middleware.
If origin customisation is required: `php artisan config:publish cors`.

---

## Frontend Architecture

### Directory Layout

```
frontend/src/
├── api/            ← HTTP call functions (Axios)
│   ├── client.ts   ← Axios instance + interceptors
│   └── auth.ts     ← Auth API calls
├── store/          ← Zustand — client/global state only
│   └── auth.ts     ← Token, isAuthenticated
├── hooks/          ← TanStack Query — server state
│   └── useAuth.ts  ← useCurrentUser, useLogin, useLogout
├── components/
│   └── layout/     ← AppShell, Sidebar, Header
├── pages/          ← Route-level components
├── router/         ← React Router v7
├── types/          ← Shared TypeScript interfaces
└── features/       ← Future ERP module feature folders
```

### State Management

| State Type | Owner | Examples |
|---|---|---|
| Server/API data | **TanStack Query v5** | User profile, any ERP data |
| Client/UI state | **Zustand v5** | Token, isAuthenticated, sidebar state |

**Rule:** API response data is never stored in Zustand. TanStack Query owns all fetched data.

### Authentication Flow

```
User submits login form
    ↓
useLogin mutation → POST /api/v1/auth/login
    ↓
On success:
  - Store token in Zustand (in-memory)
  - Seed TanStack Query cache with user data
  - Navigate to /dashboard
    ↓
All subsequent requests: token attached via Axios interceptor
    ↓
On 401 response: Axios interceptor clears Zustand auth state
```

> **Token Persistence Note:** Token is stored in-memory only (no localStorage).
> The production authentication persistence strategy will be evaluated during the Authentication/RBAC stage (Stage 1).

### Routing

React Router v7. Protected routes require `isAuthenticated` in Zustand.
Unauthenticated users are redirected to `/login`.

---

## API Architecture

All conventions are defined in `docs/API_CONTRACT.md`. Do not redefine them elsewhere.

- Base path: `/api/v1`
- Consistent JSON envelope: `{ success, message, data }`
- Consistent error envelope: `{ success: false, message, errors }`
- Authentication: Sanctum Bearer token

---

## Database Strategy

- Engine: PostgreSQL 18.6
- ORM: Eloquent
- Migrations: versioned, never edited after deployment
- Primary keys: `BIGSERIAL` (auto-increment bigint)
- Financial values: `NUMERIC(15,4)` — never float
- Soft deletes: selective (business entities only)
- Timestamps: all tables

---

## Module Boundaries (Future Stages)

Each future ERP module will follow this pattern:

```
backend/app/
├── Http/Controllers/Api/V1/{Module}/
├── Http/Requests/{Module}/
├── Services/{Module}/
├── Models/{Module models}
└── (no Handler.php — all exceptions via bootstrap/app.php)

frontend/src/features/{module}/
├── api/          ← module-specific API calls
├── components/   ← module UI components
├── hooks/        ← TanStack Query hooks
├── pages/        ← route-level pages
└── types/        ← module-specific TypeScript types
```

---

## Key Architectural Decisions

| Decision | Rationale |
|---|---|
| No `Handler.php` | Laravel 13 native `withExceptions()` is cleaner and requires no legacy override file |
| `ApiResponse` as static helper | Provides consistent envelope without inventing a framework |
| TanStack Query for server state | Removes caching/loading/error boilerplate from components |
| Zustand for client state | Minimal, focused global state without Redux complexity |
| Tailwind v4 CSS-first | No config file, faster builds, CSS custom properties as the design token system |
| `config/nova.php` for env access | All `env()` calls centralized — compatible with `config:cache` |
| PostgreSQL | Required for future financial data (NUMERIC type), advanced constraints, UUID support |
| No Redis in Stage 0 | Not required yet; will be introduced when queues/caching needs materialize |
| sessionStorage for token | Used for client session restoration on refresh in local/staging; XSS warning acknowledged, HttpOnly cookies deferred to final production |
| Request-scoped permission cache | Instance-level array on User model prevents N+1 queries on permission checks without leaking state globally |
| Global vs Scoped Master Data | Global: Category, Brand, Unit, Product (no company_id); Company-scoped: Customer, Supplier; Branch-scoped: Warehouse |

---

## Authorization & Scope Boundaries (Stage 1)

### Request Authorization Flow
To safely support permission-based capabilities and data-ownership security policies without conflicts:
- **Permission Middleware (`CheckPermission`)**: Validates if the authenticated user has a general system capability (e.g. `products.update`). Super Admin bypasses this.
- **Eloquent Policies**: Checks record-level context (e.g. "Can this user edit *this* specific warehouse?"). Super Admins do **not** bypass Policy rules.

### Organizational Scope Mapping
All transactional modules must enforce matching scopes:
- **Scoping Context**: `User → Employee (optional) → Company → Branch`.
- **Composite Unique Constraints**: Enforced at the database level to ensure codes are scoped within branches/companies:
  - Branch codes: `UNIQUE(company_id, branch_code)`
  - Department codes: `UNIQUE(branch_id, department_code)`
  - Warehouse codes: `UNIQUE(branch_id, warehouse_code)`

