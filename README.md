<h1 align="center">
  <br>
  🏠 Toral House Dashboard
  <br>
</h1>

<p align="center">
  Real-time visitor analytics for a Second Life location — synced from Google Sheets, visualised with interactive charts.
</p>

<p align="center">
  <a href="https://github.com/niobedev/toral-house-dashboard/actions/workflows/docker.yml">
    <img src="https://github.com/niobedev/toral-house-dashboard/actions/workflows/docker.yml/badge.svg" alt="Build Status">
  </a>
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Symfony-8-000000?logo=symfony&logoColor=white" alt="Symfony 8">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
  <img src="https://img.shields.io/badge/Caddy-2-00ADD8?logo=caddy&logoColor=white" alt="Caddy 2">
  <img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
</p>

---

## ✨ Features

| | |
|---|---|
| 📊 **8+ live charts** | Leaderboard, heatmap, peak hours, daily trends, visit duration, new vs returning, scatter plot |
| 🟢 **Live Now panel** | Shows currently online visitors with per-second elapsed timers |
| 🔄 **Auto-sync** | Incremental Google Sheets sync every 60 seconds, UI refreshes automatically on new data |
| 🔍 **Visitor search** | Filter visitors by name across any time period |
| 👤 **Avatar profiles** | Per-avatar page with SL profile data (name, bio, picture) cached from the Second Life API |
| 📝 **Avatar notes** | Freeform markdown notes per avatar; author can edit or delete via modal |
| ⏰ **Avatar reminders** | Dated reminders with live countdown; any user can resolve; overdue reminders highlighted in red |
| 🔔 **Reminder navbar badge** | Clock icon in the nav shows active reminder count; turns red when any reminder is overdue |
| 🌍 **UTC-aware times** | All timestamps stored as UTC; displayed in the viewer's local timezone via `<time data-local>` |
| 🔐 **Auth** | Session-based login to keep the dashboard private |
| 🐳 **Docker-first** | Single-image production deploy; dev environment with hot reload |

## 🛠 Tech Stack

- **Backend** — Symfony 8 / PHP 8.4-fpm, Doctrine ORM, MySQL 8 window functions (`LEAD`, `ROW_NUMBER`)
- **Frontend** — Apache ECharts (ES modules), Tailwind CSS (CDN, dark mode), EasyMDE (markdown editor)
- **Markdown** — `league/commonmark` (server-side rendering, XSS-safe)
- **Data source** — Google Sheets API v4 via service account
- **Web server** — Caddy 2 bundled inside the app container (`php_fastcgi localhost:9000`)
- **Scheduler** — supervisor-managed 60 s loop inside the app container (no separate cron container)

---

## 🚀 Local Development

### Prerequisites

- Docker + Docker Compose
- A Google service account JSON key with Sheets API access

### 1 — Clone & configure

```bash
git clone https://github.com/niobedev/toral-house-dashboard.git
cd toral-house-dashboard

cp .env .env.local
```

Edit `.env.local`:

```dotenv
GOOGLE_SHEET_ID=your_spreadsheet_id_here
APP_SECRET=any_random_32_char_string
MYSQL_ROOT_PASSWORD=rootsecret
MYSQL_PASSWORD=secret

# Timezone of the timestamps in your Google Sheet (used to convert them to UTC on import)
SHEET_TIMEZONE=Europe/Kyiv
```

Place your service account key at:

```
secrets/google-sa-key.json
```

### 2 — Start

```bash
docker compose up -d
```

On first start, Composer installs dependencies automatically (~30 s). Then open **http://localhost:8080**.

### 3 — Create an admin user

```bash
docker compose exec php php bin/console app:create-user admin yourpassword
```

### 4 — Trigger an initial sync

```bash
docker compose exec php php bin/console app:sync-sheet
```

---

## 🏭 Production Deployment

The production image is a **single self-contained container** — php-fpm, Caddy (static files + FastCGI), and the sync scheduler all run together under supervisord. No volume sharing with external services required.

The image is published automatically to **GitHub Container Registry** on every push to `main`:

```
ghcr.io/niobedev/toral-house-dashboard:latest
```

### 1 — One-time server setup

Create the shared Docker network that your Caddy instance and app containers use to communicate:

```bash
docker network create proxy
```

### 2 — Create `.env.prod` on the server

