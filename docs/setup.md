# macOS PHP 开发环境搭建指南

基于 **Colima + Docker Compose** 搭建现代化 Laravel/PHP 开发环境。本地零污染,开发/生产配置分离。

## 技术栈

| 组件 | 版本 | 说明 |
|------|------|------|
| Colima | latest | 轻量 Linux VM,替代 Docker Desktop |
| Docker CE | latest | 容器运行时 |
| Docker Compose | v2 | 编排工具 |
| FrankenPHP | 1.12.2-bookworm | PHP 应用服务器 |
| PHP | 8.5.5 | 运行时 |
| PostgreSQL | 18.3 | 主数据库 |
| Valkey | 9.0.3 | 缓存/队列(Redis 兼容) |
| Mailpit | latest | 本地邮件捕获 |
| Meilisearch | 1.13 | 搜索引擎(可选) |

## 设计目标

- **服务零污染**:Postgres/Valkey/Meilisearch/FrankenPHP runtime **全部容器化**,Mac 本地不装服务进程
- **PHP CLI 本机化**:PHP/Composer/Laravel installer 通过 **[Herd Lite](https://php.new)** 装在宿主机,用于依赖求解、代码生成、IDE 集成;**运行时仍是容器里的 PHP 8.5.5**
- **团队一致**:配置文件进 Git,新人装 Herd Lite + Colima 两条命令即可开跑
- **开发生产分离**:`compose.yml` 共用,`override` 开发,`prod` 生产
- **端口避让**:开发端口默认 `APP_PORT=8080`(HTTP),dev 关 HTTPS(`auto_https off`);生产才用 80/443
- **性能优先**:Apple Silicon 下用 `vz` + `virtiofs`,接近原生

### 为什么 PHP CLI 在宿主机

| 维度 | host(Herd Lite) | container(容器内) |
|------|-----------------|--------------------|
| 跑 `composer require` | ✅ 快(100ms 级启动) | ❌ 慢(`docker compose exec` 有 200-300ms 开销) |
| IDE 集成(intelephense/PhpStorm) | ✅ 直接 | ❌ 需 Path Mapping |
| `php artisan make:*`(代码生成) | ✅ 秒回 | ❌ 反复 exec |
| 连 postgres/valkey 服务 | ❌ 要暴露端口,容器网络不通 | ✅ 容器内 DNS 直连 |
| `php artisan migrate` 等需服务的 | ❌ | ✅ |
| 运行时应用(FrankenPHP) | ❌ | ✅ |

结论:**工具在 host,服务和 runtime 在 container**。两者通过 bind-mounted 源码 + `composer.lock` 共享状态。

---

## 一、前置准备

### 1.0 安装宿主机 PHP 工具链(Herd Lite)

**一条命令装齐 PHP + Composer + Laravel 安装器**:

```bash
/bin/bash -c "$(curl -fsSL https://php.new/install/mac)"
source ~/.zshrc    # 刷新 PATH
```

验证:

```bash
php --version        # 输出 Herd Lite 打包的 PHP 版本(≥ 8.3)
composer --version
laravel --version
```

说明:

- [php.new](https://php.new) 是 Laravel 官方团队的一键安装脚本,免装 Homebrew 也能用
- 装到 `~/.config/herd-lite/bin`,不污染系统路径
- 需要**付费版 Herd**(Nginx/DnsMasq/MySQL 集成)再考虑,开发阶段 Lite 足够

### 1.1 安装 Colima 和 Docker CLI(服务层)

```bash
brew install colima docker docker-compose docker-buildx
```

说明:

- `colima` — Linux VM 运行时
- `docker` — Docker 命令行客户端(不是 Docker Desktop)
- `docker-compose` — Compose v2 插件
- `docker-buildx` — 多平台构建插件

### 1.2 配置 Docker CLI 插件

让 Docker CLI 能找到 Homebrew 装的插件:

```bash
mkdir -p ~/.docker/cli-plugins
ln -sfn $(brew --prefix)/opt/docker-buildx/bin/docker-buildx ~/.docker/cli-plugins/docker-buildx
ln -sfn $(brew --prefix)/opt/docker-compose/bin/docker-compose ~/.docker/cli-plugins/docker-compose
```

验证:

```bash
docker buildx version
docker compose version
```

### 1.3 启动 Colima

**首次启动**(Apple Silicon 推荐):

```bash
colima start \
  --cpu 4 \
  --memory 8 \
  --vm-type vz \
  --mount-type virtiofs
```

关键参数:

| 参数 | 值 | 说明 |
|------|-----|------|
| `--cpu` | 4 | CPU 核心数 |
| `--memory` | 8 | 内存 GB |
| `--vm-type` | vz | 用 Apple Virtualization.framework,比 QEMU 快 |
| `--mount-type` | virtiofs | 挂载性能最好 |

> **关于 `--disk` 参数**:磁盘大小**只能在首次创建 VM 时指定**,后续无法缩小,只能增大。如果已经创建过更大容量,`--disk` 参数会被忽略并警告,正常现象不影响使用。

### 1.4 固化默认配置(可选)

```bash
colima start --edit
```

编辑器打开配置文件,确认或修改:

```yaml
cpu: 4
memory: 8
vmType: vz
mountType: virtiofs
```

保存退出后,下次直接 `colima start` 即可,不用带参数。

### 1.5 验证环境

```bash
colima status
# INFO[0000] colima is running using macOS Virtualization.Framework
# INFO[0000] arch: aarch64
# INFO[0000] runtime: docker
# INFO[0000] mountType: virtiofs

docker info
docker compose version
```

### 1.6 日常启停

```bash
colima start    # 启动
colima stop     # 停止(保留数据)
colima status   # 查状态
colima restart  # 重启
colima delete   # 完全删除(丢失所有 Docker 数据,慎用)
```

---

## 二、项目结构

```
forge-cms/
├── compose.yml                 # 基础编排(开发生产共用)
├── compose.override.yml        # 开发 overlay(自动加载)
├── compose.prod.yml            # 生产 overlay(显式 -f 指定)
├── Makefile                    # 日常命令入口
├── .env                        # 本地环境变量(gitignored)
├── .env.example                # 环境变量模板
├── .dockerignore
├── .gitignore
├── deploy/                     # 部署辅助资产(容器配置)
│   ├── Dockerfile              # 多阶段:base → dev / prod
│   ├── Caddyfile.dev
│   ├── Caddyfile.prod
│   ├── php.dev.ini
│   └── php.prod.ini
├── docs/                       # 产品与技术文档
│   ├── prd.md                  # 产品需求
│   ├── story.md                # 用户故事
│   ├── database.md             # 数据模型
│   ├── laravel.md              # Laravel 开发规范
│   └── setup.md                # 本文件
│
├── app/                        # ── Laravel 应用代码 ──
│   ├── Filament/               # 后台资源(Filament 自动扫描)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Web/            # Inertia 前台控制器
│   │   ├── Middleware/
│   │   └── Requests/           # FormRequest 校验类
│   ├── Models/
│   ├── Policies/               # 授权策略
│   └── Providers/
├── bootstrap/
│   └── app.php                 # Laravel 11+ 的应用入口(路由/中间件/异常在这配)
├── config/                     # 各包配置(app.php / database.php / filament.php ...)
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── public/                     # Web 入口 + 静态资源
│   ├── index.php
│   └── build/                  # Vite 产物(gitignored)
├── resources/
│   ├── js/
│   │   ├── app.tsx             # Inertia 客户端入口
│   │   ├── ssr.tsx             # SSR 入口(Node 进程)
│   │   ├── Pages/              # Inertia 页面组件(映射 Controller)
│   │   ├── Components/         # 业务组件
│   │   ├── components/ui/      # shadcn/ui copy-in
│   │   ├── Layouts/
│   │   └── lib/utils.ts        # shadcn 标配
│   ├── css/app.css             # Tailwind 4 入口
│   ├── views/
│   │   └── app.blade.php       # 唯一 Blade:Inertia 挂载点
│   └── lang/
│       ├── zh_CN/
│       └── en/
├── routes/
│   ├── web.php                 # 前台路由
│   ├── console.php             # 调度任务 + Artisan 命令
│   └── auth.php                # Fortify 认证路由
├── storage/                    # 日志/缓存/session(gitignored 内部)
├── tests/                      # Pest 测试
│   ├── Feature/
│   └── Unit/
├── vendor/                     # Composer 依赖(gitignored)
├── node_modules/               # 前端依赖(gitignored)
│
├── artisan                     # Laravel CLI 入口
├── composer.json               # PHP 依赖声明
├── composer.lock               # PHP 依赖锁(进 Git)
├── package.json                # JS 依赖声明(含 volta pin)
├── bun.lock                    # JS 依赖锁(进 Git)
├── vite.config.ts              # Vite 配置
├── tsconfig.json               # TypeScript 配置
├── phpunit.xml                 # 测试框架配置(Pest 也读)
├── pint.json                   # Laravel Pint 代码风格
├── eslint.config.js            # JS/TS lint 规则
├── components.json             # shadcn/ui 配置
├── .editorconfig               # 跨 IDE 代码风格
├── .prettierrc                 # Prettier 格式化配置
└── README.md / README.zh-CN.md
```

**目录分层思路**:

- 三个 `compose.*.yml` 同级在根目录 —— 它们是同一套编排的不同 profile,命令行用 `-f` 切换
- `deploy/` 只放**部署辅助资产**:Dockerfile、容器内配置(Caddy/PHP),未来可加 deploy/backup 脚本
- 编排文件(compose)**不**进 `deploy/`,因为它描述运行时拓扑,开发/生产共用
- `docs/` 和 `app/` 等 Laravel 目录并列,文档不藏在某个源码子目录下
- 前台 React 代码在 `resources/js/Pages/` (Inertia 约定),不另建 `web/` 目录

---

## 三、配置文件

### 3.1 `compose.yml`(基础)

```yaml
name: ${COMPOSE_PROJECT_NAME:-forge-cms}

services:
  app:
    build:
      context: .
      dockerfile: deploy/Dockerfile
      args:
        PHP_VERSION: ${PHP_VERSION:-8.5.5}
        FRANKENPHP_VERSION: ${FRANKENPHP_VERSION:-1.12.2}
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
      valkey:
        condition: service_healthy
    networks:
      - app

  postgres:
    image: postgres:18.3-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE}
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
      PGDATA: /var/lib/postgresql/data/pgdata
    volumes:
      - postgres-data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME} -d ${DB_DATABASE}"]
      interval: 5s
      timeout: 5s
      retries: 10
    networks:
      - app

  valkey:
    image: valkey/valkey:9.0.3-alpine3.23
    restart: unless-stopped
    command: >
      valkey-server
      --save 60 1
      --appendonly yes
      --maxmemory 512mb
      --maxmemory-policy allkeys-lru
      --requirepass ${REDIS_PASSWORD}
    volumes:
      - valkey-data:/data
    healthcheck:
      test: ["CMD", "valkey-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 5s
      timeout: 3s
      retries: 5
    networks:
      - app

networks:
  app:
    driver: bridge

volumes:
  postgres-data:
  valkey-data:
```

### 3.2 `compose.override.yml`(开发,自动加载)

```yaml
services:
  app:
    build:
      target: dev
    ports:
      - "${APP_PORT:-8080}:80"
      # Dev 关闭 HTTPS（Caddyfile.dev 里 auto_https off,站点绑 :80),443 不映射
    volumes:
      - .:/app:cached
      - caddy-data:/data
      - caddy-config:/config
    environment:
      SERVER_NAME: ${SERVER_NAME:-forge-cms.localhost}
      APP_ENV: local
      APP_DEBUG: "true"
    extra_hosts:
      - "host.docker.internal:host-gateway"

  postgres:
    ports:
      - "${POSTGRES_PORT:-5432}:5432"

  valkey:
    ports:
      - "${VALKEY_PORT:-6379}:6379"

  mailpit:
    image: axllent/mailpit:latest
    restart: unless-stopped
    ports:
      - "${MAILPIT_SMTP_PORT:-1025}:1025"
      - "${MAILPIT_WEB_PORT:-8025}:8025"
    environment:
      MP_MAX_MESSAGES: 5000
      MP_DATABASE: /data/mailpit.db
    volumes:
      - mailpit-data:/data
    networks:
      - app

  meilisearch:
    image: getmeili/meilisearch:v1.13
    restart: unless-stopped
    ports:
      - "${MEILISEARCH_PORT:-7700}:7700"
    environment:
      MEILI_MASTER_KEY: ${MEILISEARCH_KEY:-masterKey}
      MEILI_ENV: development
    volumes:
      - meilisearch-data:/meili_data
    networks:
      - app

volumes:
  caddy-data:
  caddy-config:
  mailpit-data:
  meilisearch-data:
```

**端口说明**:

- 应用 HTTP:**8080**(不是 80,避免占用);dev 只走 HTTP,不起 HTTPS
- Postgres:**5432**
- Valkey:**6379**
- Mailpit SMTP:**1025**,Web UI:**8025**
- Meilisearch:**7700**

端口冲突时改 `.env` 里的环境变量即可。

### 3.3 `compose.prod.yml`(生产,独立)

```yaml
name: ${COMPOSE_PROJECT_NAME:-forge-cms}

services:
  app:
    build:
      context: .
      dockerfile: deploy/Dockerfile
      target: prod
      args:
        PHP_VERSION: ${PHP_VERSION:-8.5.5}
        FRANKENPHP_VERSION: ${FRANKENPHP_VERSION:-1.12.2}
    restart: always
    ports:
      - "80:80"
      - "443:443"
      - "443:443/udp"
    volumes:
      - caddy-data:/data
      - caddy-config:/config
      - app-storage:/app/storage/app
    environment:
      SERVER_NAME: ${APP_DOMAIN}
      APP_ENV: production
      APP_DEBUG: "false"
    depends_on:
      postgres:
        condition: service_healthy
      valkey:
        condition: service_healthy
    command:
      - "php"
      - "artisan"
      - "octane:frankenphp"
      - "--host=0.0.0.0"
      - "--port=80"
      - "--workers=auto"
      - "--max-requests=500"
    networks:
      - app
    deploy:
      resources:
        limits:
          memory: 2G

  postgres:
    image: postgres:18.3-alpine
    restart: always
    environment:
      POSTGRES_DB: ${DB_DATABASE}
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
      PGDATA: /var/lib/postgresql/data/pgdata
    volumes:
      - postgres-data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME} -d ${DB_DATABASE}"]
      interval: 5s
      timeout: 5s
      retries: 10
    networks:
      - app
    deploy:
      resources:
        limits:
          memory: 4G

  valkey:
    image: valkey/valkey:9.0.3-alpine3.23
    restart: always
    command: >
      valkey-server
      --save 60 1
      --appendonly yes
      --maxmemory 512mb
      --maxmemory-policy allkeys-lru
      --requirepass ${REDIS_PASSWORD}
    volumes:
      - valkey-data:/data
    healthcheck:
      test: ["CMD", "valkey-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 5s
      timeout: 3s
      retries: 5
    networks:
      - app
    deploy:
      resources:
        limits:
          memory: 1G

  queue:
    build:
      context: .
      dockerfile: deploy/Dockerfile
      target: prod
    restart: always
    depends_on:
      - postgres
      - valkey
    environment:
      APP_ENV: production
    command: ["php", "artisan", "horizon"]
    networks:
      - app
    deploy:
      resources:
        limits:
          memory: 1G

  scheduler:
    build:
      context: .
      dockerfile: deploy/Dockerfile
      target: prod
    restart: always
    depends_on:
      - postgres
      - valkey
    environment:
      APP_ENV: production
    command: ["php", "artisan", "schedule:work"]
    networks:
      - app
    deploy:
      resources:
        limits:
          memory: 256M

networks:
  app:
    driver: bridge

volumes:
  postgres-data:
  valkey-data:
  caddy-data:
  caddy-config:
  app-storage:
```

### 3.4 `deploy/Dockerfile`

三阶段构建:`base`(共用)→ `dev` / `prod`。**无 `frontend-builder` stage** —— 前端由宿主机 `bun run build` 产出 `public/build/`,直接随 `COPY . .` 进 prod 镜像。

```dockerfile
ARG PHP_VERSION=8.5.5
ARG FRANKENPHP_VERSION=1.12.2

# ============================================
# Stage 1: base
# ============================================
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-bookworm AS base

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        curl \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        redis \
        intl \
        zip \
        gd \
        bcmath \
        opcache \
        pcntl \
        exif \
        sockets

COPY --from=composer:2.9.7 /usr/bin/composer /usr/bin/composer

RUN groupadd --system --gid 1000 app \
    && useradd --system --uid 1000 --gid app --home /app --shell /bin/bash app \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R app:app /app /data /config

# ============================================
# Stage 2: dev
# ============================================
FROM base AS dev

RUN install-php-extensions xdebug

COPY deploy/php.dev.ini /usr/local/etc/php/conf.d/app.ini
COPY deploy/Caddyfile.dev /etc/caddy/Caddyfile

USER app

# ============================================
# Stage 3: prod
# 前端资产须在宿主机 `bun run build` 后再进入 build context
# ============================================
FROM base AS prod

COPY deploy/php.prod.ini /usr/local/etc/php/conf.d/app.ini
COPY deploy/Caddyfile.prod /etc/caddy/Caddyfile

COPY --chown=app:app composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY --chown=app:app . .

RUN composer dump-autoload --optimize --classmap-authoritative \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache \
    && chown -R app:app storage bootstrap/cache

USER app

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=80", "--workers=auto"]
```

**关键设计说明**:

- `composer:2.9.7` 固定版本,保证构建可复现;升级时修改此处一行即可追溯
- 容器内用户 `app`(UID 1000,GID 1000),框架中立 —— 不叫 `laravel` 方便日后加别的服务
- 前端无容器化:生产部署前必须先 `bun run build`,否则镜像里 `public/build/` 为空
- `dev` 装 Xdebug 但 `xdebug.mode=off`(默认),性能零损失,需要时 `make xdebug-on` 切换
- `prod` 运行 `config:cache` / `route:cache` / `view:cache` / `event:cache` 预热,首请求响应即是热路径

### 3.5 `deploy/Caddyfile.dev`

```
{
    frankenphp
    order php_server before file_server
    admin off
}

{$SERVER_NAME} {
    root /app/public
    encode zstd br gzip

    @forbidden path /.git/* /.env /.env.*
    respond @forbidden 404

    php_server

    log {
        output stderr
        format console
        level DEBUG
    }

    @static path *.css *.js *.png *.jpg *.jpeg *.gif *.svg *.webp *.avif *.woff *.woff2 *.ico *.map
    header @static Cache-Control "no-store, no-cache, must-revalidate"
}
```

### 3.5b `deploy/Caddyfile.prod`

```
{
    frankenphp
    order php_server before file_server
    admin off
    email {$CADDY_EMAIL}
}

{$SERVER_NAME} {
    root /app/public
    encode zstd br gzip

    @forbidden path /.git/* /.env /.env.* /composer.* /package.* /artisan /storage/logs/* /bootstrap/cache/*
    respond @forbidden 404

    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Content-Type-Options nosniff
        X-Frame-Options DENY
        Referrer-Policy strict-origin-when-cross-origin
        Permissions-Policy "interest-cohort=(), geolocation=(), microphone=(), camera=()"
        -Server
        -X-Powered-By
    }

    php_server

    log {
        output stderr
        format json
        level INFO
    }

    @immutable path /build/*
    header @immutable Cache-Control "public, max-age=31536000, immutable"

    @static path *.css *.js *.png *.jpg *.jpeg *.gif *.svg *.webp *.avif *.woff *.woff2 *.ico
    header @static Cache-Control "public, max-age=31536000"

    @maps path *.map
    header @maps Cache-Control "no-cache"
}
```

> **Octane 与 Caddyfile 的关系**:`php artisan octane:frankenphp` 默认动态生成 Caddy 配置,**不读** `/etc/caddy/Caddyfile`。若要让 `Caddyfile.prod` 的安全头和缓存策略在生产生效,需要给 octane 命令加 `--caddyfile=/etc/caddy/Caddyfile` 参数。

### 3.6 `deploy/php.dev.ini`

```ini
display_errors = On
display_startup_errors = On
error_reporting = E_ALL
log_errors = On
log_errors_max_len = 0

upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 512M

opcache.enable = 1
opcache.enable_cli = 0
opcache.validate_timestamps = 1
opcache.revalidate_freq = 0

xdebug.mode = off
xdebug.start_with_request = yes
xdebug.client_host = host.docker.internal
xdebug.client_port = 9003
xdebug.log = /tmp/xdebug.log
```

### 3.7 `deploy/php.prod.ini`

```ini
display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

upload_max_filesize = 20M
post_max_size = 20M
max_execution_time = 30
memory_limit = 256M

opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.fast_shutdown = 1

opcache.jit_buffer_size = 128M
opcache.jit = tracing
```

### 3.8 `.env.example`

完整内容见仓库根 [`./.env.example`](../.env.example)(约 160 行,持续维护);此处只做结构总览。

**12 个逻辑分段**:

| # | 段落 | 关键字段 |
|---|------|----------|
| 1 | 用法注释块 | 开发 / 生产各改哪些 |
| 2 | Docker 编排 | `COMPOSE_PROJECT_NAME` / `PHP_VERSION=8.5.5` / `FRANKENPHP_VERSION=1.12.2` |
| 3 | 开发端口 | `APP_PORT`(HTTP)/ `POSTGRES_PORT` / `VALKEY_PORT` / `MAILPIT_*` / `MEILISEARCH_PORT`(`APP_HTTPS_PORT` 默认注释,仅手动开 HTTPS 时用) |
| 4 | 域名与 ACME | `SERVER_NAME` / `APP_DOMAIN` / `CADDY_EMAIL` |
| 5 | Laravel 应用 | `APP_NAME=ForgeCMS` / `APP_LOCALE=zh_CN` / `APP_FAKER_LOCALE=zh_CN` / `BCRYPT_ROUNDS=12` |
| 6 | 日志 | `LOG_CHANNEL=stderr`(容器化关键,不落盘) |
| 7 | 数据库 | `DB_HOST=postgres`(容器 DNS,不是 127.0.0.1) |
| 8 | 会话 / 缓存 / 队列 / 广播 | 全部走 Redis/Valkey(`SESSION_DRIVER=redis` / `CACHE_STORE=redis` / `QUEUE_CONNECTION=redis` / `BROADCAST_CONNECTION=reverb`) |
| 9 | Redis/Valkey | `REDIS_HOST=valkey` / `REDIS_CLIENT=phpredis` |
| 10 | Mail | 开发走 Mailpit(`MAIL_HOST=mailpit` / `MAIL_PORT=1025`) |
| 11 | 对象存储 | `AWS_*` 预留,`FILESYSTEM_DISK=local` 默认 |
| 12 | 搜索 / Octane / SSR / Reverb / Vite / Xdebug | 各自独立段落 |

**生产部署时必改的 8 项**(顶部注释块已列):
`APP_ENV=production`、`APP_DEBUG=false`、`APP_URL`、`APP_DOMAIN`、`DB_PASSWORD`、`REDIS_PASSWORD`、`MEILISEARCH_KEY`、所有 `REVERB_*` + `CADDY_EMAIL`。

### 3.9 `.dockerignore`

```
.git
.gitignore
.env
.env.*
!.env.example

vendor
node_modules
public/build
public/hot

storage/logs/*
!storage/logs/.gitignore
storage/framework/cache/data/*
storage/framework/sessions/*
storage/framework/views/*
storage/framework/testing/*

tests
.phpunit.cache
.phpunit.result.cache
phpstan.neon
.php-cs-fixer.cache

compose.override.local.yaml
Dockerfile*
compose*.yaml
.dockerignore

.idea
.vscode
.fleet

.DS_Store
Thumbs.db

README.md
CHANGELOG.md
docs
```

### 3.10 `.gitignore`(追加)

```
# Docker
.env
.env.backup
.env.production
compose.override.local.yaml

# Laravel
/vendor
/node_modules
/public/build
/public/hot
/public/storage
/storage/*.key
/storage/pail
.env
.phpactor.json
.phpunit.result.cache
Homestead.json
Homestead.yaml
auth.json
npm-debug.log
yarn-error.log
/.fleet
/.idea
/.nova
/.vscode
/.zed
```

### 3.11 `Makefile`

实际内容以仓库根的 `Makefile` 为准。关键设计点:

- **扁平目标,无分组** —— 项目规模下分组反而增加维护负担
- **`make c / a / ca / npm` 是四个通用入口**:
  - `c` = 宿主机 composer(Herd Lite)
  - `a` = 宿主机 artisan(不连服务的命令)
  - `ca` = 容器 artisan(需要连 postgres/valkey/meili 的命令)
  - `npm` = 宿主机 bun/npm
- `COMPOSE_PROD` 用 `-f compose.yml -f compose.prod.yml` 双文件链,**显式跳过 `compose.override.yml`**(生产不要开发 overlay)
- `include .env; export` + `$(DB_USERNAME)` 用 Make 变量展开,失败信号清晰 —— 变量没定义时命令空字符串报错,不静默
- `restart` 只重启 `app`,避免抖动 postgres/valkey
- `setup` 用**宿主机 composer install**(因为已有 Herd Lite),再启容器跑 migrate
- **绝对路径封装宿主机二进制**:`HERD_BIN := $(HOME)/.config/herd-lite/bin`,然后 `HOST_COMPOSER := env PATH="$(HERD_BIN):$(PATH)" $(HERD_BIN)/composer`。**原因**:macOS 自带 GNU Make 3.81(2006 年版,Apple 因 GPLv3 冻结未升级),对 `export PATH := ...` 的 execvp 处理有 bug,简单 recipe 命令(如 `composer install`)不继承新 PATH。用绝对路径 + `env` 前缀绕过这个 bug,兼容所有 Make 版本和任何 shell。

典型命令:

```bash
make c require spatie/laravel-feed       # 宿主机装包
make a make:model Tag -mfsc              # 宿主机生成模型
make migrate                             # 容器跑迁移
make ca queue:work                       # 容器跑队列
make npm run build                       # 宿主机 bun 构建前端
```

---

## 四、快速启动

### 4.1 新项目从零开始

```bash
# 0. 前置:Herd Lite 已装(§1.0),Colima 已跑
php --version                           # 验证 Herd Lite 可用
colima status

# 1. 克隆或新建目录,放入配置骨架(compose.*.yml / Makefile / deploy/ / .env.example)
mkdir forge-cms && cd forge-cms
# (从模板仓库拷贝或按第三节手动创建)

# 2. 复制 .env
cp .env.example .env

# 3. 宿主机 Laravel installer 建骨架(一次性把技术栈选项都传进去)
laravel new tmp --react --pest --bun --database=pgsql --no-interaction
rm -rf tmp/.git 2>/dev/null                               # 兜底:删可能被创建的嵌套 .git
rsync -a --ignore-existing tmp/ .                         # 同步 tmp 内容到根,不覆盖已有文件
rm -rf tmp                                                # 清理临时目录

# ↑ 命令详解
#
# laravel new tmp --react --pest --bun --database=pgsql --no-interaction
#   tmp                  目标目录名(不能用 . 因为当前目录已有 docs/ 等文件)
#   --react              装 Inertia + React + shadcn/ui + Vite + Tailwind + 身份验证 UI
#   --pest               用 Pest 替代默认 PHPUnit
#   --bun                用 Bun 作为 JS 包管理器(生成 bun.lockb)
#   --database=pgsql     .env 预填 PostgreSQL 连接
#   --no-interaction     跳过其余交互问答,用默认值
#
# ⚠️ 注意:--git 是布尔 flag(存在即启用),不接受 --git=false 语法。
#   默认不启用 git init,所以不写它即可;rm -rf tmp/.git 是兜底防御。
#
# rsync -a --ignore-existing tmp/ .
#   -a                   archive 模式:递归 + 保权限 + 自带包含隐藏文件
#                        (比 cp 省心,不用单独开 dotglob)
#   --ignore-existing    目标已存在的文件跳过,等价 cp -n
#                        → 我们的 .env.example / .gitignore / README.md 被保留
#   tmp/ 尾斜杠           同步 tmp 的【内容】而非 tmp 目录本身
#   .                    目标 = 当前目录
#
# 为什么用 rsync 不用 cp + shopt:
#   - shopt 是 bash 内置,zsh(macOS 2026 默认)没有,会报 "command not found"
#   - rsync 跨所有 shell 可用,语义更显式

# 4. 宿主机装所有业务包(见 docs/laravel.md §13.3 完整清单)
composer require \
  laravel/octane laravel/horizon laravel/sanctum laravel/scout laravel/reverb \
  inertiajs/inertia-laravel tightenco/ziggy \
  filament/filament bezhansalleh/filament-shield \
  spatie/laravel-permission spatie/laravel-medialibrary \
  # ... 其余见 laravel.md

# 5. 启动容器服务
make dev

# 6. 容器内跑迁移(连 postgres)
make ca key:generate
make migrate

# 7. 访问
open http://forge-cms.localhost:8001
```

### 4.2 已有项目克隆

```bash
git clone <repo>
cd forge-cms
make setup
```

`make setup` 会自动完成 copy .env、build、up、composer install、key:generate、migrate。

### 4.3 日常命令

**服务管理**(都在容器):

```bash
make dev               # 起（开发）
make down              # 停
make restart           # 重启 app
make build             # 重新构建 app 镜像
make ps                # 服务状态
make logs              # 跟踪日志(Ctrl+C 退出)
```

**进容器**(调试或跑需要服务的命令):

```bash
make app               # 进入 app 容器 bash
make db                # 进入 postgres 容器 bash
make psql              # 直接进 psql 客户端
```

**宿主机 composer**(Herd Lite):

```bash
make c install                          # = composer install
make c require spatie/laravel-feed      # 装单个包
make c require --dev larastan/larastan
make c update
make c outdated                         # 查过期依赖
make c show filament/filament           # 查看包详情
make c why spatie/laravel-permission    # 谁依赖了它
```

**宿主机 artisan**(不连服务的命令,秒回):

```bash
make a make:model Post -mfsc            # 生成 model + migration + factory + seeder + controller
make a make:filament-resource Post --generate
make a route:list                       # 查看路由表
make a config:show app                  # 查看配置
make a pint                             # 代码格式化
make a shield:generate                  # Filament Shield 权限
```

**容器内 artisan**(必须连 postgres / valkey / meilisearch):

```bash
make migrate                            # 执行迁移
make fresh                              # 清库重建 + seed
make tinker                             # REPL(要连数据库才能查模型)
make ca db:seed                         # 通用入口:容器内 artisan
make ca queue:work                      # 跑队列 worker
make ca scout:import "App\Models\Post"  # 索引同步到 Meilisearch
make ca horizon
make ca reverb:start                    # WebSocket 服务
```

**前端**(宿主机 bun):

```bash
make npm install                        # = bun install
make npm run dev                        # Vite 开发服务器,HMR 端口 5173
make npm run build                      # 生产资产构建(部署前必跑)
```

**数据库维护**:

```bash
make dump                               # 导出 backup_YYYYMMDD_HHMMSS.sql
make import                             # 交互式选择 .sql 文件导入
make key                                # 生成 APP_KEY(宿主机,改本地 .env)
```

**生产**:

```bash
make prod                               # 启动生产(在服务器上)
```

**清理**(危险):

```bash
make clean                              # 删所有容器、镜像、数据卷(需输入 yes 确认)
```

**判断一个命令跑 host 还是 container 的规则**:

| 命令性质 | 例子 | 跑哪 | Makefile 入口 |
|---------|------|------|---------------|
| 依赖管理 | `composer require` / `install` / `update` | 🖥️ host | `make c ...` |
| 文件生成 | `artisan make:*`, `pint`, `shield:generate` | 🖥️ host | `make a ...` |
| 读配置/路由 | `route:list`, `config:show` | 🖥️ host | `make a ...` |
| 数据库 | `migrate`, `db:seed`, `db:wipe` | 🐳 container | `make migrate` / `make ca ...` |
| 缓存 | `cache:clear`, `config:cache` | 🐳 container | `make ca ...` |
| 队列/调度 | `queue:work`, `horizon`, `schedule:work` | 🐳 container | `make ca ...` |
| 搜索 | `scout:*` | 🐳 container | `make ca ...` |
| WebSocket | `reverb:start` | 🐳 container | `make ca ...` |
| REPL | `tinker` | 🐳 container | `make tinker` |
| 前端构建 | `bun install/run` | 🖥️ host | `make npm ...` |

---

## 五、访问地址

| 服务 | URL / 端口 |
|------|-----------|
| 应用(dev) | http://forge-cms.localhost:8080 |
| Mailpit Web | http://localhost:8025 |
| Meilisearch | http://localhost:7700 |
| PostgreSQL | localhost:5432 |
| Valkey/Redis | localhost:6379 |

> Dev 不起 HTTPS(Caddyfile.dev 的 `auto_https off`)。生产走 80→443 真证书,见 §九。

### 5.1 `.localhost` 域名解析

macOS 原生解析 `*.localhost` 到 `127.0.0.1`,无需改 hosts。验证:

```bash
ping forge-cms.localhost
# 应 ping 到 127.0.0.1
```

如果不通(老版本 macOS),手动加:

```bash
sudo sh -c 'echo "127.0.0.1 forge-cms.localhost" >> /etc/hosts'
```

### 5.2 信任本地 HTTPS 证书(仅 HTTPS 开发场景用)

> Dev 默认关闭 HTTPS,**本节不适用日常开发**。只有当你临时切到 HTTPS(比如测试 Service Worker、OAuth `redirect_uri` 校验、secure cookie)时才需要 —— 自己把 `deploy/Caddyfile.dev` 的 `auto_https off` 改成 `auto_https disable_redirects`,容器重启后访问 `https://forge-cms.localhost:8443` 会有 Caddy 自签证书警告。信任流程:

```bash
# 从容器导出根证书
docker compose exec app cat /data/caddy/pki/authorities/local/root.crt > /tmp/caddy-root.crt

# 加入系统钥匙串
sudo security add-trusted-cert -d -r trustRoot \
    -k /Library/Keychains/System.keychain /tmp/caddy-root.crt

# 清理
rm /tmp/caddy-root.crt
```

信任一次,所有 `*.localhost` 站点都免警告。

### 5.3 数据库客户端连接

推荐 **TablePlus**(`brew install --cask tableplus`):

```
Host:     127.0.0.1
Port:     5432
User:     forge_cms
Password: secret
Database: forge_cms
```

---

## 六、进阶配置

### 6.1 个人覆盖(不进 Git)

如果个人开发者想改端口、加环境变量,但不想影响团队:

```yaml
# compose.override.local.yaml (进 .gitignore)
services:
  app:
    ports:
      - "8888:80"   # 我本地 8080 被占了
    environment:
      MY_PERSONAL_VAR: value
```

启动时:

```bash
docker compose \
  -f compose.yml \
  -f compose.override.yml \
  -f compose.override.local.yaml \
  up -d
```

或者在 Makefile 里加个 `COMPOSE_FILE` 环境变量处理。

### 6.2 多项目同时开发

每个项目用不同端口,在各自 `.env` 里错开:

```bash
# 项目 A:.env
APP_PORT=8080
POSTGRES_PORT=5432
VALKEY_PORT=6379

# 项目 B:.env
APP_PORT=8081
POSTGRES_PORT=5433
VALKEY_PORT=6380
```

`COMPOSE_PROJECT_NAME` 也要不同(隔离容器和 volume)。

### 6.3 Xdebug 配置(VS Code)

`.vscode/launch.json`:

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/app": "${workspaceFolder}"
            }
        }
    ]
}
```

然后:

```bash
make xdebug-on
# 在 VS Code 按 F5 开启监听
# 设断点,触发请求
make xdebug-off   # 调完关掉(Xdebug 拖性能)
```

### 6.4 前端 Vite HMR

`vite.config.js` 配置:

```javascript
export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
    },
    // ...
});
```

`compose.override.yml` 暴露 5173 端口:

```yaml
services:
  app:
    ports:
      - "5173:5173"
