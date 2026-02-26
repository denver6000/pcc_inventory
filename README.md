# PCC Inventory System

PCC Inventory System is a Laravel 12 + Inertia.js (React 18) single-page inventory app for managing:

- item master data
- product recipes
- restocking
- production
- stock consumption

The system is built around an **append-only stock ledger** and supports **as-of date** stock views.

---

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** Inertia.js + React 18 (JSX)
- **Build Tooling:** Vite 7
- **Styling:** Tailwind CSS v4 via PostCSS
- **Database for tests:** SQLite in-memory

---

## Core Concept: Ledger-Derived Stock

Stock is not treated as mutable state.

For a date $D$:

$$
	ext{stock as-of } D = \sum(\text{in lines up to } D) - \sum(\text{out lines up to } D)
$$

All stock movement is recorded in `daily_journal_lines` and read through `App\Services\StockLedger`.

> `current_stock` / `quantity_added` fields are display slots hydrated from the ledger. Do not update these directly.

---

## Available Sections

- `/` — Items
- `/products` — Products and recipe management
- `/produce` — Production runs (consume ingredients, add finished goods)
- `/restock` — Item restocking and batch creation
- `/units` — Units CRUD

Other routes such as POS/checkout/sales-history exist in code for legacy/ongoing work and are not part of the main inventory workflow.

---

## Quick Start

### 1) Install and bootstrap

```bash
composer run setup
php artisan storage:link
```

### 2) Run in development

```bash
composer run dev
```

This starts:

- Laravel server on port **8001**
- queue listener
- Vite dev server

### 3) Run tests

```bash
composer run test
```

---

## Useful Commands

### Fresh DB + default seed

```bash
php artisan migrate:fresh --seed
```

### Scenario seeders

```bash
php artisan migrate:fresh --seed --seeder="Database\Seeders\Scenarios\CarryoverScenarioSeeder"
php artisan migrate:fresh --seed --seeder="Database\Seeders\Scenarios\ShortageScenarioSeeder"
```

---

## Date-Aware (Time Travel) Behavior

- GET pages read `?date=YYYY-MM-DD` (defaults to today).
- Mutations can accept `journal_date`; otherwise current date is used.
- The selected date is shared to all Inertia pages and preserved across navigation.

This allows auditing and verifying inventory state for historical dates.

---

## Ledger Rules

1. **Append only**: create journal lines, do not mutate stock counters.
2. **Restock**: create `restock` journals with `in` lines.
3. **Produce**: create `produce` journals with ingredient `out` lines and product `in` lines.
4. **Consume**: create `consume` journals with `out` lines.
5. Wrap journal-creating operations in `DB::transaction()`.

---

## Project Structure (High Level)

- `app/Http/Controllers` — page + mutation controllers
- `app/Services/StockLedger.php` — stock computation helpers
- `resources/js/Pages` — Inertia React pages
- `routes/web.php` — web routes
- `tests/Feature` — inventory flow tests
- `tests/Unit/StockLedgerTest.php` — ledger math coverage

---

## Testing Notes

- PHPUnit 11 with in-memory SQLite.
- DB tests use `RefreshDatabase`.
- Stock assertions should use `StockLedger` helpers (`itemStockAsOf`, `batchBalancesAsOf`, etc.).
- Avoid direct writes to `current_stock` in tests.

---

## License

This project is open-sourced under the [MIT license](https://opensource.org/licenses/MIT).
