# Copilot instructions

## Project overview
- Laravel 12 + Inertia.js (Vue 3) SPA stack. Routes return `Inertia::render('PageName', $props)` instead of `view()`. The single HTML shell lives in [resources/views/app.blade.php](resources/views/app.blade.php).
- Vue page components live in [resources/js/Pages/](resources/js/Pages/) (e.g. `Welcome.vue`). Inertia resolves them by name: `'Welcome'` → `./Pages/Welcome.vue`.
- Shared server-side data (auth user, flash messages, etc.) is exposed via Inertia's `HandleInertiaRequests` middleware and accessed in components through `usePage().props`.
- Eloquent models live in [app/Models](app/Models); the current model is `User` in [app/Models/User.php](app/Models/User.php).
- Database schema changes go through migrations in [database/migrations](database/migrations).
- Frontend assets are built with Vite + Tailwind v4 + `@vitejs/plugin-vue`; entrypoints are [resources/css/app.css](resources/css/app.css) and [resources/js/app.js](resources/js/app.js) as configured in [vite.config.js](vite.config.js).

## Developer workflows (from composer scripts)
- Initial setup: `composer run setup` (installs PHP deps, copies .env, generates key, runs migrations, installs/builds frontend assets). See [composer.json](composer.json).
- Local dev: `composer run dev` runs `php artisan serve`, queue listener, log tailing via `php artisan pail`, and Vite in parallel. See [composer.json](composer.json).
- Tests: `composer run test` wraps `php artisan test` with config clear. See [composer.json](composer.json).

## Conventions to follow
- Routes live in [routes/web.php](routes/web.php) and **always** return `Inertia::render('ComponentName', $props)`. Do not return `view()` for full-page responses.
- Page components go in [resources/js/Pages/](resources/js/Pages/). Use `<script setup>` + `defineProps()` to receive server props; do not fetch data from inside components.
- Reusable UI pieces go in `resources/js/Components/`; layouts in `resources/js/Layouts/`.
- Use the Inertia `<Link>` component (imported from `@inertiajs/vue3`) instead of `<a>` for client-side navigation.
- When adding controllers, place them under [app/Http/Controllers](app/Http/Controllers) and wire them from routes.
- When adding new tables or fields, create migrations in [database/migrations](database/migrations) and update the corresponding Eloquent model in [app/Models](app/Models).
- Tailwind scans `resources/**/*.blade.php`, `resources/**/*.js`, and `resources/**/*.vue` (see [resources/css/app.css](resources/css/app.css)).

## Integration points
- PHP dependencies are managed via Composer in [composer.json](composer.json); JS dependencies in [package.json](package.json).
- Inertia server adapter: `inertiajs/inertia-laravel` (PHP). Client adapter: `@inertiajs/vue3` (JS). Page resolution is set up in [resources/js/app.js](resources/js/app.js).
- HTTP entry point is [public/index.php](public/index.php) with framework bootstrap in [bootstrap/app.php](bootstrap/app.php).
- The Inertia root Blade template is [resources/views/app.blade.php](resources/views/app.blade.php) — it must contain `@inertia` and `@inertiaHead`.
