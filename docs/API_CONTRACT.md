# NovaERP — API Contract

> This document is the single source of truth for all NovaERP REST API conventions.
> All implementation stages must follow these conventions without exception.
> Do not redefine these conventions in stage-specific documentation.

---

## 1. Versioning

All API routes are prefixed with `/api/v1`.

```
https://api.novatech.com/api/v1/...
http://localhost:8000/api/v1/...   (development)
```

When a breaking change is required, a new version prefix (`/api/v2`) is introduced. Existing version routes remain supported until formally deprecated.

---

## 2. URL Conventions

| Rule | Example |
|---|---|
| Plural nouns for collections | `/api/v1/users` |
| Singular for singletons | `/api/v1/auth/me` |
| Kebab-case for multi-word segments | `/api/v1/purchase-orders` |
| Nested resources for clear ownership | `/api/v1/orders/{id}/lines` |
| No trailing slash | `/api/v1/users` not `/api/v1/users/` |
| No verbs in URLs | `/api/v1/orders` not `/api/v1/getOrders` |

---

## 3. HTTP Methods

| Method | Usage |
|---|---|
| `GET` | Read — retrieve resource(s) |
| `POST` | Create a new resource |
| `PUT` | Full replacement of a resource |
| `PATCH` | Partial update of a resource |
| `DELETE` | Remove a resource |

---

## 4. Request Conventions

All API requests must include:

```
Content-Type: application/json
Accept: application/json
```

Authenticated requests must include:

```
Authorization: Bearer <sanctum_token>
```

Request bodies are JSON. Query parameters are used for filtering, sorting, and pagination.

---

## 5. Success Response Structure

All successful responses use a consistent envelope:

```json
{
  "success": true,
  "message": "Human-readable description",
  "data": { ... }
}
```

For empty responses (e.g. DELETE):

```json
{
  "success": true,
  "message": "Resource deleted successfully",
  "data": null
}
```

---

## 6. Paginated Response Structure

```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 142,
    "last_page": 6,
    "from": 1,
    "to": 25
  },
  "links": {
    "first": "/api/v1/users?page=1",
    "last": "/api/v1/users?page=6",
    "prev": null,
    "next": "/api/v1/users?page=2"
  }
}
```

---

## 7. Error Response Structure

All error responses use a consistent envelope:

```json
{
  "success": false,
  "message": "Human-readable error description",
  "errors": null
}
```

---

## 8. Validation Error Structure (422)

Validation errors include field-level detail:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required.", "The email must be a valid email address."],
    "password": ["The password field is required."]
  }
}
```

---

## 9. Filtering

Filters are applied via query parameters using the field name:

```
GET /api/v1/users?status=active
GET /api/v1/orders?customer_id=42&status=pending
```

Range filters use `_from` / `_to` suffixes:

```
GET /api/v1/orders?created_at_from=2026-01-01&created_at_to=2026-12-31
```

---

## 10. Sorting

```
GET /api/v1/users?sort=name&direction=asc
GET /api/v1/orders?sort=created_at&direction=desc
```

| Parameter | Values | Default |
|---|---|---|
| `sort` | field name | `created_at` |
| `direction` | `asc` or `desc` | `desc` |

---

## 11. Pagination

```
GET /api/v1/users?page=2&per_page=25
```

| Parameter | Default | Maximum |
|---|---|---|
| `page` | `1` | — |
| `per_page` | `25` | `100` |

---

## 12. Authentication

NovaERP uses Laravel Sanctum token-based authentication.

To authenticate:
1. `POST /api/v1/auth/login` → receive `token`
2. Include `Authorization: Bearer <token>` in all subsequent requests

Tokens are revoked on logout: `POST /api/v1/auth/logout`

> Token persistence strategy (session vs. cookie vs. in-memory) will be finalized during the Authentication/RBAC stage.

---

## 13. HTTP Status Code Reference

| Code | Meaning | When Used |
|---|---|---|
| `200 OK` | Success | GET, PUT, PATCH, POST (non-create) |
| `201 Created` | Resource created | POST resulting in new resource |
| `204 No Content` | Success, no body | DELETE (rare — most responses include message) |
| `400 Bad Request` | Malformed request | Invalid JSON, bad parameters |
| `401 Unauthorized` | Not authenticated | Missing or invalid token |
| `403 Forbidden` | Not authorized | Authenticated but insufficient permission |
| `404 Not Found` | Resource not found | Invalid ID or route |
| `422 Unprocessable Entity` | Validation failed | Request body fails validation rules |
| `500 Internal Server Error` | Unexpected server error | Uncaught exception (details hidden in production) |

---

## 14. Stage 0 API Endpoints

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/health` | None | Service health check |
| `POST` | `/api/v1/auth/login` | None | Authenticate, receive token |
| `GET` | `/api/v1/auth/me` | Required | Return authenticated user |
| `POST` | `/api/v1/auth/logout` | Required | Revoke current token |
