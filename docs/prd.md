# ForgeCMS 产品需求文档（PRD）

> 本文档定义 ForgeCMS v1.0 的范围边界、目标用户与功能列表。所有路线图、数据模型、用户故事以此为依据。

## 关于本文档的基线假设

> 已与 [`setup.md`](./setup.md) 的基础设施定义对齐；已与 v1.0 技术选型决策对齐。

- **产品形态**：自托管的通用内容管理系统，对标 WordPress 但现代化（Laravel + PostgreSQL + 开发者友好的代码库）
- **部署模型**：单租户（每个站点独立部署一套），暂不考虑 SaaS 多租户
- **受众**：中小团队（10 人内）、独立开发者、内容创作者
- **技术栈**（继承自 setup.md，不再重复基础设施版本）：
  - 后端：Laravel 13 on FrankenPHP 1.12.2 + PHP 8.5.5 + Octane worker 模式
  - 数据：PostgreSQL 18.3 + Valkey 9.0.3 + Meilisearch 1.13
  - **管理后台**：**Filament 5.5.2**（Livewire 底层，独占 `/admin/*` 路由）
  - **前台**：**Inertia.js v2 + React 19 + TypeScript + SSR**
  - **SSR**：Node 24.14.1 LTS + `@inertiajs/react/server`（独立进程）
  - **UI 库**：shadcn/ui（copy-in 组件）+ Tailwind 4 + Radix primitives + lucide-react
  - **权限**：spatie/laravel-permission + bezhansalleh/filament-shield
- **部署目标**（继承 Caddyfile.prod 的安全设定）：
  - 强制 HTTPS（Caddy 自动 Let's Encrypt）
  - HSTS + 安全响应头预置
  - 静态资源 `/build/*` 走 `immutable` 缓存
- **商业模式**：开源（MIT），可选付费主题/插件市场（非本期范围）

---

## 1. 背景与目标

### 1.1 问题陈述

现有 CMS 两极分化：

- **WordPress**：生态庞大但技术栈老旧（PHP 7 时代惯例、MySQL、插件质量参差），对现代开发者不友好
- **Headless CMS（Strapi/Directus）**：前后端分离带来额外复杂度，非技术用户门槛高
- **Laravel 生态现有方案**（Statamic / OctoberCMS）：各有取舍，但未占领"Laravel 原生体验"的中间地带

### 1.2 目标

- **v1.0（MVP）**：覆盖典型博客/品牌站/文档站三种用例的核心功能
- **v1.x**：扩展多语言、SEO、评论、媒体库等
- **v2.0+**：插件市场、主题切换、多站点管理（非本 PRD 范围）

### 1.3 非目标（明确不做）

- ❌ 电商功能（购物车、支付、库存）
- ❌ 论坛/社区功能（用户间交互、私信）
- ❌ 多租户 SaaS
- ❌ 可视化拖拽页面构建器（首版用 Blade/Inertia + Markdown）
- ❌ 迁移工具（从 WordPress 导入）——v2 再考虑

---

## 2. 目标用户

| 角色 | 画像 | 主要诉求 |
|------|------|----------|
| **站长 / 管理员** | 技术型创始人、独立博主 | 自己部署、低成本运维、能看懂代码 |
| **作者 / 编辑** | 内容创作者、市场运营 | 流畅的编辑体验、媒体管理、草稿与定时发布 |
| **开发者 / 集成者** | Laravel 熟练、需要深度定制 | 清晰的 API、模型与路由可扩展、文档齐全 |
| **读者 / 访客** | 内容消费者 | 页面加载快、SEO 好、移动端友好 |

---

## 3. 功能范围

### 3.1 MVP（v1.0）

