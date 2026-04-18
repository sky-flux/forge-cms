.PHONY: bootstrap setup dev prod down restart build logs ps app db psql migrate fresh tinker key dump import clean help c a ca npm

# ----------------------------------------------------------------------------
# Herd Lite（宿主机 PHP 工具链）绝对路径
#
# macOS 自带 GNU Make 3.81（2006 年版），不能正确处理 `export PATH := ...` 下的
# execvp 简单命令查找。因此所有宿主机二进制走绝对路径变量封装，兼容所有 Make
# 版本 + 避免 PATH 继承问题。
# ----------------------------------------------------------------------------
HERD_BIN := $(HOME)/.config/herd-lite/bin

# `env PATH=... CMD` 走 /usr/bin/env,确保子进程(如 composer 内部调用 php)能找到 Herd Lite 的 php
HOST_ENV := env PATH="$(HERD_BIN):$(PATH)"
HOST_PHP := $(HOST_ENV) $(HERD_BIN)/php
HOST_COMPOSER := $(HOST_ENV) $(HERD_BIN)/composer
HOST_LARAVEL := $(HOST_ENV) $(HERD_BIN)/laravel
HOST_BUN := $(HOME)/.bun/bin/bun

ifneq (,$(wildcard ./.env))
    include .env
    export
endif

COMPOSE      = docker compose
COMPOSE_PROD = docker compose -f compose.yml -f compose.prod.yml

help:
	@echo ""
	@echo "  make bootstrap 首次建 Laravel 骨架（仅当 composer.json 不存在时）"
	@echo "  make setup     首次初始化（host composer install + 启动容器 + 迁移）"
	@echo "  make dev       启动开发环境（容器服务）"
	@echo "  make prod      启动生产环境"
	@echo "  make down      停止"
	@echo "  make restart   重启 app"
	@echo "  make build     重新构建 app 镜像"
	@echo "  make logs      查看日志（Ctrl+C 退出）"
	@echo "  make ps        服务状态"
	@echo ""
	@echo "  make app       进入 app 容器 bash"
	@echo "  make db        进入 postgres 容器 bash"
	@echo "  make psql      进入 psql 客户端"
	@echo ""
	@echo "  make c [args]  宿主机 composer（装/更新依赖）"
	@echo "  make a [args]  宿主机 artisan（生成文件、不连服务的命令）"
	@echo "  make ca [args] 容器 artisan（连服务的命令：migrate/queue/scout 等）"
	@echo "  make npm [args] 宿主机 bun/npm"
	@echo ""
	@echo "  make migrate   执行数据库迁移（容器）"
	@echo "  make fresh     清库重建 + seed（容器）"
	@echo "  make tinker    进入 tinker（容器）"
	@echo "  make key       生成 APP_KEY（宿主机）"
	@echo "  make dump      导出数据库"
	@echo "  make import    导入 SQL 文件"
	@echo "  make clean     删除所有容器、镜像和数据卷"
	@echo ""

setup:
	@test -f .env || cp .env.example .env
	@test -f composer.json || { \
		echo "❌ composer.json 不存在 —— Laravel 骨架未初始化。先跑:"; \
		echo ""; \
		echo "    make bootstrap"; \
		echo ""; \
		exit 1; \
	}
	$(HOST_COMPOSER) install
	$(COMPOSE) up -d
	@if grep -q "^APP_KEY=base64:" .env; then \
		echo "✓ APP_KEY 已存在,跳过 key:generate"; \
	else \
		$(HOST_PHP) artisan key:generate; \
	fi
	$(COMPOSE) exec app php artisan migrate --force
	@echo "✅ 初始化完成,访问 http://$(SERVER_NAME):$(APP_PORT)"

bootstrap:
	@if [ -f composer.json ]; then \
		echo "⚠️  Laravel 骨架已存在,跳过。要重新 bootstrap 请先备份并删除 artisan/composer.json"; \
		exit 1; \
	fi
	$(HOST_LARAVEL) new tmp --react --pest --bun --database=pgsql --no-interaction
	rm -rf tmp/.git 2>/dev/null
	rsync -a --ignore-existing tmp/ .
	rm -rf tmp
	@echo "✅ Laravel 骨架已合并到根目录,现在跑 'make setup' 完成初始化"

dev:
	$(COMPOSE) up -d

prod:
	$(COMPOSE_PROD) up -d

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) restart app

build:
	$(COMPOSE) build --no-cache app

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

app:
	$(COMPOSE) exec app bash

db:
	$(COMPOSE) exec postgres bash

psql:
	$(COMPOSE) exec postgres psql -U $(DB_USERNAME) -d $(DB_DATABASE)

# 宿主机 composer（Herd Lite）—— 依赖管理
c:
	$(HOST_COMPOSER) $(filter-out $@,$(MAKECMDGOALS))

# 宿主机 artisan —— 只做文件生成/读取类操作（make:* / pint / route:list / config:show 等）
a:
	$(HOST_PHP) artisan $(filter-out $@,$(MAKECMDGOALS))

# 容器 artisan —— 需要连接 postgres / valkey / meilisearch 的命令
ca:
	$(COMPOSE) exec app php artisan $(filter-out $@,$(MAKECMDGOALS))

# 宿主机 bun/npm —— 前端
npm:
	$(HOST_BUN) $(filter-out $@,$(MAKECMDGOALS))

migrate:
	$(COMPOSE) exec app php artisan migrate

fresh:
	$(COMPOSE) exec app php artisan migrate:fresh --seed

tinker:
	$(COMPOSE) exec app php artisan tinker

key:
	$(HOST_PHP) artisan key:generate

dump:
	$(COMPOSE) exec -T postgres pg_dump -U $(DB_USERNAME) $(DB_DATABASE) > backup_$$(date +%Y%m%d_%H%M%S).sql

import:
	@ls -lh *.sql 2>/dev/null || echo "(未找到 .sql 文件)"
	@read -p "输入文件名: " file; \
	$(COMPOSE) exec -T postgres psql -U $(DB_USERNAME) -d $(DB_DATABASE) < $$file

clean:
	@read -p "⚠️  将删除所有容器、镜像和数据卷，输入 yes 继续: " confirm && [ "$$confirm" = "yes" ]
	$(COMPOSE) down -v --rmi all --remove-orphans

# 捕获未知目标参数（供 c/a/ca/npm 传参用）
%:
	@:
