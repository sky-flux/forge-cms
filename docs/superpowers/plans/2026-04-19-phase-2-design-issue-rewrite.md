# Phase 2: Design-Issue Doc Rewrite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Close 16 design problems flagged during the Phase 1 review across `docs/prd.md`, `docs/database.md`, `docs/laravel.md`, `docs/setup.md`, `docs/story.md`. Grouped into 7 commits by document + theme so each commit has a single tight reason-to-exist and a clean review surface.

**Architecture:** Pure markdown rewrites on the `docs/design-issue-rewrite` branch in the worktree at `.worktrees/docs-design-rewrite`. Every commit must leave the full test suite at `73 passed / 0 failed` (markdown doesn't touch code, but we still run the suite as a regression guard). Pint runs dirty for each commit for consistency with Phase 1 cadence.

**Tech context:** After Phase 1, the actual codebase has Filament 5.5, Shield v4, Sanctum, Reverb, full Spatie stack, Octane, Horizon, Telescope, Larastan, Rector. User model is `implements FilamentUser, HasMedia` with `HasRoles` + `HasApiTokens`. Admin panel at `/admin`, Horizon at `/horizon`, Telescope at `/telescope` — all three gated to `super_admin` role.

**Working directory for all tasks:** `/Users/martinadamsdev/workspace/forge-cms/.worktrees/docs-design-rewrite`

---

## Pre-flight Checklist

- [ ] `pwd` → ends in `.worktrees/docs-design-rewrite`
- [ ] `git branch --show-current` → `docs/design-issue-rewrite`
- [ ] `git log --oneline | head -1` → `6d03af7` (origin/main tip at plan start)
- [ ] Full suite: `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan test` → `73 passed (182 assertions), 0 failed`
- [ ] Working tree clean: `git status --short` → empty (after worktree setup commit if any)

---

## Shared rules for every task

1. **Full suite must stay green.** Markdown doesn't affect tests but we check anyway; if any failed appears, something else is broken and Phase 2 should BLOCK until it's understood.
2. **Pint runs dirty.** Markdown-only changes usually report `{"result":"pass"}` with nothing to format; run it regardless.
3. **No AI attribution.** No `Co-Authored-By: Claude`, no 🤖 lines.
4. **Touch ONLY the files the task lists.** Docs frequently cross-reference each other — if a task needs to fix a contradiction across two files, both files go in that task's scope; otherwise stay focused.
5. **Single authoritative decision per issue.** If an issue has two valid answers (e.g. "multi-language P0 or v1.x?"), this plan writes down one answer with rationale; subagents apply it uniformly across all mentions.

---

## Decisions baked into this plan

Before the subagents start rewriting, these are the non-obvious calls the plan makes. They're the kind of thing the rewrites will embed across multiple mentions. If any of these is wrong, stop Phase 2 and revise this header BEFORE executing.

| # | Issue | Decision | Why |
|---|---|---|---|
| D1 | `body_html` + `body_markdown` dual storage | **Drop `body_markdown`.** Single source = `body_html` produced by TipTap (Filament RichEditor). Markdown can be derived on export via server-side `league/html-to-markdown` if ever needed. | Avoids sync hazard; editor already produces HTML; markdown field was "legacy" hedge that never paid off. |
| D2 | SHA256(IP) called "GDPR 友好" | **Use HMAC-SHA256 with a `.env` `COMMENT_IP_HMAC_SECRET`**. Document that rotating the secret invalidates historical hashes. If HMAC-keyed hash isn't wanted, fall back to /24 (IPv4) / /64 (IPv6) truncation at the application layer. | Plain SHA256 of a 32-bit address is ~4B brute-forceable in seconds. HMAC-with-secret is the minimum real anonymisation. |
| D3 | `pages` lacks `is_comments_enabled` | **Add `is_comments_enabled boolean default true`** to the `pages` schema, mirroring `posts`. US-074 promises per-item toggle for anything comment-polymorphic. | Story said toggle works per-content-item; schema must match. |
| D4 | users `uuid` + `id` role unspecified | **Document:** internal FKs use `bigint id` (speed, index size). External URLs / public API expose `uuid`. Route model binding uses uuid via `getRouteKeyName`. | Common pattern but unwritten means inconsistent use downstream. |
| D5 | §5 index strategy mentions `slug` on `posts` | **Fix the index-strategy prose.** The canonical slug index is `(locale, slug) UNIQUE` on `post_translations`, not on `posts`. | Schema §3.3.1 is correct; §5 prose drifted. |
| D6 | Multi-language content **P0** | **Move to v1.x.** MVP is single-language (site defaults to zh_CN or user-chosen). UI locale switching stays (lang files already shipped in zh_CN / en). Content translation infra (`*_translations` tables, `/en/posts/...` routes, `hreflang`) defers. | Biggest scope reduction on the MVP critical path (~30%). Reduces risk to ship date without killing a requested feature — just delays it. |
| D7 | Comments system **P0 — keep, but scope down** | **Keep P0.** Strip: (a) Akismet integration (move to v1.x), (b) 3-level nesting limit (store flat + `parent_id`, render as nested up to 3 levels at the UI layer). Keep: honeypot + rate-limit, admin approval flow, guest vs. registered user differentiation. | Comments are a native-CMS expectation; dropping to v1.x would surprise product users. But the sub-features with weakest value per cost (Akismet key management, nesting enforcement) can wait. |
| D8 | §6 risk table "v1.0 先定 Blade" vs §7 decision "Inertia + React + SSR" | **Keep Inertia + React + SSR (§7).** The starter kit `laravel new --react` already set this up and Phase 1 installed the full stack (wayfinder, Inertia v3, shadcn/ui, Tailwind 4). Drop the "Blade fallback" risk-mitigation row from §6 — it contradicts reality. | §7 is what actually landed in code; §6 is stale drafting. |
| D9 | Editor dual-format HTML + Markdown | **TipTap HTML only.** Markdown is not a user-facing mode. Import/export via optional server-side conversion if a user asks. | Editor is Filament RichEditor (TipTap); there's no Markdown editing UI in the plan anyway. |
| D10 | US-032 media cleanup via HTML body scan | **Rewrite story scope.** "Find media items with no `model_type/model_id` association" is what medialibrary actually tracks. Orphan detection is already a feature of the package. Drop the impossible "scan Post/Page HTML for references" ambition. | Matches what's achievable without a custom HTML parser that would have to understand Filament's img tag shape, srcset, CDN rewrites, etc. |
| D11 | US-042 search lacks sync pipeline | **Document the pipeline:** `Post` model adds `Searchable` trait → Scout's observer enqueues index job on `saved` / `deleted` → Horizon worker runs `scout:import` in background. Index prefix `forge_cms_posts`. Soft-deleted posts auto-removed. | Not introducing new tech, just spelling out the mechanics everyone assumes. |
| D12 | US-062 hreflang | **Defer to v1.x with D6.** Remove US-062 from v1.0 priority matrix. Replace with a note that `hreflang` ships when content translation ships. | No multi-language content in v1.0 → no `hreflang` scenarios. |
| D13 | US-001 "5 fail / 15 min" vs Fortify default 5/min | **Align to 5 attempts / 5 minutes throttle.** Change the story edge-case line. No config override needed; Fortify's default is close enough and per-username throttling is preserved. | Avoid custom rate-limiter code for a cosmetic window difference. |
| D14 | `php artisan reload` | **Empirically verify.** Run `env PATH=... php artisan list | grep reload` in the worktree. If the command doesn't exist (likely — I suspect it's aspirational), replace §14.2 with the real Laravel 13 recipe: `php artisan optimize && php artisan horizon:terminate && php artisan octane:reload`. | Don't ship a fictional command in the deployment runbook. |
| D15 | Repository pattern "唯一的例外" too absolute | **Soften to:** "Prefer Eloquent scopes / dedicated query objects. Legitimate cases for a repository layer exist — swapping an ORM, event-sourcing, shared code across data sources — don't add one reflexively but don't forbid it either." | Room for judgment in the rule. |
| D16 | setup.md §1.0 Mac-only Herd Lite, README says Linux works | **Add a Linux path** to §1.0: `curl -fsSL https://php.new/install/linux \| bash` + a note that Homebrew on Linux / system php8.5 package also work. | README shouldn't over-promise what setup.md excludes. |

