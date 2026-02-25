# Copilot instructions

## Overview
- Laravel 12 + Inertia.js (Vue 3) SPA; single shell [resources/views/app.blade.php](resources/views/app.blade.php). Controllers always `Inertia::render(...)`, never `view()`.
- Stock is time-travelled and ledger-derived: “as-of date D” = Σ in − Σ out from `daily_journal_lines` with `journal_date <= D`; `current_stock` and `quantity_added` columns are vestigial display slots only.
- No auth guards. POS controllers/pages exist but are **not** routed in [routes/web.php](routes/web.php).

## Dev workflow
- Setup: `composer run setup` (installs, copies .env, migrates, npm install/build). First run also needs `php artisan storage:link`.
- Dev: `composer run dev` runs serve on 8001 + queue:listen + `npm run dev` via concurrently; stop with Ctrl+C. Tests: `composer run test` (in-memory SQLite).
- CSS: Tailwind v4 via `@import 'tailwindcss'` and `@source` in [resources/css/app.css](resources/css/app.css), processed by [postcss.config.js](postcss.config.js) using `@tailwindcss/postcss` (not the Vite plugin).
- UI tokens live in [resources/js/theme.js](resources/js/theme.js) `ui` map; prefer composing these classes over ad-hoc Tailwind strings.

## Routing and pages
- [routes/web.php](routes/web.php): Items CRUD + consume, Products CRUD, Produce create, Restock create, Units CRUD. All return `back()` responses.
- POS stack is unused by routing (see [app/Http/Controllers/PosController.php](app/Http/Controllers/PosController.php), [app/Http/Controllers/CheckoutController.php](app/Http/Controllers/CheckoutController.php), [resources/js/Pages/Pos/Index.vue](resources/js/Pages/Pos/Index.vue)).

## Ledger rules (append-only)
- Never mutate stock columns or use `increment()`, `decrement()`, `lockForUpdate()` for quantities. Only append `DailyJournalLine` rows.
- Restock: create `DailyJournal` kind `restock` + `in` lines and mirror `RestockBatch`/`RestockBatchItem` records. Codes: `RST-YYYYMMDD-NNN`; initial item stock uses `RST-INIT-YYYYMMDD-{padded_id}`.
- Produce: `DailyJournal` kind `produce`; for each ingredient batch write `out` lines (respect batch order or FIFO) then one `in` line for the product. Pre-check with `StockLedger::batchBalancesAsOf()`; on shortage throw validation errors.
- Consume: `DailyJournal` kind `consume` with `out` lines per selected batch order.
- Known anti-pattern: [app/Http/Controllers/CheckoutController.php](app/Http/Controllers/CheckoutController.php) decrements `current_stock` and writes `Sale` rows without journal entries—do not copy.

## StockLedger usage ([app/Services/StockLedger.php](app/Services/StockLedger.php))
- Pure static helpers; `linesThrough($date)` filters with `DATE(journal_date) <= $date` for SQLite/SQL Server friendliness.
- Helpers: `itemStocksAsOf`, `productStocksAsOf`, `batchBalancesAsOf`, `batchBalancesForItemAsOf` plus `hydrateItems`/`hydrateProducts` to set the vestigial stock fields on loaded models (and prune zero-balance batch lines on items).

## Date propagation / time travel
- [app/Http/Middleware/HandleInertiaRequests.php](app/Http/Middleware/HandleInertiaRequests.php) injects `currentDate` from `?date=` (default today) into every page.
- Controllers hydrate ledger values using that date; mutation forms accept optional `journal_date` and UI pages set it from `page.props.currentDate` before posting.
- Navigation preserves the date via [resources/js/Layouts/AppLayout.vue](resources/js/Layouts/AppLayout.vue) `navHref()`; [resources/js/Components/DateNavigator.vue](resources/js/Components/DateNavigator.vue) issues `router.get` with `?date=` and provides prev/next/calendar/today.

## Frontend conventions
- Always `<script setup>` with `defineProps`; no in-component fetch—props come from controllers. Use `useForm` for mutations and `<Link>` for nav; active state computed in AppLayout.
- CRUD panes toggle via a `mode` ref (`null | 'add' | id`) instead of routing—see [resources/js/Pages/Items/Index.vue](resources/js/Pages/Items/Index.vue) and [resources/js/Pages/Products/Index.vue](resources/js/Pages/Products/Index.vue). Mutations post to `/items`/`/products` and use `page.props.currentDate` for ledger dates.

## Pricing (keep PHP/JS aligned)
- Effective price: manual `selling_price` if >0; else `computed_cost * (1 + markup_percentage/100)` if markup >0; else `computed_cost`. Implemented in [app/Http/Controllers/CheckoutController.php](app/Http/Controllers/CheckoutController.php), [resources/js/Pages/Products/Index.vue](resources/js/Pages/Products/Index.vue), [resources/js/Pages/Pos/Index.vue](resources/js/Pages/Pos/Index.vue). `Products/Index` enforces exclusivity via `pricingMode` and zeroing inactive fields.

## Misc backend notes
- Images saved to `public` disk (`/storage/items/...`, `/storage/products/...`) and deleted on model removal.
- Product recipes are replace-all updates (`$product->ingredients()->delete();` then recreate). `computed_cost` is appended manually after eager loading.
- Restock listing shows batches with journals on/before the viewed date and hydrates remaining quantities via `StockLedger::batchBalancesAsOf()`.