| 模块 | 功能点 | 优先级 |
|------|--------|--------|
| **用户与权限** | 登录、密码重置、用户 CRUD；spatie 三角色：Admin/Editor/Author | P0 |
| **内容类型** | Post（博客）、Page（静态页） | P0 |
| **编辑器** | Filament 内置 RichEditor（TipTap 底层）+ Markdown 导入导出 | P0 |
| **分类体系** | Category（层级）、Tag（扁平）、多对多挂到 Post | P0 |
| **多语言内容** | ⭐ 支持多语言文章/页面；默认中英双语，可扩展 | P0 |
| **评论系统** | ⭐ Post 下评论、管理员审核、反垃圾（honeypot + Akismet 可选） | P0 |
| **媒体管理** | 图片/文件上传、本地存储默认、S3 可选、自动缩略图 | P0 |
| **发布流** | Draft / Published / Scheduled 三态；30 天垃圾箱 | P0 |
| **前台** | 文章列表页、详情页、分类/标签归档、Meilisearch 搜索 | P0 |
| **SEO 基础** | meta title/description/OG、sitemap.xml、robots.txt、hreflang（多语言） | P0 |
| **管理后台** | **Filament 5.5.2**，含 Dashboard / Posts / Pages / Media / Users / Settings | P0 |
| **开发邮件** | Mailpit 捕获（setup.md 已配） | P1 |

### 3.2 v1.x（MVP 之后，一年内）

- RSS/Atom feed
- 邮件订阅（与 Newsletter 集成）
- API 令牌 + 公开 REST API（Sanctum）
- 主题系统（多套 Blade 模板切换）
- 版本历史（文章 revision）
- 可配置 webhook / 站点事件推送

### 3.3 v2.0+（未来考虑）

- 插件市场
- 多站点管理（一个后台管多个独立前台）
- 可视化页面构建器
- WordPress 数据迁移工具

---

## 4. 非功能需求

| 维度 | 要求 |
|------|------|
| **性能** | 首页冷启动 < 500ms（Octane worker 模式预热后）；列表页 p95 < 200ms |
| **SEO** | Lighthouse SEO ≥ 95；核心页面 SSR（非 SPA） |
| **安全** | Laravel 标准 CSRF/XSS 防护；APP_KEY 强制；HTTPS 仅（Caddy 自动） |
| **可观测** | 结构化日志（JSON，可接 Loki）；关键路径 metrics（可选接 Prometheus） |
| **部署** | 单节点 Docker Compose 即可运行；可选 K8s/Ansible（不在 v1 范围） |
| **浏览器兼容** | 管理后台：最新版 Chrome/Firefox/Safari；前台：IE11 不支持 |
| **国际化** | UI 语言支持中英双语切换（v1.0），内容多语言（v1.x） |

---

## 5. 成功指标（v1.0 发布后 3 个月）

- GitHub Stars ≥ 500
- Docker Hub Pulls ≥ 5k
- Issue 平均响应时间 < 3 天
- 至少 3 个独立第三方写的接入教程/博客
- 自托管实例数（匿名统计，可选开启）≥ 100

---

## 6. 风险与缓解

| 风险 | 影响 | 缓解 |
|------|------|------|
| Laravel 版本升级打破插件 | 高 | 明确支持 Laravel LTS；提供升级指南 |
| Filament 5 深度绑定后期难换 | 中 | 后台抽象接口，理论上可替换；文档标注此依赖 |
| Valkey 生态未来不稳 | 低 | 协议兼容 Redis，最坏切回 Redis |
| 前端栈选型拉锯（Blade vs Inertia vs Livewire） | 中 | v1.0 先定 Blade，Inertia 作 v1.x 备选 |

---

## 7. 已锁定的技术决策

