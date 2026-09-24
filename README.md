# Customer CRUD Application

A simple CRUD application for managing customer records, built with Laravel, MySQL, and Elasticsearch, fully containerized with Docker.

## Tech Stack

- **Backend:** Laravel 12 (PHP 8.2)
- **Frontend:** Angular (coming soon)
- **Database:** MySQL 8.0
- **Search:** Elasticsearch 7.17
- **Web Server:** nginx (reverse proxy / load balancer)
- **Containerization:** Docker Compose

## Architecture

```
Browser → nginx (controller) → Laravel (api) → MySQL (database)
                                     ↓
                              Elasticsearch (searcher)
```

## Prerequisites

- Docker Desktop installed and running

## Setup Instructions

1. Clone this repository:
```bash
   git clone https://github.com/YOUR-USERNAME/customer-crud.git
   cd customer-crud
```

2. Copy the example environment file:
```bash
   cp backend/.env.example backend/.env
```

3. Update `backend/.env` with the following database settings:
```
   DB_CONNECTION=mysql
   DB_HOST=database
   DB_PORT=3306
   DB_DATABASE=customer_crud
   DB_USERNAME=crud_user
   DB_PASSWORD=crud_password
```

4. Build and start the containers:
```bash
   docker compose up -d --build
```

5. Install PHP dependencies (run inside the api container to match its PHP version):
```bash
   docker compose run --rm --entrypoint composer api install --prefer-dist
```

6. Generate the application key:
```bash
   docker compose exec api php artisan key:generate
```

7. Run database migrations:
```bash
   docker compose exec api php artisan migrate
```

8. Visit the application at [http://localhost:8080](http://localhost:8080)

## Services

| Service | Description | Port |
|---|---|---|
| controller | nginx reverse proxy | 8080 |
| api | Laravel backend | (internal 9000) |
| database | MySQL | 3306 |
| searcher | Elasticsearch | 9200 |