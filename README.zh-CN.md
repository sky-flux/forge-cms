# ForgeCMS

> 基于 Laravel 的现代化 CMS，运行在 FrankenPHP + PostgreSQL + Valkey 之上。

**语言** · [English](./README.md) · [简体中文](./README.zh-CN.md)

---

## 技术栈

| 层级 | 选型 | 版本 |
|------|------|------|
| 运行时 | PHP on FrankenPHP（Caddy + Octane worker 模式） | 8.5.5 / 1.12.2 |
| 框架 | Laravel | 13.x |
| 数据库 | PostgreSQL | 18.3 |
| 缓存 / 队列 | Valkey（BSD 许可的 Redis 分叉） | 9.0.3 |
| 搜索 | Meilisearch | 1.13 |
| 开发邮件 | Mailpit | latest |
| 前端 | Vite（Node runtime）+ Bun（包管理器），宿主机侧 | — |
| 编排 | Colima + Docker Compose v2 | — |

## 前置要求

- Apple Silicon 的 macOS（Linux 也行，路径可能略有差异）
- **宿主机 PHP 工具链**：[Herd Lite](https://php.new) — 提供 PHP / Composer / Laravel 安装器
- [Colima](https://github.com/abiosoft/colima)、Docker CLI、Docker Compose v2（服务层）
- Node.js 24.14.1 LTS（Vite 运行时）
- [Bun](https://bun.sh)（包管理器）

macOS 安装：

```bash
# 1. 宿主机 PHP 工具链（Herd Lite 一行装齐）
/bin/bash -c "$(curl -fsSL https://php.new/install/mac)"
source ~/.zshrc

# 2. 容器服务层
brew install colima docker docker-compose docker-buildx

# 3. 前端工具链
volta install node@24.14.1        # 精确 pin Node 24 LTS
brew install oven-sh/bun/bun      # Bun 作为包管理器
```

**工作流分工**：PHP / Composer / 代码生成类 Artisan 跑在宿主机（快）；PostgreSQL / Valkey / Meilisearch / FrankenPHP runtime 跑在容器。详见 [`docs/setup.md`](./docs/setup.md)。

## 快速上手

```bash
# 1. 启动 Colima（首次）
colima start --cpu 4 --memory 8 --vm-type vz --mount-type virtiofs

# 2. 克隆并初始化
git clone <repo-url> forge-cms
cd forge-cms
make setup                         # 宿主机 composer install + 启动容器 + 迁移

# 3. 宿主机安装前端依赖并启动 Vite 开发服务器
bun install                        # 生成 bun.lockb
bun run dev                        # Vite 开发服务器跑在 :5173

# 4. 打开应用
open http://forge-cms.localhost:8001
```

`make setup` 自动完成：复制 `.env` → 宿主机 `composer install` → `docker compose up -d` → 容器里 `artisan key:generate` + `migrate`。

## 日常命令

```bash
# 服务
make dev / make down / make restart

# 宿主机 composer（Herd Lite）
make c require <package>
make c update

# 宿主机 artisan（文件生成，不连服务）
make a make:model Post -mfsc
make a make:filament-resource Post

# 容器 artisan（需连 postgres/valkey/meilisearch）
make migrate
make ca queue:work
make ca scout:import "App\Models\Post"

# 进容器
make app     # app 容器 bash
make psql    # postgres psql 客户端
make tinker  # Laravel REPL（容器内）

# 查看完整命令
make help
```

详见 [`Makefile`](./Makefile) 或运行 `make help`。

## 项目结构

```
forge-cms/
├── compose.yml              # 基础编排
├── compose.override.yml     # 开发 overlay（自动加载）
├── compose.prod.yml         # 生产 overlay（需显式 -f 指定）
├── Makefile
├── .env.example             # 单一配置模板
├── deploy/                  # 容器与 web 服务器配置
│   ├── Dockerfile           # 多阶段：base → dev / prod
│   ├── Caddyfile.dev
│   ├── Caddyfile.prod
│   ├── php.dev.ini
│   └── php.prod.ini
└── docs/
    └── setup.md             # 完整配置参考
```

## 生产部署

```bash
# 在服务器上（Herd Lite + Colima + Bun 同本地装法，或接 CI）
git clone <repo-url> /opt/forge-cms && cd /opt/forge-cms
cp .env.example .env && vi .env    # 设置 APP_ENV=production、APP_KEY、域名、强密码

# 先在宿主机构建前端资产（生产镜像需要）
bun install && bun run build

# 构建生产镜像（容器内部 composer install --no-dev，使用仓库里的 lock 文件）
docker compose -f compose.yml -f compose.prod.yml build
docker compose -f compose.yml -f compose.prod.yml up -d
docker compose -f compose.yml -f compose.prod.yml exec app php artisan migrate --force
```

## 文档

关于 Colima 调优、Xdebug 配置、Vite HMR、UID 映射、故障排查等完整细节，请查阅 [`docs/setup.md`](./docs/setup.md)。

## 许可证

MIT
