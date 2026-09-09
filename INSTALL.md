# GymLead CRM — install onto an existing Laravel project

Your `vendor/` folder is already present. Do **not** replace it.

## 1. Copy these folders/files

```
app/                  → your app/
bootstrap/app.php     → merge middleware aliases (Laravel 11+)
config/gymlead.php    → config/gymlead.php
database/migrations/  → database/migrations/
database/seeders/     → merge DatabaseSeeder
public/assets/        → public/assets/
resources/views/      → resources/views/
routes/web.php        → replace or merge (this app uses a single web.php)
storage/app/imports/
storage/app/exports/
```

## 2. Environment

Add to `.env`:

```
APP_NAME="GymLead CRM"
SESSION_DRIVER=database
```

Google API key is **not** an env hardcoded secret for the scraper. Admins set it in **Settings**. Optional fallback:

```
GOOGLE_MAPS_API_KEY=
```

## 3. Laravel 11 / 12 middleware

In `bootstrap/app.php` inside `withMiddleware`:

```php
$middleware->alias([
    'role' => \App\Http\Middleware\EnsureRole::class,
]);
```

## 4. Laravel 10 middleware

See `bootstrap/laravel10-kernel-snippet.php`. Register `role` alias in `app/Http/Kernel.php`.

## 5. Migrate

```bash
php artisan migrate
php artisan db:seed --class=GymLeadSeeder
php artisan storage:link
```

## 6. Google Cloud APIs to enable

- Places API (legacy Places API is used: Nearby Search, Text Search, Place Details, Autocomplete)
- Geocoding API

Restrict the key by IP (server) for web-service calls.

## 7. Permissions

```
storage/app/imports
storage/app/exports
storage/logs
```

must be writable.
