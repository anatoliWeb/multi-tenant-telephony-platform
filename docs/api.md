# API Documentation

## 1. Overview

This project uses an API-first architecture: Laravel provides a JSON REST API, and the Angular dashboard plus Vue admin consume it as separate clients.

All protected endpoints use token-based access (Sanctum Bearer tokens).

## 2. Authentication

Authenticate with email/password and use the returned token in every protected request.

Authorization header format:

```http
Authorization: Bearer YOUR_TOKEN
```

Token issue endpoints:
- `POST /api/token`
- `POST /api/login` (alias of `/api/token`)

## 3. Base URL

Default example:

```text
http://localhost/api
```

Docker setup in this project commonly exposes backend via:

```text
http://localhost:8080/api
```

## 4. Headers

Use these headers for JSON API requests:

```http
Content-Type: application/json
Accept: application/json
```

## 5. Endpoints

### Auth

#### POST /api/token
Description: Issue API token by credentials.

Permissions: Public

Request body:

```json
{
  "email": "admin@test.com",
  "password": "password"
}
```

Response:

```json
{
  "token": "1|long-sanctum-token"
}
```

#### POST /api/login
Description: Alias of `POST /api/token`.

Permissions: Public

Request/response: same as `/api/token`.

---

### Users

#### GET /api/users
Description: Returns list of users for admin table.

Permissions: `users.view`

Response:

```json
{
  "data": [
    {
      "id": 1,
      "name": "John",
      "email": "john@example.com",
      "roles": ["admin"],
      "permissions": ["users.view", "users.edit"],
      "denied_permissions": ["users.delete"]
    }
  ]
}
```

#### POST /api/users
Description: Create a new user with roles and optional direct permissions.

Permissions: `users.create`

Request body:

```json
{
  "name": "Jane",
  "email": "jane@example.com",
  "password": "secret123",
  "roles": [1],
  "permissions": ["users.view", "users.edit"],
  "denied_permissions": ["users.delete"]
}
```

Response (`201 Created`):

```json
{
  "data": {
    "id": 15,
    "name": "Jane",
    "email": "jane@example.com",
    "roles": ["admin"],
    "permissions": ["users.view", "users.edit"],
    "denied_permissions": ["users.delete"]
  }
}
```

#### GET /api/users/{id}
Description: Returns single user details.

Permissions: `users.view`

Response:

```json
{
  "data": {
    "id": 15,
    "name": "Jane",
    "email": "jane@example.com",
    "roles": ["admin"],
    "permissions": ["users.view", "users.edit"],
    "denied_permissions": ["users.delete"]
  }
}
```

#### PUT /api/users/{id}
Description: Update user profile, roles, and direct permissions.

Permissions: `users.edit`

Request body:

```json
{
  "name": "Jane Updated",
  "email": "jane.updated@example.com",
  "password": "optional-new-password",
  "roles": [2],
  "permissions": ["users.view"],
  "denied_permissions": ["users.edit"]
}
```

Response:

```json
{
  "data": {
    "id": 15,
    "name": "Jane Updated",
    "email": "jane.updated@example.com",
    "roles": ["manager"],
    "permissions": ["users.view"],
    "denied_permissions": ["users.edit"]
  }
}
```

#### DELETE /api/users/{id}
Description: Delete user.

Permissions: `users.delete`

Response:

```json
{
  "data": {
    "deleted": true
  }
}
```

---

### Stats

#### GET /api/stats
Description: Returns dashboard metrics and recent activity.

Permissions: Authenticated user (Sanctum)

Response:

```json
{
  "data": {
    "users": 15,
    "roles": 3,
    "permissions": 4,
    "activity_logs": 24,
    "admins": 2,
    "managers": 4,
    "tokens": 10,
    "users_with_direct_permissions": 5,
    "recent_activity": []
  }
}
```

---

### Meta

#### GET /api/meta
Description: Returns metadata for frontend forms and permission-aware UI.

Permissions: Authenticated user (Sanctum)

Frontend usage:
- roles for user form role selector
- permissions for direct permission editor
- `current_user_permissions` for conditional UI actions

Response:

```json
{
  "data": {
    "roles": [{ "id": 1, "name": "admin" }],
    "permissions": [{ "id": 1, "name": "users.view" }],
    "current_user": {
      "id": 1,
      "name": "Admin",
      "email": "admin@test.com",
      "roles": [{ "id": 1, "name": "admin" }]
    },
    "current_user_permissions": ["users.view", "users.edit"]
  }
}
```

## 6. Error Handling

### Validation error (`422 Unprocessable Entity`)

```json
{
  "message": "Validation error",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

### Common API errors

- `401 Unauthorized`: missing or invalid token, or invalid login credentials.
- `403 Forbidden`: authenticated but missing required permission.
- `404 Not Found`: resource does not exist.
- `500 Internal Server Error`: unexpected backend failure.

## 7. Examples

### Example: list users

```bash
curl -X GET "http://localhost:8080/api/users" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Example: create user

```bash
curl -X POST "http://localhost:8080/api/users" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name":"Jane",
    "email":"jane@example.com",
    "password":"secret123",
    "roles":[1],
    "permissions":["users.view"]
  }'
```

---

<!-- WHY:
Improves developer navigation and onboarding experience.
-->
## Related Documentation

- [Architecture](./architecture.md)
- [API](./api.md)
- [Commands](./commands.md)
- [Coding Standards](./coding-standards.md)
- [Main Docs](./README.md)
