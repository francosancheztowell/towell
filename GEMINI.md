# Towell Project Context

## Project Overview
**Towell** is a comprehensive web-based production and business planning management system designed for the textile industry. Built on the Laravel framework, the application manages various production stages including planning, weaving, warping, sizing, and personnel management (tying and weavers).

**Key Features:**
- **Modular Architecture:** Dedicated modules for planning, weaving, warping, tying, maintenance, and configuration.
- **Authentication & Security:** Custom authentication supporting employee numbers/passwords and QR code logins. It features a granular, role-based permission system caching permissions for optimized performance.
- **Data Integration:** Robust support for Excel data import/export (`maatwebsite/excel`).
- **Modern Frontend:** Built as a Progressive Web App (PWA) using Vite, Tailwind CSS v4, and various UI libraries (SweetAlert2, Select2, Chart.js, Toastr).

**Primary Technologies:**
- **Backend:** PHP >= 8.2, Laravel ^12.0
- **Database:** SQL Server (`sqlsrv`)
- **Frontend:** Node.js >= 18.x, Vite ^6.0, Tailwind CSS ^4.0, jQuery, Blade Templates
- **Key PHP Packages:** `maatwebsite/excel` (Excel integrations), `dompdf/dompdf` (PDF generation), `predis/predis` (Redis integration).

## Building and Running

### Prerequisites
- PHP >= 8.2
- Composer
- Node.js >= 18.x and npm
- SQL Server (or compatible database)

### Initial Setup
1. **Install dependencies:**
   ```bash
   composer install
   npm install
   ```
2. **Environment Configuration:**
   Copy the example environment file and generate an application key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Note: Ensure your `.env` is configured for SQL Server (`DB_CONNECTION=sqlsrv`).*
3. **Run Migrations & Storage Link:**
   ```bash
   php artisan migrate
   php artisan storage:link
   ```

### Development Server
The most efficient way to run the full development environment (PHP server, queue worker, logs, and Vite asset compilation) concurrently is to use the custom Composer script:
```bash
composer dev
```
Alternatively, you can run the backend and frontend separately:
```bash
php artisan serve
npm run dev
```

### Production Build
To compile frontend assets for production:
```bash
npm run build
```

## Development Conventions

- **Architecture:** The project strictly follows Laravel's standard MVC (Model-View-Controller) pattern.
  - Models: `app/Models/` (organized into subdirectories by module like `Planeacion`, `Tejido`, `Inventario`)
  - Controllers: `app/Http/Controllers/`
  - Views: `resources/views/`
- **Helper Functions:** The project uses custom global helpers located in `app/Helpers/` (e.g., `format_helpers.php`, `permission-helpers.php`, `device_helpers.php`) which are auto-loaded via `composer.json`.
- **Code Formatting & Testing:** The codebase uses `laravel/pint` for code styling and `phpunit/phpunit` for automated tests.
- **Asset Management:** Frontend styling heavily utilizes Tailwind CSS (v4) processed through Vite. Interactivity is handled via vanilla JavaScript and jQuery alongside plugins like Select2 and SweetAlert2.
- **Permissions:** Permissions are managed granularly (access, create, modify, delete, register) and are cached for 24 hours to reduce database load. Always utilize the provided permission helpers when building new views or controller actions.
