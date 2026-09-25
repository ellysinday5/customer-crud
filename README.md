# Customer CRUD Application

A full-stack customer management application built with **Laravel 12**, **Angular**, **MySQL 8**, and **Elasticsearch 7**, fully containerised with Docker Compose.

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | Laravel 12 (PHP 8.2) |
| **Frontend** | Angular (latest, standalone components) |
| **UI Framework** | Bootstrap 5 |
| **Database** | MySQL 8.0 |
| **Search** | Elasticsearch 7.17 (no Laravel Scout) |
| **Web Server** | nginx (reverse proxy) |
| **Containerisation** | Docker Compose |

## Architecture

```
Browser (Angular @ :4200)
        │
        ▼
nginx controller (:8080)
        │
        ▼
Laravel API (PHP-FPM @ :9000)
        │                └────────────────────────┐
        ▼                                         ▼
MySQL database (:3306)            Elasticsearch searcher (:9200)
```

> **Search flow**: All `GET /api/customers?search=<query>` requests hit Elasticsearch first (fuzzy multi-field match across `first_name`, `last_name`, `email`, and `contact_number`). If the `searcher` container is unreachable, the API automatically falls back to a MySQL `LIKE` query.

> **Sync flow**: Every `POST`, `PUT`, and `DELETE` to `/api/customers` mirrors the change to Elasticsearch using Laravel's native HTTP client — **no Laravel Scout, no third-party SDK**.

## Docker Services

| Service name | Image | Description | Port |
|---|---|---|---|
| `controller` | nginx:alpine | Reverse proxy / load balancer | **8080** |
| `api` | Custom PHP 8.2-FPM | Laravel backend | 9000 (internal) |
| `database` | mysql:8.0 | Relational data store | 3306 |
| `searcher` | elasticsearch:7.17.13 | Full-text search engine | 9200 |

## Prerequisites

- **Docker Desktop** installed and running

## Setup Instructions

### 1. Clone the repository

```bash
git clone https://github.com/YOUR-USERNAME/customer-crud.git
cd customer-crud
```

### 2. Copy and configure environment variables

```bash
cp backend/.env.example backend/.env
```

The example file already includes the correct values for the Docker network. No changes are needed unless you want to override defaults:

```dotenv
DB_CONNECTION=mysql
DB_HOST=database
DB_PORT=3306
DB_DATABASE=customer_crud
DB_USERNAME=crud_user
DB_PASSWORD=crud_password

ELASTICSEARCH_URL=http://searcher:9200
ELASTICSEARCH_INDEX=customers
```

### 3. Build and start all containers

```bash
docker compose up -d --build
```

### 4. Install PHP dependencies

```bash
docker compose run --rm --entrypoint composer api install --prefer-dist
```

### 5. Generate the application key

```bash
docker compose exec api php artisan key:generate
```

### 6. Run database migrations

```bash
docker compose exec api php artisan migrate
```

### 7. Start the Angular frontend

```bash
cd frontend
npm install
npm start
```

The Angular dev server runs on **http://localhost:4200** by default.

## API Endpoints

All endpoints are proxied through nginx on port `8080`.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/customers` | List all customers |
| `GET` | `/api/customers?search=<query>` | Search by name, email, or contact number (Elasticsearch-powered) |
| `POST` | `/api/customers` | Create a new customer |
| `GET` | `/api/customers/{id}` | View a single customer |
| `PUT` | `/api/customers/{id}` | Update a customer |
| `DELETE` | `/api/customers/{id}` | Delete a customer |

### Customer Payload

```json
{
  "first_name": "Juan",
  "last_name": "Dela Cruz",
  "email": "juan@example.com",
  "contact_number": "09171234567"
}
```

All fields are **required**. `email` must be a valid email address and **unique** across all customers.

## Running Tests

Tests use an **in-memory SQLite** database and mock the Elasticsearch service — no running containers required.

```bash
# Run all tests (from the backend directory, or inside the api container)
docker compose exec api php artisan test

# Or using vendor/phpunit directly
docker compose exec api ./vendor/bin/phpunit
```

Test coverage includes:

- **Feature tests** (`tests/Feature/CustomerApiTest.php`): full CRUD lifecycle, input validation, email uniqueness, and search fallback behaviour.
- **Unit tests** (`tests/Unit/ElasticsearchServiceTest.php`): HTTP request assertions for index, delete, search, availability check, and graceful connection-error handling.

## Elasticsearch Notes

- The `searcher` container may take **30–60 seconds** to become ready after `docker compose up`. The API handles this gracefully by falling back to MySQL search until Elasticsearch is healthy.
- To verify the searcher is up: `curl http://localhost:9200/_cluster/health`
- Customer documents are indexed automatically on every create/update and removed on every delete.