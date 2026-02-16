# Premium IPTV Panel

Modern IPTV portal with secure customer gating, reseller self-service, Netflix-style browsing, and admin controls. Built with PHP 8 + MySQL (PDO, prepared statements), session hardening, and hashed credentials.

## Feature Highlights
- **Customer access**: UUID + 4‑digit PIN; `includes/auth/check_auth.php` protects all user-facing pages.
- **Reseller portal**: Create customers, auto-generate UUID/PIN, manage subscriptions, share watch links (`/reseller`).
- **Admin panel**: Channels, ads, video ads, SEO/settings, and Reseller Management (`admin/resellers.php`).
- **Live TV**: Proxy support, DRM awareness, player pages with ads and info panels.
- **VOD (Phase 5 groundwork)**: Movies/series/episodes tables; detail pages (`movie.php`, `series.php`); watch-history schema for resume; My List schema; channel favorites and EPG tables.
- **UI/UX**: Shared sticky header (`pages/partials/header.php`), refreshed customer styles (`assets/css/app.css`, `assets/css/style.css`), cards/grids, buttons, glass panels.

## Auth & Sessions
- Sessions bootstrapped in `includes/auth/session.php` (secure cookie flags, regen/destroy helpers).
- Gatekeeper `includes/auth/check_auth.php` enforces active, non-expired subscriptions.
- Admin/reseller logins use `password_verify`; session regen after login.

## Database Schema (bootstrap in `includes/config.php`)
- Core: `admins`, `channels`, `categories`, `ads`, `video_ads`, `settings`.
- Resellers/customers: `resellers`, `customers` (PIN hash, UUID, subscription status/expiry).
- VOD: `movies`, `series`, `episodes`.
- Engagement: `watch_history`, `my_list`, `channel_favorites`, `epg_programs`.

## Key Routes
- Public entry router: `index.php` (routes /watch, /pages/*, /admin/*, /api/*).
- Customer: `/watch?uuid=...` (PIN entry), `/player.php` and `/pages/player.php`.
- Reseller: `/reseller/login.php`, `/reseller/index.php`, actions under `/reseller/actions/`.
- Admin: `/admin/*` (requires `requireAdminAuth()`).
- VOD detail: `/movie.php?id=…`, `/series.php?id=…`.

## Setup
1. **Requirements**: PHP 8+, MySQL 5.7/8.0+, Composer (optional), XAMPP/LAMP stack.
2. **Configure DB**: Update credentials in `includes/config.php` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
3. **Run app**: Serve repo root as web root (e.g., XAMPP `htdocs/…/IPTV Premium Panel/`). First load auto-creates tables via `createTables($pdo)`.
4. **Default admin**: username `admin`, password `admin` (change immediately via Admin > Profile).

## Flows
- **Customer sign-in**: Reseller creates customer → share `/watch?uuid=…` + PIN → customer enters PIN → gatekeeper session set → access site.
- **Subscription control**: Reseller dashboard provides +1M/+3M/+1Y and deactivate actions; gatekeeper expires sessions past `subscription_expiry_date`.
- **Continue watching** (schema ready): Persist progress into `watch_history`; player integration needed to write positions.
- **My List** (schema ready): Toggle content into `my_list`; UI/API wiring pending.
- **EPG + favorites** (schema ready): Populate `epg_programs`; use `channel_favorites` for “Favorites” tab.

## Styling & Components
- Base customer styles: `assets/css/style.css`
- Modern overlay theme: `assets/css/app.css`
- Shared header/nav: `pages/partials/header.php`
- Admin styles: `assets/css/admin.css`

## Security Notes
- All DB access uses PDO prepared statements.
- Passwords/PINs stored with `password_hash`.
- Sessions hardened with HttpOnly/SameSite, regen on login, explicit destroy on failure.
- Keep `DB_*` secrets out of VCS for production; use env/ini where possible.

## Next Steps (per Phase 5 spec)
- Hook player events to update `watch_history` (resume).
- Implement My List add/remove UI + API.
- Build homepage rows (Featured carousel, Genre rows, Continue Watching, My List).
- Add global search endpoint `/api/search.php` with AJAX suggestions + filters.
- Replace Live TV list with EPG grid + favorites tab backed by `epg_programs`/`channel_favorites`.