```dotenv
APP_SECRET=<openssl rand -hex 32>

DATABASE_URL=mysql://app:strongpassword@mysql:3306/house_visits?serverVersion=8.0&charset=utf8mb4

GOOGLE_SHEET_ID=your_spreadsheet_id_here
GOOGLE_SHEET_RANGE=AD!A2:E
```

### 3 — Configure your Caddy instance

Add one block to your existing Caddyfile — this never needs to change again, even across app updates:

```caddyfile
yourdomain.com {
    reverse_proxy toral-house-app:80
}
```

### 4 — Deploy

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

On startup the container automatically:
1. Warms the Symfony production cache
2. Runs any pending database migrations
3. Starts **Caddy** (`:80`, serves static assets + proxies PHP to php-fpm)
4. Starts **php-fpm** (`:9000`, internal only)
5. Starts the **60 s Google Sheets sync loop**

### Updating to a new version

Pull the new image and recreate the container — external Caddy needs no restart since the container name stays the same:

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod pull
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

### Architecture

```
External Caddy (proxy network)
      │  reverse_proxy toral-house-app:80
      ▼
 ┌─────────────────────────────────┐
 │       toral-house-app           │
 │  ┌─────────┐    ┌────────────┐  │
 │  │  Caddy  │───▶│  php-fpm   │  │
 │  │  :80    │    │   :9000    │  │
 │  └─────────┘    └────────────┘  │
 │  ┌───────────────────────────┐  │
 │  │  sync loop (every 60 s)   │  │
 │  └───────────────────────────┘  │
 └─────────────────────────────────┘
      │ (internal network only)
      ▼
    MySQL
```

---

## 📁 Project Structure

```
├── docker/
│   ├── caddy/Caddyfile          # Caddy config (bundled into prod image)
│   └── php/
│       ├── Dockerfile.dev       # Dev image (xdebug, volume-mounted source)
│       ├── Dockerfile.prod      # Production image (php-fpm + Caddy + scheduler)
│       ├── entrypoint.prod.sh   # cache:warmup + migrations on startup
│       └── supervisord.conf     # manages php-fpm, Caddy, and sync loop
├── src/
│   ├── Command/SyncSheetCommand.php
│   ├── Controller/
│   │   ├── ApiController.php       # JSON endpoints for charts + reminders
│   │   ├── DashboardController.php # Main dashboard + avatar pages
│   │   ├── NoteController.php      # Create/edit/delete avatar notes
│   │   └── ReminderController.php  # Create/edit/delete/resolve avatar reminders
│   ├── Entity/
│   │   ├── AvatarNote.php
│   │   ├── AvatarProfile.php       # Cached SL profile (name, bio, picture)
│   │   └── AvatarReminder.php
│   ├── EventListener/
│   │   └── ReminderNavbarSubscriber.php  # Injects reminder counts as Twig globals
│   ├── Service/
│   │   ├── GoogleSheetsService.php
│   │   ├── SecondLifeProfileService.php  # Fetches & caches SL avatar profiles
│   │   └── SheetSyncService.php          # Converts sheet timestamps to UTC on import
│   └── Repository/
│       ├── AvatarNoteRepository.php
│       ├── AvatarProfileRepository.php
│       ├── AvatarReminderRepository.php
│       └── EventRepository.php           # All analytics queries (window functions)
├── public/assets/
│   ├── app.js                   # Poll loop, sync-aware auto-refresh
│   ├── local-time.js            # Rewrites <time data-local> to browser local timezone
│   └── charts/                  # One ES module per chart
├── .github/workflows/docker.yml # CI: builds & pushes image to ghcr.io on push to main
├── docker-compose.yml           # Dev stack
└── docker-compose.prod.yml      # Production stack (joins external "proxy" network)
```

---

## 🔧 Useful Commands

A `Makefile` is included for common tasks. Run `make` to see all targets.

| Command | Description |
|---|---|
| `make up` | Start the dev stack |
| `make down` | Stop and remove containers |
| `make logs` | Follow all container logs |
| `make logs-php` | Follow php logs only (sync output here) |
| `make shell` | Open a shell in the php container |
| `make sync` | Run an incremental sync from Google Sheets |
| `make sync-full` | Wipe all events and re-import from scratch |
| `make migrate` | Run pending database migrations |
| `make user name=admin pass=secret` | Create a login user |
| `make build` | Rebuild the dev image |
| `make cache-clear` | Clear the Symfony cache |
| `make composer-require pkg=vendor/name` | Add a Composer package |
| `make prod-build` | Build the production image locally |
| `make prod-push` | Push the production image to GHCR |