---

## Task breakdown (7 commits)

### Task 27: `docs(db): single-source body storage + harden IP hash + align polymorphic comments`

**Files:**
- Modify: `docs/database.md`

**Subsections touched:**
- §3.1 users — add note on id vs uuid roles (D4)
- §3.3 posts / §3.3.1 post_translations — drop `body_markdown` field + any example code (D1)
- §3.4 pages — add `is_comments_enabled` boolean field (D3)
- §3.10 comments — rewrite `guest_ip_hash` column description to use HMAC, add `.env` example (D2)
- §5 index strategy — fix slug UNIQUE mention from `posts` to `post_translations` (D5)
- §7 Seed — no changes needed, but verify nothing references `body_markdown`

**Concrete edits:**

1. **§3.1** after the users column table, append a new paragraph:
   > **`id` vs `uuid`**:`id` 是**内部 FK** 用(外键引用 / JOIN 性能);`uuid` 是**外部标识**用(URL / 公开 API / 不暴露自增计数)。`User` 模型的 `getRouteKeyName()` 返回 `'uuid'`,路由模型绑定走 uuid。同样的双主键策略适用于所有有"外部可引用"语义的表(posts / pages / media)。

2. **§3.3.1** `post_translations` table — remove the `body_markdown` row from the column table. Remove it from the Schema::create migration example at the bottom of §3.3.1. Keep only `body_html`.