| 决策 | 选定 | 说明 |
|------|------|------|
| 管理后台 | **Filament 5.5.2** | Livewire 底层，独占 `/admin/*`；资源 CRUD 自动生成 |
| 权限系统 | **spatie/laravel-permission** + **bezhansalleh/filament-shield** | 角色 + 权限双体系；Shield 自动把 Filament Resources 转成权限项 |
| 认证后端 | **laravel/fortify** | 由 `--react` starter 默认装;处理 login / register / 2FA / password-reset / email-verification 的服务端逻辑。Fortify 是**无 UI 纯后端**,前端页面自由实现 |
| 前台渲染 | **Inertia.js v2 + React 19 + SSR** | Laravel Controller 返回 `Inertia::render`，Node SSR 产出初始 HTML，浏览器 hydrate 后走 SPA |
| 前台 UI 组件 | **shadcn/ui** | copy-in 到 `resources/js/components/ui/`，自由修改 |
| 前台样式 | **Tailwind 4** + Radix primitives | Filament 和前台共用 Tailwind 实例 |
| 前台图标 | **lucide-react** | shadcn 默认 icon set |
| 前台表单 | **Inertia `useForm` hook** | 自动绑定 Laravel FormRequest 的 validation errors，无需 react-hook-form |
| 前台路由导航 | **Inertia `<Link>` + laravel/wayfinder** | Wayfinder 自动把 Laravel 路由生成**类型安全的 TS 函数**:`PostsController.show(id)` 代替字符串 `route('posts.show', {id})`,IDE 补全、类型检查、重命名路由 TS 侧自动报错 |
| 前台状态 | **Inertia props** | 服务端推数据为主;复杂客户端状态才用 Zustand |
| API tokens(v1.x) | **laravel/sanctum** | 未来开放 REST API 时启用;与 Fortify session auth 并存不冲突 |
| 编辑器 | **Filament RichEditor**（TipTap 底层） | 后台统一组件；数据库存 HTML 为主 + Markdown 导出字段 |
| 多语言 | **v1.0 包含** | 默认 zh_CN + en；`posts` / `pages` 走 translation 子表；前台 Inertia 共享 `locale` prop |
| 评论 | **v1.0 包含** | `comments` polymorphic 关联 post/page；前台 React 组件 + `useForm` 提交 |
| 默认存储 | **本地磁盘**（`local` disk） | S3/R2 作为 `.env` 切换项；通过 `spatie/laravel-medialibrary` 抽象 |

## 8. 前台目录结构

前台 React 代码归 `resources/js/`，后台 Filament 在 `app/Filament/`，**不**在根目录建 `web/`：

```
forge-cms/
├── app/
│   ├── Filament/
│   │   ├── Resources/               # 后台 CRUD 资源(Post, Page, User, Tag ...)
│   │   ├── Pages/                   # Dashboard / Settings
│   │   └── Widgets/                 # 仪表盘图表
│   ├── Http/
│   │   ├── Controllers/Web/         # 前台控制器，返回 Inertia::render
│   │   └── Requests/                # FormRequest 统一校验
│   └── Models/
├── resources/
│   ├── js/
│   │   ├── app.tsx                  # Inertia 客户端入口
│   │   ├── ssr.tsx                  # SSR 入口(Node 进程)
│   │   ├── Pages/                   # Inertia 约定：Inertia::render('Posts/Show') 对应此目录
│   │   │   ├── Home.tsx
│   │   │   ├── Posts/
│   │   │   │   ├── Index.tsx
│   │   │   │   └── Show.tsx
│   │   │   ├── Pages/
│   │   │   ├── Search.tsx
│   │   │   └── Auth/
│   │   ├── Layouts/
│   │   │   └── AppLayout.tsx        # 站点公共 Layout
│   │   ├── Components/              # 业务组件(PostCard, CommentTree, ...)
│   │   ├── components/ui/           # shadcn/ui copy-in(Button, Card, Dialog, Form, ...)
│   │   ├── hooks/                   # 自定义 React hooks
│   │   └── lib/
│   │       └── utils.ts             # shadcn 标配 cn()
│   ├── css/app.css                  # Tailwind 4 入口(@import "tailwindcss")
│   ├── views/
│   │   └── app.blade.php            # 唯一 Blade：Inertia 挂载点
│   └── lang/{zh_CN,en}/             # Laravel i18n 翻译
├── routes/
│   ├── web.php                      # 前台路由
│   └── console.php
└── public/build/                    # Vite 产物(bun run build 生成，不进 Git)
```

**关键点**:

- 前台 Page 组件在 `resources/js/Pages/` —— Inertia 约定,`Inertia::render('Posts/Show')` 映射到 `Pages/Posts/Show.tsx`
- shadcn copy-in 到 `components/ui/`(小写,shadcn 默认)
- 业务组件放 `Components/`(大写,和 Inertia Page 同层)
- 后台 Filament 在 `app/Filament/`,Filament 自动扫描,无需路由声明
- **唯一的 Blade 文件** `resources/views/app.blade.php` 只做 Inertia 挂载点,不写业务模板

## 9. 推荐 Laravel 包(社区共识)

### 9.1 必装(v1.0 MVP 范围)

