# NovaERP — Development Rules

> These rules apply to ALL implementation stages. Future Antigravity stages must follow these conventions without exception.

---

## 1. Coding Conventions

### PHP
- PHP 8.3+ features encouraged (readonly properties, enums, typed properties, match expressions)
- PSR-12 code style enforced via Laravel Pint
- Strict types declaration in all files: `declare(strict_types=1);`
- Return types declared on all methods
- No `var_dump`, `die`, `dd` in production code
- Docblocks only where they add value beyond type hints

### TypeScript
- `strict: true` in tsconfig
- No implicit `any`
- Explicit return types on exported functions
- No `@ts-ignore` without explanation comment
- Interfaces over type aliases for object shapes (unless union/intersection types are needed)

---

## 2. Architecture Rules

### Controllers
- Controllers MUST remain thin
- No business logic in controllers
- Controllers: validate → delegate to service → return response
- Each controller method does one thing

### Services
- All business logic lives in `app/Services/`
- Services are injected via constructor or resolved by the container
- Services must not directly return HTTP responses

### Exception Handling
- All exception handling configuration is in `bootstrap/app.php` via `withExceptions()`
- Do NOT create `app/Exceptions/Handler.php`
- Use Laravel's built-in exception types where they exist
- Custom exceptions should extend `RuntimeException` or Laravel base exceptions

### Configuration
- `env()` is called ONLY in `config/*.php` files
- Application code ALWAYS uses `config()` — never `env()` directly
- This ensures `php artisan config:cache` compatibility

---

## 3. API Rules

All API conventions are defined in `docs/API_CONTRACT.md`.

Do NOT redefine API conventions in stage-specific code or documentation.

Summary:
- All routes under `/api/v1/`
- All responses use the standard envelope: `{ success, message, data }`
- All errors use: `{ success: false, message, errors }`
- Validation errors: 422 with field-level `errors` object
- Authentication errors: 401
- Authorization errors: 403
- Not found: 404
- Server error: 500 (no stack trace in production)

---

## 4. Database Rules

- **NEVER** use `FLOAT` or `DOUBLE` for financial values. Use `NUMERIC(15,4)` (Eloquent: `decimal:4`)
- Primary keys: `BIGSERIAL` (bigIncrements) by default
- Every table must have `created_at` and `updated_at` (Eloquent timestamps)
- Foreign keys must declare `constrained()` with explicit `onDelete()` behaviour
- Never write raw SQL when Eloquent/Query Builder can express the query safely
- Migrations are immutable after deployment — create new migrations, never edit old ones
- Index frequently filtered and sorted columns
- Do not soft-delete every table — apply selectively to business entities

---

## 5. State Management Rules (Frontend)

- **TanStack Query owns all server/API state** — fetched data is never stored in Zustand
- **Zustand owns client/UI state** — token presence, sidebar state, UI flags
- No `localStorage` usage for sensitive data without explicit security review
- Token storage strategy (session vs cookie) will be finalized in Stage 1

---

## 6. Security Rules

- Never commit `.env` files or any file containing credentials
- Never call `env()` outside `config/` files
- Never log sensitive values (passwords, tokens, personal data)
- Always use parameterized queries (Eloquent handles this)
- Validate all user input via Form Requests before it reaches the service layer
- Configure CORS intentionally — never use `*` for allowed origins in production
- Protect all non-public API endpoints with `auth:sanctum` middleware

---

## 7. Dependency Rules

- Introduce new backend packages only when they provide genuine value that Laravel cannot provide natively
- Introduce new frontend packages only when they solve a problem that existing packages cannot
- Do not add packages to experiment — only add what will be committed to
- Every new package must be justified in the PR/commit description
- Do not introduce Redis, queues, Elasticsearch, or microservices without explicit discussion

---

## 8. Testing Expectations

### Backend
- All new features must have Feature tests covering the happy path and key error paths
- Use `RefreshDatabase` trait for tests that touch the database
- Tests run against `novaerp_test` PostgreSQL database (see `backend/.env.testing`)
- Run `php artisan test --env=testing` before committing
- Do not merge code with failing tests

### Frontend
- Run `npx tsc --noEmit` before committing — zero type errors
- Run `npm run build` — must produce a clean build
- Run `npm run lint` — zero ESLint errors

---

## 9. Antigravity Stage Rules

Each Antigravity stage follows this pattern:

1. **Plan** — create `implementation_plan.md` with `RequestFeedback: true`
2. **Review** — user approves the plan before any code is written
3. **Implement** — follow the approved plan exactly
4. **Verify** — run all verification commands listed in the plan
5. **Report** — create `walkthrough.md` summarizing what was done
6. **Stage approval** — explicit "STAGE N — APPROVED" sign-off

**Do NOT:**
- Implement business modules in Stage 0 or ahead of their assigned stage
- Skip verification before declaring a stage complete
- Move to the next stage without explicit user approval

### Stage Sequence
- **Stage 0** — Foundation ✅ (current)
- **Stage 1** — Authentication completion + RBAC + Organization + Master Data
- **Stage 2+** — ERP business modules (assigned per stage)