3. **§3.4** `pages` field list — add to the inline list:
   > `is_comments_enabled | boolean | default true | 单页级别评论开关(story.md US-074)`

4. **§3.10** the `guest_ip_hash` row description — change from:
   > `guest_ip_hash | varchar(64) | NULL | IP 的 SHA256(GDPR 考虑,不存明文)`
   
   to:
   > `guest_ip_hash | varchar(64) | NULL | HMAC-SHA256(IP, COMMENT_IP_HMAC_SECRET) — 配合 `.env` 里的 secret 做不可逆化;轮换 secret 会作废历史比对但保留记录。`

   And below the table, in the "设计说明" bullet list, rewrite:
   > **IP 存哈希不存明文**:GDPR 合规,仅用于同 IP 频率限制和屏蔽,不可逆
   
   to:
   > **IP 存 HMAC 而非明文**:GDPR 合规 & 防彩虹表反查。生成:`hash_hmac('sha256', $ip, config('forge.comments.ip_hmac_secret'))`,`.env` 里设 `COMMENT_IP_HMAC_SECRET` 长随机值。仅用于同 IP 频率限制和屏蔽;轮换 secret 会作废历史比对但不丢记录。若严格匿名不需要历史比对,替换为 `/24` (IPv4) / `/64` (IPv6) 截断。

5. **§5** — find the bullet that says something like "文章详情页:按 slug → `slug` UNIQUE 索引", change to:
   > 文章详情页:按 `(locale, slug)` → 索引落在 `post_translations` 表(`(locale, slug) UNIQUE` 见 §3.3.1)

Verify no stray references to `body_markdown` anywhere in the file after edits: `grep -n body_markdown docs/database.md` → should return nothing.

**Steps:**
- [ ] 27.1 Read `docs/database.md` fully
- [ ] 27.2 Apply all 5 edits above
- [ ] 27.3 Grep verify: no `body_markdown`, no standalone `guest_ip_hash.*SHA256` anywhere
- [ ] 27.4 Pint (no-op on markdown): `vendor/bin/pint --dirty --format agent`
- [ ] 27.5 Full suite: `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan test | grep -E "Tests:"` — `73 passed`
- [ ] 27.6 Commit:

```
docs(db): single-source body storage + harden ip hash + comment-gate pages

Addresses 5 design gaps found in Phase 2 review of database.md:

- §3.3.1 drop `body_markdown` column. TipTap editor produces HTML;
  dual storage creates a two-source-of-truth hazard. On-demand
  Markdown export can round-trip via `league/html-to-markdown`
  server-side if a future user asks for it.
- §3.10 rewrite `guest_ip_hash` description: SHA256(IP) is trivially
  reversible for IPv4 (4.3B address space, seconds with a rainbow
  table). Replace with HMAC-SHA256 keyed by `.env`
  `COMMENT_IP_HMAC_SECRET`, document secret-rotation semantics, and
  offer /24-/64 truncation as the strict-anonymity alternative.
- §3.4 pages schema gains `is_comments_enabled boolean default true`
  to match story.md US-074's per-item comment toggle contract —
  previously only posts had the column, so page comments couldn't
  be disabled.
- §3.1 append a clarifying paragraph distinguishing `id` (internal
  FK / JOIN) from `uuid` (external URL / public API surface). The
  dual-primary-key pattern was already implied but not written down.
- §5 fix the high-frequency-queries bullet that claimed a `slug`
  UNIQUE index on `posts`; the actual index lives on
  `post_translations (locale, slug)` per §3.3.1.
```

