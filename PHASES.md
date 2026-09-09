# Implementation phases

## Phase 1 — Foundation
Auth, roles (admin/staff), layout, dashboard, encrypted settings store, activity log.

## Phase 2 — Users
Admin can create/edit/disable staff, set per-user lead caps, reset passwords.

## Phase 3 — Leads core
CRUD, AJAX table, status pipeline, notes, follow-up date, ownership rules.

## Phase 4 — Assignment & bulk
Assign/reassign, bulk assign/status/delete/export, staff only sees own leads.

## Phase 5 — Import / export
CSV import with column mapping + duplicate skip report. CSV export (all / filtered / selected).

## Phase 6 — Google Maps scraper
Admin searches location (server-side autocomplete), keyword, radius, type.
Places Nearby / Text Search → preview → select → Place Details enrich → save + assign.
API key from Settings. Caps, throttling, duplicate skip.

## Phase 7 — Staff panel
My Leads, update status/notes/follow-up, no scraper/users/settings access.

## Phase 8 — Hardening
CSRF, middleware, mass-assignment guards, encrypted API key, rate limits,
duplicate indexes, audit trail, XSS escaping, pagination limits.
