# NovaERP

Enterprise Resource Planning platform for NovaTech Industries — a fictional electronics manufacturing and distribution company.

Built on a modular, production-quality foundation using Laravel 13 + React 19 + PostgreSQL 18.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.5.9, Laravel 13.x |
| Auth | Laravel Sanctum |
| ORM | Eloquent |
| Database | PostgreSQL 18.6 |
| Frontend | React 19, TypeScript, Vite |
| Styling | Tailwind CSS v4 |
| Routing | React Router v7 |
| Server State | TanStack Query v5 |
| Client State | Zustand v5 |
| HTTP | Axios |

---

## Project Structure

```
NovaERP/
├── backend/        # Laravel 13 REST API
├── frontend/       # React 19 + Vite SPA
├── docs/
│   ├── PROJECT_SPEC.md      # Business context and planned modules
│   ├── ARCHITECTURE.md      # Technical architecture
│   ├── DEVELOPMENT_RULES.md # Coding standards and rules
│   └── API_CONTRACT.md      # API conventions (single source of truth)
├── README.md
└── .gitignore
```

---

## Local Setup

### Prerequisites

- PHP 8.3+ with extensions: `pdo_pgsql`, `mbstring`, `openssl`, `bcmath`, `ctype`, `json`, `tokenizer`, `xml`
- Composer 2.x
- Node.js 18+ and npm
- PostgreSQL 14+ (18.6 used in development)
- Git

### 1. Clone the repository

```bash
git clone <repository-url>
cd NovaERP
```

### 2. Backend Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Edit `backend/.env`:
```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=novaerp
DB_USERNAME=<your_pg_username>
DB_PASSWORD=<your_pg_password>

NOVA_ADMIN_PASSWORD=<choose_a_local_password>
```

### 3. Create PostgreSQL Databases

```sql
-- Connect to PostgreSQL as superuser:
CREATE DATABASE novaerp;
CREATE DATABASE novaerp_test;
```

### 4. Run Migrations and Seed

```bash
cd backend
php artisan migrate:fresh --seed
```

> The seeder creates `admin@novatech.com` with the password from `NOVA_ADMIN_PASSWORD`.

### 5. Frontend Setup

```bash
cd frontend
npm install
cp .env.example .env
```

`frontend/.env` (no secrets needed):
```ini
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

---

## Running Tests

### Backend

Configure `backend/.env.testing`:
```ini
DB_DATABASE=novaerp_test
DB_USERNAME=<your_pg_username>
DB_PASSWORD=<your_pg_password>
NOVA_ADMIN_PASSWORD=testpassword123
```

```bash
cd backend
php artisan test --env=testing
```

### Frontend

```bash
cd frontend
npx tsc --noEmit    # TypeScript type check — must pass with 0 errors
npm run build       # Production build — must succeed
npm run lint        # ESLint — must pass with 0 errors
```

---

## Running the Application

### Backend (Terminal 1)

```bash
cd backend
php artisan serve --port=8000
```

### Frontend (Terminal 2)

```bash
cd frontend
npm run dev
```

Open **http://localhost:5173** in your browser.

Sign in with:
- Email: `admin@novatech.com`
- Password: your `NOVA_ADMIN_PASSWORD` value

---

## API Reference

See `docs/API_CONTRACT.md` for all API conventions.

### Stage 0 Endpoints

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/v1/health` | None | Health check |
| POST | `/api/v1/auth/login` | None | Login |
| GET | `/api/v1/auth/me` | Bearer token | Authenticated user |
| POST | `/api/v1/auth/logout` | Bearer token | Logout |

---

## Documentation

| File | Description |
|---|---|
| `docs/PROJECT_SPEC.md` | Business context, planned modules, tech stack |
| `docs/ARCHITECTURE.md` | Frontend, backend, API, database architecture |
| `docs/DEVELOPMENT_RULES.md` | Coding standards, security rules, stage protocol |
| `docs/API_CONTRACT.md` | API conventions (versioning, envelopes, status codes) |

---

## Current Stage

**Stage 0 — Foundation** ✅

Next: **Stage 1 — Authentication completion + RBAC + Organization + Master Data**
