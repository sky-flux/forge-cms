# ForgeCMS

> A modern Laravel-based CMS running on FrankenPHP, Postgres, and Valkey.

**Languages** · [English](./README.md) · [简体中文](./README.zh-CN.md)

---

## Tech Stack

| Layer | Choice | Version |
|-------|--------|---------|
| Runtime | PHP on FrankenPHP (Caddy + Octane worker mode) | 8.5.5 / 1.12.2 |
| Framework | Laravel | 13.x |
| Database | PostgreSQL | 18.3 |
| Cache / Queue | Valkey (BSD-licensed Redis fork) | 9.0.3 |
| Search | Meilisearch | 1.13 |
| Mail (dev) | Mailpit | latest |
| Frontend | Vite (Node runtime) + Bun (package manager), host-side | — |
| Orchestration | Colima + Docker Compose v2 | — |

## Requirements

- macOS on Apple Silicon (Linux works too, paths may differ)
- **Host PHP toolchain** via [Herd Lite](https://php.new) — PHP, Composer, Laravel installer
- [Colima](https://github.com/abiosoft/colima), Docker CLI, Docker Compose v2 (services layer)
- Node.js 24.14.1 LTS (runtime for Vite)
- [Bun](https://bun.sh) (package manager)

Install on macOS:

```bash
# 1. PHP toolchain on host (Herd Lite — one-liner)
/bin/bash -c "$(curl -fsSL https://php.new/install/mac)"
source ~/.zshrc

# 2. Container services layer
brew install colima docker docker-compose docker-buildx

# 3. Frontend toolchain
volta install node@24.14.1        # pin Node 24 LTS exact version
brew install oven-sh/bun/bun      # Bun as the package manager
```

**Workflow split**: PHP/Composer/Artisan code-gen run on the host (fast); Postgres/Valkey/Meilisearch/FrankenPHP runtime live in containers. See [`docs/setup.md`](./docs/setup.md) for rationale.

## Quick Start

```bash
# 1. Boot Colima (first time)
colima start --cpu 4 --memory 8 --vm-type vz --mount-type virtiofs

# 2. Clone and initialize
git clone <repo-url> forge-cms
cd forge-cms
make setup                         # host composer install + start containers + migrate

# 3. Install frontend deps and start Vite dev server
bun install                        # generates bun.lockb
bun run dev                        # Vite dev server on :5173

# 4. Open the app
open http://forge-cms.localhost:8001
```

`make setup` handles: `.env` copy → **host** `composer install` → `docker compose up -d` → `artisan key:generate` → `artisan migrate` (in container).

## Daily Commands

```bash
# Services
make dev / make down / make restart

# Host composer (Herd Lite)
make c require <package>
make c update

# Host artisan (file generation, no service connection)
make a make:model Post -mfsc
make a make:filament-resource Post

# Container artisan (needs postgres/valkey/meilisearch)
make migrate
make ca queue:work
make ca scout:import "App\Models\Post"

# Shells
make app     # app container bash
make psql    # postgres psql
make tinker  # Laravel REPL (in container)

# Full list
make help
```

See full [`Makefile`](./Makefile) or run `make help`.

## Project Structure

```
forge-cms/
├── compose.yml              # Base orchestration
├── compose.override.yml     # Dev overlay (auto-loaded)
├── compose.prod.yml         # Prod overlay (explicit -f)
├── Makefile
├── .env.example             # Single source of truth for env config
├── deploy/                  # Container + web server configs
│   ├── Dockerfile           # Multi-stage: base → dev / prod
│   ├── Caddyfile.dev
│   ├── Caddyfile.prod
│   ├── php.dev.ini
│   └── php.prod.ini
└── docs/
    └── setup.md             # Full setup reference
```

## Production Deployment

```bash
# On the server (install Herd Lite + Colima + Bun same as local, or use CI)
git clone <repo-url> /opt/forge-cms && cd /opt/forge-cms
cp .env.example .env && vi .env    # set APP_ENV=production, APP_KEY, domain, strong passwords

# Build frontend assets first (required for prod image — host Bun)
bun install && bun run build

# Build prod image (container-side composer install --no-dev, uses committed lock file)
docker compose -f compose.yml -f compose.prod.yml build
docker compose -f compose.yml -f compose.prod.yml up -d
docker compose -f compose.yml -f compose.prod.yml exec app php artisan migrate --force
```

## Documentation

For full details on Colima tuning, Xdebug setup, Vite HMR config, UID mapping, and troubleshooting, see [`docs/setup.md`](./docs/setup.md).

## License

MIT