---

### Task 28: `docs(prd): rescope multi-language content from P0 to v1.x`

**Files:**
- Modify: `docs/prd.md`

**Subsections touched:**
- §3.1 MVP feature matrix — move "多语言内容" from P0 to v1.x
- §3.2 v1.x scope — add multi-language content row
- §4 non-functional — keep UI i18n (zh_CN / en switcher already in lang/), clarify scope boundary
- §7 已锁定技术决策 — update "多语言" row from "v1.0 包含" to "v1.x 规划"
- §8 前台目录结构 — keep resources/js/pages structure, no change

**Concrete edits:**

1. **§3.1 Feature matrix table:** delete the row:
   > `| **多语言内容** | ⭐ 支持多语言文章/页面;默认中英双语,可扩展 | P0 |`

2. **§3.1:** in the same table, edit "SEO 基础" row — remove `hreflang(多语言)` from its content since hreflang goes with D12:
   > `| **SEO 基础** | meta title/description/OG、sitemap.xml、robots.txt | P0 |`

3. **§3.2 v1.x feature list:** add two new rows at the top:
   > - 多语言内容(独立 `*_translations` 表 + locale routing + fallback)
   > - `hreflang` 标签自动生成(随多语言内容一起上)

4. **§4 非功能需求:** rewrite the "国际化" row to:
   > `| **国际化** | UI 语言 v1.0 支持 zh_CN / en 切换(`lang/` 已装);**内容多语言 v1.x 实现** |`

5. **§7 已锁定技术决策 table:** update the "多语言" row — change the `选定` column from "**v1.0 包含**" to "**v1.x 规划**", update the `说明` to acknowledge the scope move:
   > `| 多语言 | **v1.x 规划** | v1.0 先单语言(默认 zh_CN,可配置);内容翻译、locale routing、hreflang 随 v1.x 一并上。UI 文案仍然支持 zh_CN / en 切换(lang 文件夹已就位)。 |`

**Steps:**
- [ ] 28.1 Read `docs/prd.md` §3, §4, §7
- [ ] 28.2 Apply 5 edits
- [ ] 28.3 Grep verify: `grep -c "多语言" docs/prd.md` — count is sane (mentions of v1.x multi-language remain, but no P0 claim)
- [ ] 28.4 Pint + full suite → 73 passed
- [ ] 28.5 Commit:

```
docs(prd): rescope multi-language content from P0 to v1.x

Multi-language content was sitting in the MVP critical path with
scope that reads innocuous ("支持多语言文章/页面,默认中英双语") but
actually encodes four separate subsystems: four `*_translations`
tables, locale-aware routing, `hreflang` generation, translation
management UI. Shipping it inside v1.0 crowds out work on the
core CMS features that READ as MVP (Posts, Pages, Categories,
Media).

Move content-level multi-language to v1.x. v1.0 keeps:
- UI i18n (Filament admin + front-end) via zh_CN / en locale
  switcher (lang/ already populated by the starter kit)
- Single-language content, with the locale choice exposed as a
  site-wide `APP_LOCALE` env

Move to v1.x:
- `*_translations` tables for posts / pages / categories / tags
- Locale-prefixed routing (`/en/posts/...`)
- `hreflang` meta tag generation (US-062, rescoped together)

§3.1 feature matrix drops the "多语言内容" P0 row; §3.2 adds it as a
v1.x bullet; §4 rewrites the "国际化" non-functional row to split UI
i18n (v1.0) from content i18n (v1.x); §7 flips the "多语言" decision
row to "v1.x 规划" with the rationale inline.

Blocks story US-060 / US-061 / US-062 / US-063 — updated in the
follow-up story.md rewrite.
```

---

### Task 29: `docs(prd): constrain comments system to MVP-essential features`

**Files:**
- Modify: `docs/prd.md`

**Subsections touched:**
- §3.1 MVP feature matrix — tighten comment row description
- §3.2 v1.x feature list — add "Akismet 集成" as v1.x row
- §7 decision table — update "评论" row wording

**Concrete edits:**

1. **§3.1 评论系统 row:** change from:
   > `| **评论系统** | ⭐ Post 下评论、管理员审核、反垃圾(honeypot + Akismet 可选) | P0 |`
   
   to:
   > `| **评论系统** | Post / Page 下评论、管理员审核、honeypot + 速率限制反垃圾(无 Akismet) | P0 |`