```

启动:

```bash
make dev
```

### 6.5 队列和定时任务

开发环境不需要独立 queue 容器,直接在 app 容器里跑:

```bash
# 进容器
make shell

# 跑队列(前台)
php artisan queue:work

# 或用 Horizon
php artisan horizon
```

生产环境用 `compose.prod.yml` 里定义的独立 `queue` 和 `scheduler` 服务。

---

## 七、常见问题

### 7.1 Colima 启动提示磁盘相关警告

```
WARN unable to resize disk: specified size "60GiB" is less than
     the current disk size "100GiB". Disk shrinking is currently unavailable
```

**原因**:磁盘只能增大不能缩小。
**处理**:不影响使用,警告可忽略。要彻底清理重来:`colima delete` → 重新 `colima start`(会丢所有 Docker 数据)。

### 7.2 端口被占用

```
Error: port is already allocated
```

**排查**:

```bash
lsof -i :8080
```

**处理**:改 `.env` 里对应端口,`make down && make up`。

### 7.3 bind mount 性能慢

**排查**:

```bash
colima status
# 确认 mountType: virtiofs
```

不是 virtiofs 就重启 Colima:

```bash
colima stop
colima start --vm-type vz --mount-type virtiofs
```

### 7.4 容器里改了代码不生效

**可能原因**:

1. OPcache 缓存了 —— 开发 `php.dev.ini` 里 `opcache.validate_timestamps = 1` 已经打开,正常情况不应该有这问题
2. 用了 Octane worker 模式 —— 开发环境建议不用 worker,用普通 `php_server`
3. bind mount 没生效 —— 检查 `volumes: .:/app:cached` 是否存在

### 7.5 权限错误

容器里创建的文件在宿主机看是 root:

```bash
# 改回当前用户
sudo chown -R $(whoami) .
```

预防:Dockerfile 已用 `app` 用户(UID 1000),如果 Mac 用户 UID 不是 1000 会有问题。可以在 `compose.override.yml` 里加:

```yaml
services:
  app:
    build:
      args:
        UID: ${UID:-501}
        GID: ${GID:-20}
