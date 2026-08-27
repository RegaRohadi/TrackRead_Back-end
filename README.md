# 📖 TrackRead Backend (API)

REST API backend for **TrackRead** — a personal book management and reading progress tracker. Built with **Laravel 12**, **PostgreSQL**, and **Laravel Sanctum**.

---

## 🚀 Tech Stack & Architecture

- **Framework:** Laravel 12 (PHP 8.2+)
- **Database:** PostgreSQL 17
- **Authentication:** Laravel Sanctum (Bearer Token)
- **API Documentation:** Dedoc Scramble (`/docs/api`)
- **Testing:** PHPUnit (Unit & Feature tests)
- **Architecture Pattern:** Clean Layered Architecture
  - **Controllers:** Request handling & response transformations via API Resources
  - **Form Requests:** Input validation rules
  - **Services:** Business logic separation
  - **Repositories (Eloquent):** Database query abstraction
  - **Models & Migrations:** Multi-tenancy per user

---

## 🛠️ API Endpoints

### 🔐 Authentication (`/api/v1/auth`)
| Method | Endpoint | Description | Protected |
| :--- | :--- | :--- | :--- |
| `POST` | `/auth/register` | Register new user account | ❌ No |
| `POST` | `/auth/login` | Log in and receive API token | ❌ No |
| `POST` | `/auth/logout` | Revoke current user token | ✅ Yes |
| `GET` | `/auth/me` | Fetch authenticated user profile | ✅ Yes |
| `POST` | `/auth/change-password` | Update current user password | ✅ Yes |

### 📚 Book Management (`/api/v1/books`)
| Method | Endpoint | Description | Protected |
| :--- | :--- | :--- | :--- |
| `GET` | `/books` | Paginated list of books (supports `?genre=&page=&per_page=`) | ✅ Yes |
| `GET` | `/books/genres` | Unique list of user genres | ✅ Yes |
| `GET` | `/books/search` | Search books (`?q=&genre=&page=`) | ✅ Yes |
| `POST` | `/books` | Create new book (multipart/form-data with optional cover) | ✅ Yes |
| `PUT` | `/books/{id}` | Update existing book details or progress | ✅ Yes |
| `DELETE` | `/books/{id}` | Delete book from library | ✅ Yes |

---

## 🐳 Running with Docker (Recommended)

1. **Copy Environment File:**
   ```bash
   cp .env.example .env
   ```
   *Ensure database settings in `.env` match Docker:*
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=postgres
   DB_PORT=5432
   DB_DATABASE=trackread
   DB_USERNAME=trackread
   DB_PASSWORD=trackread123
   ```

2. **Build and Start Containers:**
   ```bash
   docker compose up -d --build
   ```

3. **Install Dependencies & Setup Laravel:**
   ```bash
   docker compose exec php composer install
   docker compose exec php php artisan key:generate
   docker compose exec php php artisan migrate
   docker compose exec php php artisan storage:link
   ```

4. **Access the API:**
   - Base URL: `http://localhost:8000/api/v1`
   - Interactive API Docs: `http://localhost:8000/docs/api`

---

## 💻 Running Locally (Without Docker)

### Prerequisites
- PHP 8.2+ with `pdo_pgsql`, `mbstring`, `gd` extensions
- Composer 2.x
- PostgreSQL server running locally

### Steps
```bash
# 1. Install dependencies
composer install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Configure .env with your local PostgreSQL credentials, then run:
php artisan migrate
php artisan storage:link

# 4. Start local development server
php artisan serve
```

---

## 🧪 Testing

Run PHPUnit tests:
```bash
# In Docker:
docker compose exec php php artisan test

# Locally:
php artisan test
```

---

## 📄 License
This project is open-source under the [MIT License](LICENSE).