2. **§3.2 v1.x list:** add:
   > - Akismet 集成(v1.0 仅靠 honeypot + 速率限制,Akismet key 管理、故障降级等都留给 v1.x)

3. **§7 已锁定技术决策 table:** update 评论 row `说明` column:
   > `评论 | **v1.0 包含** | Post / Page polymorphic 关联,前台 React 组件 + useForm 提交。反垃圾 v1.0 只走 honeypot + 速率限制(honeypot 包已装);Akismet 第三方 API 集成 v1.x 再加。嵌套回复在 UI 层最多 3 级展示,DB 层 flat + parent_id。 |`

**Steps:**
- [ ] 29.1 Read §3.1 / §3.2 / §7 of `docs/prd.md`
- [ ] 29.2 Apply 3 edits
- [ ] 29.3 Pint + full suite → 73 passed
- [ ] 29.4 Commit:

```
docs(prd): constrain comments system to MVP-essential features

Comments stay P0 — CMS readers expect a comment box — but the
original matrix row bundled Akismet into MVP. Akismet has its own
v1.x concerns (API key management in the admin UI, graceful
degradation when akismet.com is down, spam-review pipeline). None
of that is load-bearing for shipping a readable site with basic
moderation.

Trim the MVP comment scope to what the installed stack already
covers:
- Post / Page polymorphic `comments` table (database.md §3.10)
- Admin approval via a pending/approved/spam/trash status column
- Honeypot (spatie/laravel-honeypot — installed Phase 1 Task 17)
  plus Laravel's built-in rate limiter on the POST endpoint
- Nested replies DB-wise (flat + parent_id); 3-level indent is a
  render-time decision, not a schema constraint

§3.2 gains an "Akismet 集成" bullet for v1.x. §7 decision table
clarifies the split.

Story rewrites in Task 32 drop US-075 (Akismet) from v1.0's P2
list and put it in a v1.x section.
```

---

### Task 30: `docs(prd): resolve frontend stack contradiction (§6 vs §7)`

**Files:**
- Modify: `docs/prd.md`

**Subsections touched:**
- §6 风险与缓解 — drop or rewrite the "Blade fallback" row
- §7 decision table — confirm Inertia + React + SSR is the committed path

**Concrete edits:**

1. **§6 risk table:** find the row:
   > `| 前端栈选型拉锯(Blade vs Inertia vs Livewire) | 中 | v1.0 先定 Blade,Inertia 作 v1.x 备选 |`
   
   Replace with:
   > `| Inertia/React 包深度绑定后期难迁移 | 低 | Controller 返回 `Inertia::render` 是抽象点;底层前端框架切换(React → Vue)在 Controller 契约上几乎无感。风险评估自 `--react` starter 实装后已下降至"低"。 |`

2. **§7 decision table 前台渲染 row:** drop any "Inertia v2" typo (current install is v3) if present, confirm version:
   > `前台渲染 | **Inertia.js v3 + React 19 + SSR** | Laravel Controller 返回 `Inertia::render`,Node SSR 产出初始 HTML,浏览器 hydrate 后走 SPA。starter 默认装,Phase 1 未改动。 |`

**Steps:**
- [ ] 30.1 Read §6 / §7
- [ ] 30.2 Apply 2 edits
- [ ] 30.3 Grep: `grep -n "Blade" docs/prd.md` — any remaining mentions should be in the context of "唯一 Blade:Inertia 挂载点" (§8 directory structure), not as a frontend fallback option
- [ ] 30.4 Pint + full suite → 73 passed
- [ ] 30.5 Commit:

```
docs(prd): resolve frontend stack contradiction (§6 vs §7)

§6 risk table carried a stale row saying "v1.0 先定 Blade,Inertia
作 v1.x 备选". §7 decision table says the committed path is Inertia
+ React 19 + SSR. Phase 1 landed that full stack via the `--react`
starter — Blade fallback never happened.

Replace §6 row with a realistic residual risk: framework-level
churn is cheap because Controllers return `Inertia::render(...)`
regardless of which JS view library the frontend uses. Risk
rating drops to 低 — the decision has been validated in code.

§7 row updated from "Inertia.js v2" to "Inertia.js v3" (the actual
installed version). The sole remaining Blade file is the Inertia
mount point at `resources/views/app.blade.php` (§8), which is not
a "Blade frontend" in the CMS sense.
```

