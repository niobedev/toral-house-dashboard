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
| 🔐 **Auth** | Session-based login to keep the dashboard private |
| 🐳 **Docker-first** | Single-image production deploy; dev environment with hot reload |

## 🛠 Tech Stack

- **Backend** — Symfony 8 / PHP 8.4-fpm, Doctrine ORM, MySQL 8 window functions (`LEAD`, `ROW_NUMBER`)
- **Frontend** — Apache ECharts (ES modules), Tailwind CSS (CDN, dark mode)
- **Data source** — Google Sheets API v4 via service account
- **Web server** — Caddy 2 (`php_fastcgi`)
- **Scheduler** — supervisor loop inside the PHP container (no separate cron)

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

The production image is published automatically to **GitHub Container Registry** on every push to `main`:

```
ghcr.io/niobedev/toral-house-dashboard:latest
```

### 1 — Create `.env.prod` on the server

```dotenv
APP_SECRET=<openssl rand -hex 32>

DATABASE_URL=mysql://app:strongpassword@mysql:3306/house_visits?serverVersion=8.0&charset=utf8mb4

GOOGLE_SHEET_ID=your_spreadsheet_id_here
GOOGLE_SHEET_RANGE=AD!A2:E
```

### 2 — Deploy

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

On startup the container automatically:
1. Warms the Symfony production cache
2. Runs any pending database migrations
3. Starts **php-fpm** + the **60 s sync loop** under supervisord

### Caddy (if already running on the host)

Skip the `caddy` service in the compose file and add a site block to your existing Caddyfile:

```caddyfile
yourdomain.com {
    root * /var/www/html/public
    encode gzip
    php_fastcgi <container-ip>:9000
    file_server
}
```

---

## 📁 Project Structure

```
├── docker/
│   ├── caddy/Caddyfile          # Web server config
│   └── php/
│       ├── Dockerfile.dev       # Dev image (xdebug, volume-mounted source)
│       ├── Dockerfile.prod      # Production image (fully self-contained)
│       ├── entrypoint.prod.sh   # cache:warmup + migrations on startup
│       └── supervisord.conf     # php-fpm + 60 s sync loop
├── src/
│   ├── Command/SyncSheetCommand.php
│   ├── Service/
│   │   ├── GoogleSheetsService.php
│   │   └── SheetSyncService.php
│   └── Repository/EventRepository.php   # All analytics queries (window functions)
├── public/assets/
│   ├── app.js                   # Poll loop, sync-aware auto-refresh
│   └── charts/                  # One ES module per chart
├── docker-compose.yml           # Dev stack
└── docker-compose.prod.yml      # Production stack
```

---

## 🔧 Useful Commands

```bash
# Run a manual sync
docker compose exec php php bin/console app:sync-sheet

# Full re-sync from scratch (wipes existing events)
docker compose exec php php bin/console app:sync-sheet --full

# Follow live logs — sync output visible here
docker compose logs php -f

# Open a shell inside the container
docker compose exec php sh
```

---

## 📄 License

MIT