```

并修改 Dockerfile 接收 UID/GID 参数。

### 7.6 数据库连接失败

容器内 app 连 `postgres` 用服务名:

```
DB_HOST=postgres
DB_PORT=5432
```

**不要用 `localhost` 或 `127.0.0.1`**,那是容器自己。

宿主机连数据库才用 `localhost:5432`(映射到容器的 5432)。

### 7.7 Colima VM 体积变大

Colima VM 磁盘用久了会膨胀。清理:

```bash
# 清 Docker 未使用的资源
docker system prune -a --volumes

# 清理无用镜像
docker image prune -a
```

彻底清零(核选项):

```bash
colima delete
colima start
```

### 7.8 `make setup` 报 `composer: No such file or directory`

**错误信息**:

```
composer install
make: composer: No such file or directory
make: *** [setup] Error 1
```

**原因**:macOS 自带 GNU Make 3.81(2006 年版,Apple 为避免 GPL v3 冻结未升级)。对简单 recipe 命令(无 shell 元字符)Make 走 `execvp` 直接调用,不读 recipe 里 `export PATH := ...` 设置的新 PATH,也不加载你 shell 的 `~/.zshrc`。Herd Lite 装在 `~/.config/herd-lite/bin/` 找不到。

**排查**:

```bash
ls ~/.config/herd-lite/bin/composer   # 应该存在
make --version                         # 看到 "GNU Make 3.81" 就是命中
```

**解决**:Makefile 已用 `HERD_BIN` 绝对路径 + `env PATH=...` 前缀封装二进制调用,规避了这个 bug(见 §3.11)。如果错误仍出现:

1. 确认 Herd Lite 已装:`/bin/bash -c "$(curl -fsSL https://php.new/install/mac)"`
2. 确认你本机 `$HOME` 展开正确(Makefile 里 `$(HOME)` 应该是 `/Users/你的用户名`)
3. 实在不行,宿主机手动装 `brew install make`,用 `gmake` 代替 `make`(3.82+ 行为正常)

### 7.9 `port is already allocated`(端口被占)

**错误信息**:

```
Error response from daemon: failed to set up container networking:
driver failed programming external connectivity on endpoint forge-cms-app-1:
Bind for 0.0.0.0:8080 failed: port is already allocated
```

**排查**:

```bash
lsof -i :8080    # 看谁占了
# 常见占用者:ssh tunnel、Jenkins、Tomcat、某些 VPN 代理
```

**解决**:改**本机 `.env`** 的 `APP_PORT`(不要动 `.env.example`,团队默认 8080 保持):

```bash
# .env(仅本机)
APP_PORT=8001          # 或其他空闲端口(dev 只绑 HTTP)
```

然后:

```bash
make down && make dev
```

**找空闲端口的快速方法**:

```bash
for p in 8001 8002 8088 9090 3000; do
  lsof -i :$p >/dev/null && echo "❌ $p" || echo "✅ $p"