---

### Task 31: `docs(prd,story): unify editor as TipTap WYSIWYG, drop Markdown dual-format`

**Files:**
- Modify: `docs/prd.md`
- Modify: `docs/story.md`

**Changes:**

1. **prd.md §3.1 编辑器 row:** change from:
   > `| **编辑器** | Filament 内置 RichEditor(TipTap 底层)+ Markdown 导入导出 | P0 |`
   
   to:
   > `| **编辑器** | Filament RichEditor(TipTap 底层,WYSIWYG HTML 输出) | P0 |`

2. **prd.md §7 编辑器 row:** update to:
   > `编辑器 | **Filament RichEditor**(TipTap 底层) | 后台统一组件;数据库单源 HTML 存储。Markdown 导入导出等流转需求留到 v1.x,可选接入 `league/html-to-markdown` 服务端渲染。|`

3. **story.md US-011 Real-time Preview:** DELETE this story entirely. TipTap is WYSIWYG — there's no separate preview pane. Add a single-line note in its place:
   > **US-011 Markdown real-time preview**:**已撤销**。编辑器改为 TipTap WYSIWYG 模式,所见即所得,不再存在独立预览窗口。

4. **story.md Story 优先级矩阵 P1 row:** remove `011` from the P1 list of story IDs. If it becomes an empty gap, the line is still coherent.

**Steps:**
- [ ] 31.1 Read relevant sections of both files
- [ ] 31.2 Apply 4 edits across both
- [ ] 31.3 Pint + full suite → 73 passed
- [ ] 31.4 Commit:

```
docs(prd,story): unify editor as tiptap wysiwyg, drop markdown dual format

prd.md §3.1 listed the editor as "Filament RichEditor + Markdown
导入导出" and story US-011 promised a real-time Markdown preview
window. Both are at odds with the installed stack: Filament
RichEditor is a TipTap WYSIWYG — it renders as-you-type; there is
no separate preview. There's also no Markdown editor mode
anywhere in the plan.

Resolve:
- prd.md §3.1 / §7 editor rows pinned to "Filament RichEditor
  (TipTap, WYSIWYG, HTML output)". Markdown import/export becomes
  an optional v1.x workflow via `league/html-to-markdown`
  server-side.
- story.md US-011 withdrawn (retained as a "已撤销" placeholder
  with one-line note). Removed from the P1 priority matrix.

Pairs with Task 27's `body_markdown` column drop — single source
of truth is HTML everywhere.
```

---

### Task 32: `docs(story): align user stories with data-model + scope realities`

**Files:**
- Modify: `docs/story.md`

**Addresses:** D10 US-032 media cleanup, D11 US-042 scout pipeline, D12 US-062 hreflang defer, D13 US-001 rate limit, D6 cross-ref (deferred multi-language stories).

**Concrete edits:**

1. **US-001 边界:** change:
   > 连续 5 次失败锁定 15 分钟;记住我勾选后 token 有效期 30 天。
   
   to:
   > 连续 5 次失败节流 5 分钟(Fortify 默认 throttle);记住我勾选后 token 有效期 30 天。

2. **US-032 "清理未使用媒体":** rewrite completely. Replace with:
   > **US-032 清理孤儿媒体**
   > ```
   > 作为:Admin
   > 当:在"媒体库"点"清理孤儿媒体"
   > 那么:系统扫描 `media` 表中 `model_type` + `model_id` 为 NULL(或指向不存在的模型)的记录;预览列表后确认删除
   > ```
   > **边界**:只检测 medialibrary 追踪的未关联记录;**不**扫描 Post/Page body HTML 正文中的 `<img>` 引用(HTML 可能含 CDN 重写、srcset、Filament 改写后路径,无可靠解析)。删除后释放 storage 磁盘,不可恢复。

3. **US-042 全文搜索:** after the main Given-When-Then block, add a new paragraph:
   > **同步管线**:`Post` 模型用 `Searchable` trait(laravel/scout 已装);Scout observer 监听 `saved` / `deleted` 事件,通过 Redis 队列(Horizon)异步发送索引任务到 Meilisearch。Meilisearch index 名 `forge_cms_posts`,按 `locale` 字段做过滤(v1.x 多语言启用后再加)。初次/强制重建:`php artisan scout:import "App\Models\Post"`。