| 包 | 用途 | 备注 |
|----|------|------|
| `inertiajs/inertia-laravel` | Inertia 服务端 adapter | 返回 `Inertia::render` |
| `laravel/octane` | FrankenPHP worker 模式 | 生产用 |
| `laravel/sanctum` | API tokens | v1.x API 会用 |
| `laravel/scout` | 搜索抽象层 | Meilisearch 驱动 |
| `meilisearch/meilisearch-php` | Scout 的 Meili 驱动实现 | |
| `laravel/horizon` | Redis 队列 dashboard + 监控 | |
| `spatie/laravel-permission` | 角色 + 权限 | |
| `bezhansalleh/filament-shield` | Filament + spatie/permission 自动桥接 | 为每个 Resource 生成权限项 |
| `filament/filament` | 管理后台核心 | |
| `spatie/laravel-medialibrary` | 媒体文件上传、转换、集合管理 | 替代手写 `media` 表;提供缩略图生成、storage 切换、模型关联 |
| `filament/spatie-laravel-media-library-plugin` | Filament 的 media-library 上传组件 | |
| `spatie/laravel-sluggable` | 自动从 title 生成 slug | |
| `spatie/laravel-sitemap` | 生成 sitemap.xml | |
| `spatie/laravel-activitylog` | 审计日志(谁改了什么) | |
| `spatie/laravel-backup` | 数据库 + storage 定时备份 | |

### 9.2 开发依赖(require-dev)

| 包 | 用途 |
|----|------|
| `laravel/pint` | 代码格式化(PSR-12 + Laravel style),官方 |
| `pestphp/pest` | 测试框架(2026 年社区默认,比 PHPUnit 写起来简洁) |
| `pestphp/pest-plugin-laravel` | Pest 的 Laravel 扩展 |
| `larastan/larastan` | PHPStan for Laravel,静态分析 |
| `rector/rector` + `driftingly/rector-laravel` | 自动重构升级 |
| `laravel/telescope` | 请求/查询/job 调试面板(仅 local 启用) |
| `laravel/pail` | 实时 tail 应用日志 |
| `barryvdh/laravel-ide-helper` | 生成 IDE 类型提示文件 |
| `spatie/laravel-ray` | 调试输出(可选,需 Ray 桌面 app) |

### 9.3 前端依赖(package.json)

生产:

```json
{
  "dependencies": {
    "@inertiajs/react": "^2.0",
    "react": "^19.0",
    "react-dom": "^19.0",
    "lucide-react": "latest",
    "class-variance-authority": "latest",
    "clsx": "latest",
    "tailwind-merge": "latest",
    "sonner": "latest",
    "cmdk": "latest",
    "date-fns": "latest",
    "zod": "latest"
  },
  "devDependencies": {
    "@vitejs/plugin-react": "latest",
    "@tailwindcss/vite": "^4.0",
    "tailwindcss": "^4.0",
    "laravel-vite-plugin": "latest",
    "vite": "^8.0",
    "typescript": "^5.0",
    "@types/react": "^19.0",
    "@types/react-dom": "^19.0",
    "@types/node": "^22.0"
  },
  "volta": {
    "node": "24.14.1"
  }
}
```

### 9.4 v1.x 再加的(不急)

| 包 | 场景 |
|----|------|
| `spatie/laravel-translatable` | 如果决定把 translations 合进主表 JSON(目前选独立表,用不到) |
| `laravel/pulse` | 应用性能监控(上线后再开) |
| `spatie/laravel-feed` | RSS/Atom feed |
| `spatie/laravel-newsletter` | 邮件订阅 |
| `spatie/laravel-honeypot` | 反垃圾评论的 honeypot 字段 |
| `akaunting/laravel-money` | 如果未来有收费功能 |
| `filament-notifications` | 后台通知中心 |

### 9.5 明确**不**用的

- ❌ `laravel-mix` —— Vite 已取代,官方已弃用
- ❌ `laravel/breeze` with Blade —— 我们用 Inertia+React starter
- ❌ `beyondcode/laravel-comments` —— 自建 `comments` 表,包依赖不稳定
- ❌ `spatie/laravel-comments` —— 付费包,MVP 不划算
- ❌ `jenssegers/agent` —— Laravel 11+ 已有原生 UserAgent 支持
- ❌ `predis/predis` —— 用更快的 phpredis 扩展(Dockerfile 已装)
- ❌ `barryvdh/laravel-debugbar` —— Telescope 功能覆盖且更现代