done
```

---

## 八、技术栈要点

### 8.1 各组件版本对照(2026 年 4 月)

| 组件 | 最新 stable |
| --- | --- |
| PHP | 8.5.5 |
| Laravel | 13.x |
| FrankenPHP | 1.12.2 |
| PostgreSQL | 18.3 |
| Valkey | 9.0.3 |
| Vite | 8.0.8 |
| Node.js | 24.14.1 LTS (Krypton) |

### 8.2 为什么用 Valkey 不用 Redis

Redis 8.0+ 采用 **RSALv2 / SSPLv1 / AGPLv3** 三重许可。AGPLv3 对 SaaS 场景有开源传染性风险。**Valkey 是 Linux 基金会 fork,保持 BSD-3,商用零风险**,协议完全兼容 Redis,Laravel 的 Redis 驱动直接可用。

### 8.3 为什么用 FrankenPHP 不用 Nginx + PHP-FPM

- 单容器,运维简单
- 自带 HTTPS(Caddy)
- 支持 HTTP/2、HTTP/3
- Worker 模式性能提升 3-10 倍
- Laravel Octane 官方推荐

### 8.4 为什么开发环境不用 worker 模式

Worker 模式下应用常驻内存,代码修改不立即生效,还要考虑状态泄漏。**开发用普通 `php_server` 模式,生产用 Octane worker 模式**,在 `compose.prod.yml` 的 `command` 里切换。

---

## 九、生产部署流程

**生产命令链**:所有生产 compose 操作都是 `docker compose -f compose.yml -f compose.prod.yml <cmd>` —— 显式指定两个文件,跳过 `compose.override.yml`(不能让开发 override 污染生产)。

```bash
# 1. 服务器上克隆代码
git clone <repo> /opt/forge-cms
cd /opt/forge-cms

