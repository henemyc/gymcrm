# GymLead CRM — Lead Management System

Laravel + AJAX lead CRM for gym sales teams. Overlay these files onto your existing Laravel project (`vendor/` is not included).

Default login after seeding:

| Role  | Email                 | Password |
|-------|-----------------------|----------|
| Admin | admin@gymlead.test    | password |
| Staff | staff@gymlead.test    | password |

---

## What this system does

- Admin and staff logins with role-based access
- Lead inbox with AJAX filters, pagination, notes, and status pipeline
- Assign / reassign leads, including bulk assign
- Bulk status change, bulk delete, selected export
- CSV import and full/filtered export
- Google Maps Places scraper (official APIs only): search location → preview businesses → select → enrich contacts → assign to staff
- Google Maps API key stored encrypted and edited from **Settings** (never hardcoded)
- Duplicate blocking on Place ID, normalized phone, and name+address hash
- Daily scrape caps, max pages, radius caps, rate limits, activity log

---

## Install onto your existing Laravel app

1. Copy `app/`, `resources/`, `routes/`, `database/`, `public/assets/`, and `config/gymlead.php` into your project.
2. Merge middleware aliases from `bootstrap/app.php` (Laravel 11+) **or** from `bootstrap/laravel10-kernel-snippet.php` (Laravel 10).
3. Merge routes from `routes/web.php` (replace the default welcome route).
4. Copy `.env.example` keys into your `.env`.
5. Run:

```bash
php artisan migrate --seed
php artisan storage:link
```

6. Log in as admin → **Settings** → paste your Google Maps API key (Places API + Geocoding API enabled in Google Cloud).

See `PHASES.md` for the build map and `INSTALL.md` for a longer checklist.