4. **US-060 / US-061 / US-062 / US-063 (multi-language group):** move all four stories to a new section "§10 v1.x 延期故事" at the end of the file. Keep the Given-When-Then blocks intact; just move them and add a note under the new §10 header:
   > 以下故事在 Phase 2 设计评审后从 v1.0 P0 移到 v1.x(详见 prd.md §3.2)。本节保留完整描述作为未来接入依据。

5. **Story 优先级矩阵 (P0 / P1 / P2) rows:** remove US-060 / US-061 / US-062 / US-063 / US-075 from the v1.0 priority table. US-032 remains but is now re-scoped (it's still P2 because orphan cleanup is a nice-to-have).

**Steps:**
- [ ] 32.1 Read `docs/story.md` fully (it's about 350 lines)
- [ ] 32.2 Apply all 5 edits
- [ ] 32.3 Pint + full suite → 73 passed
- [ ] 32.4 Commit:

```
docs(story): align user stories with data-model + scope realities

Four cross-cutting fixes:

1. US-001 login throttle: change "5 次失败锁定 15 分钟" to "5 次
   失败节流 5 分钟" — Fortify's default rate limiter is
   `throttle:5,1` (5 per minute). The 15-min window would require
   a custom limiter implementation for no real product benefit.

2. US-032 media cleanup: rewrite from "scan Post/Page HTML for
   references" (unreachable — HTML may contain Filament-rewritten
   paths, CDN srcsets, etc.) to "find media rows whose
   model_type/model_id is NULL or points at a deleted model",
   which is what medialibrary actually tracks. Matches the API
   surface of the package.

3. US-042 full-text search: document the Scout → Redis → Horizon
   → Meilisearch sync pipeline explicitly. Was assumed but never
   written, so the next implementer had to guess.

4. US-060 / US-061 / US-062 / US-063 (multi-language group):
   moved to a new "§10 v1.x 延期故事" section mirroring prd.md
   §3.2's P0 → v1.x rescope in Task 28. Stories retained
   verbatim so they drop straight back in when v1.x starts.
   Priority matrix updated to remove them from v1.0 rows.

US-075 (Akismet) also moved to §10 per Task 29's comment-scope
trim.
```

---

### Task 33: `docs(laravel,setup): fix absolutes + platform claims`

**Files:**
- Modify: `docs/laravel.md`
- Modify: `docs/setup.md`

**Addresses:** D14 reload command, D15 repository absolute, D16 Linux path.

**Concrete edits:**

1. **laravel.md §14.2 "部署后用 reload 重启 worker":** first run:
   ```
   env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan list | grep -i reload
   ```
   Record the output. Based on what actually exists:
   - If `reload` is a real top-level command → keep §14.2 as-is
   - If only `octane:reload` / `horizon:terminate` exist (likely) → replace §14.2 with the per-worker recipe:
     > ### 14.2 部署后重启 worker
     > 
     > Laravel 13 没有统一的 `artisan reload`(曾经的提案未合并)。按各长驻进程各自的 reload 信号处理:
     > 
     > ```bash
     > php artisan optimize                  # 等价 config / event / route / view cache
     > php artisan octane:reload             # 重启 FrankenPHP worker
     > php artisan horizon:terminate         # Horizon 自己会重启
     > # Reverb 用 supervisord / systemd 管理,由平台 SIGTERM 触发
     > ```
     > 
     > 部署脚本里这三条按顺序跑即可。

2. **laravel.md §3.3 Repository 模式:** replace the line:
   > 唯一的例外:切换底层 ORM(几乎不可能发生) 或 跨多个数据源。
   
   with:
   > 例外场景(合理引入 Repository 层):多租户分数据库、事件溯源架构、跨多个存储系统(比如 Postgres + Elasticsearch 的双写)、或底层 ORM 切换(虽少但不是零)。核心原则:**不要反射性地加一层**,但遇到上述场景也不强行 "Eloquent only"。

3. **setup.md §1.0 Herd Lite:** after the Mac `curl | bash` block, add a sidebar section:
   > #### Linux 等价路径
   > 
   > ```bash
   > # 官方 Linux 脚本(APT / dnf / pacman 自动识别)
   > /bin/bash -c "$(curl -fsSL https://php.new/install/linux)"
   > source ~/.bashrc   # 或 ~/.zshrc
   > ```
   > 
   > 也可以用系统包管理器:`apt install php8.5 php8.5-cli php8.5-common composer` / `dnf install php composer` 等,然后 `composer global require laravel/installer`。验证命令(`php --version`、`composer --version`、`laravel --version`)和 Mac 完全一致。
   > 
   > 本项目设计为跨平台;README 声明的 "Linux works too, paths may differ" 兑现路径就是这一段。

**Steps:**
- [ ] 33.1 Run `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan list | grep -i reload` — decide whether §14.2 keeps its current form or gets the multi-command recipe. Record the decision in the task's report.
- [ ] 33.2 Apply the reload edit (either keep or replace)
- [ ] 33.3 Apply the Repository softening
- [ ] 33.4 Apply the Linux path section
- [ ] 33.5 Pint + full suite → 73 passed
- [ ] 33.6 Commit:

```
docs(laravel,setup): fix absolutes + platform claims

Three doc accuracy fixes:

- laravel.md §14.2 `php artisan reload`: empirically verified —
  [either: the command exists and §14.2 stays; OR: it doesn't and
  §14.2 is rewritten to the optimize → octane:reload →
  horizon:terminate three-step recipe.] Deployment scripts that
  copy-pasted the old one-liner would have failed in production;
  §14.2 is now load-bearing accurate.
- laravel.md §3.3 Repository pattern: "唯一的例外:切换底层 ORM
  (几乎不可能发生)" was too absolute. Multi-tenant DB routing,
  event sourcing, cross-storage dual-writes are all legitimate
  Repository scenarios. New wording keeps the "don't reflexively
  add a layer" spirit but explicitly allows judgment.
- setup.md §1.0: add a Linux-equivalent install path
  (`curl -fsSL https://php.new/install/linux`) plus apt/dnf
  fallback. README's "Linux works too, paths may differ" claim
  now has a referenceable footnote.
```

---

## Self-review checklist

**1. Spec coverage:**
- D1 body_markdown — Task 27 ✓
- D2 IP hash — Task 27 ✓
- D3 pages comment toggle — Task 27 ✓
- D4 uuid/id role — Task 27 ✓
- D5 slug index — Task 27 ✓
- D6 multi-language → v1.x — Task 28 (+ story refs in 32) ✓
- D7 comments scope trim — Task 29 ✓
- D8 frontend stack — Task 30 ✓
- D9 editor dual format — Task 31 ✓
- D10 US-032 media cleanup — Task 32 ✓
- D11 US-042 scout pipeline — Task 32 ✓
- D12 US-062 hreflang defer — Task 32 ✓
- D13 US-001 rate limit — Task 32 ✓
- D14 artisan reload — Task 33 ✓
- D15 Repository absolute — Task 33 ✓
- D16 Linux path — Task 33 ✓

**2. Placeholder scan:** every task has concrete before/after snippets or explicit reference to the exact lines to change. No "TBD" or "figure it out".

**3. Consistency across tasks:**
- "move to v1.x" decision for multi-language appears in Task 28 (prd.md) and Task 32 (story.md §10). Both tasks agree on the subsystem list.
- "drop body_markdown" in Task 27 pairs with "drop Markdown preview" in Task 31 — both anchored on "TipTap is HTML-only".
- Commit naming consistent: `docs(<scope>): <tense>`. Scope values: `db` / `prd` / `prd,story` / `laravel,setup` / `story`.

**Known risks:**
1. **Task 33.1 is dynamic** — the `artisan list | grep reload` outcome determines the §14.2 rewrite shape. Subagent must report the actual command output in their self-review so the next reviewer can verify.
2. **Task 32 moves four stories to a new §10** — the existing priority matrix tables have to be updated in the same commit, otherwise the matrix references deleted story IDs.
3. **No code changes, but full suite still runs** — if a markdown edit somehow breaks docs that are loaded at test time (unlikely but possible if a test references a doc file path), the subagent must report it immediately.

---

## Execution handoff

**Plan complete and saved to `docs/superpowers/plans/2026-04-19-phase-2-design-issue-rewrite.md` (inside worktree).**

**Execution mode:** Subagent-Driven Development (same as Phase 1). One subagent per task, full suite + pint verification, commit, move on. Two-stage review (spec + code quality) is mostly ceremonial for pure markdown edits — I'll run it on Task 27 (largest, touches the canonical schema doc) and Task 32 (largest story rewrite), skip for the shorter ones to keep throughput.

**Target:** 7 commits on `docs/design-issue-rewrite`. Final state: Phase 2 branch ready for PR back to `main`, full suite 73 passed at every commit, design issues closed.