# 2. 配置生产 .env(基于 deploy/.env.production.example)
cp deploy/.env.production.example .env
vi .env
# 必改:
#   APP_ENV=production
#   APP_DEBUG=false
#   APP_DOMAIN=real-domain.com
#   DB_PASSWORD=强密码
#   REDIS_PASSWORD=强密码
# 生成 APP_KEY:
#   docker compose -f compose.yml -f compose.prod.yml run --rm app php artisan key:generate --show

# 3. 构建 + 启动
docker compose -f compose.yml -f compose.prod.yml build
make prod                         # = docker compose -f compose.yml -f compose.prod.yml up -d

# 4. 迁移
docker compose -f compose.yml -f compose.prod.yml exec app php artisan migrate --force

# 5. 查看
docker compose -f compose.yml -f compose.prod.yml logs -f
```

**后续更新部署**:

```bash
cd /opt/forge-cms
git pull
bun install && bun run build       # 前端资产(host 构建)
docker compose -f compose.yml -f compose.prod.yml build
docker compose -f compose.yml -f compose.prod.yml up -d --no-deps app queue scheduler
docker compose -f compose.yml -f compose.prod.yml exec app php artisan migrate --force
docker compose -f compose.yml -f compose.prod.yml exec app php artisan optimize
docker compose -f compose.yml -f compose.prod.yml exec app php artisan reload    # 重启 Octane / Horizon / Reverb worker
```

**关键命令说明**:

- `php artisan optimize` —— Laravel 13 的 **一条命令顶四条**,等价 `config:cache + event:cache + route:cache + view:cache`
- `php artisan reload` —— Laravel 13 新增,统一重启所有长驻进程(Octane / Horizon / Reverb / queue worker),不用再手写 supervisord 钩子
- 有问题立即执行 `php artisan about` 看各项配置状态

> 可以把上述步骤封装到 `deploy/deploy.sh`,用 `./deploy/deploy.sh` 一键部署。这个脚本本身就是 `deploy/` 目录的典型内容之一。

---

## 十、参考链接

- [Colima GitHub](https://github.com/abiosoft/colima)
- [Docker Compose 官方文档](https://docs.docker.com/compose/)
- [FrankenPHP 官网](https://frankenphp.dev/)
- [Laravel Octane 文档](https://laravel.com/docs/octane)
- [Valkey 官网](https://valkey.io/)

---

## 速查:常用端口

| 服务 | 开发 | 生产 |
|------|------|------|
| App HTTP | 8080 | 80(自动 → 443) |
| App HTTPS | — (dev 不起) | 443 |
| PostgreSQL | 5432 | 内部 |
| Valkey | 6379 | 内部 |
| Mailpit SMTP | 1025 | - |
| Mailpit Web | 8025 | - |
| Meilisearch | 7700 | 内部 |
| Xdebug | 9003 | - |

**开发环境不占用 80/443**,可与其他本地服务共存。
