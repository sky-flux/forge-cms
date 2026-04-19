# 系统 → 缓存 Page — Spec (Rich Redesign)

**Date:** 2026-04-19
**Status:** Approved (reviewer checklist integrated — 6 fixes applied)
**Problem:** 当前 `/console/cache` 只有 4 个用裸 HTML + Tailwind 类写的按钮,CSS 未编译导致视觉塌缩(见用户截图),功能单薄。

## Goals

1. **视觉修复**:用 Filament 5 原生组件(`getHeaderActions()` / `fi-section` 卡片),避免依赖 `bun run build` 后才能生效的自定义 Tailwind。
2. **监控可见**:暴露 Redis / Opcache 实时状态,让管理员无需 SSH 就能判断缓存健康。
3. **操作覆盖更全**:增补 `event:clear`、`opcache:reset`。敏感操作加二次确认。
4. **可追溯**:每次清理写 `spatie/laravel-activitylog`,UI 上显示最近 10 条记录。
5. **生产安全**:`flushApp` 带 modal 确认 + 醒目警告(会丢字典/Sitemap/Feed 缓存)。

## Non-Goals

- 不做跨节点缓存同步(Reverb 级 real-time broadcast)
- 不做细粒度 tag/key 浏览(`redis-cli KEYS *` 式工具)— 太低阶
- 不做预热(`config:cache` / `route:cache`)— 生产部署流程问题,不是运行时按钮
- 不做 Horizon/Pulse 链接(那些是独立 vendor dashboard,应走外链,非本页内嵌)

## UX 布局

```
┌── Page header (Filament 原生) ────────────────────────────────┐
│ 缓存              [Config][Route][View][Event] [重置Opcache] │
│                                            [⚠️ 清空应用缓存]  │
└──────────────────────────────────────────────────────────────┘

┌── Stats(4 列响应式 grid) ──────────────────────────────────┐
│ 缓存后端         Opcache        清理操作累计     本次会话     │
│ redis · ●       启用           42 次            刚刚 10:30:12 │
│ 内存 124MB      内存 52/128MB   最近 5m 前                   │
│ Keys 3,421      脚本 412                                     │
│ Uptime 42 天    命中率 98.4%                                 │
└──────────────────────────────────────────────────────────────┘

┌── 警告横幅 ─────────────────────────────────────────────────┐
│ ⚠️ 清空应用缓存会丢失 Cache::remember(字典/Sitemap/Feed)      │
│    操作已记录到 活动 页面                                      │
└──────────────────────────────────────────────────────────────┘

┌── 最近 10 次清理(表格,从 activity_log) ──────────────────┐
│ 时间         操作              操作人                         │
│ 5m ago      cache:flush        admin@forge-cms.example.com   │
│ 12m ago     config:clear       admin@forge-cms.example.com   │
│ ...                                                           │
└──────────────────────────────────────────────────────────────┘
```

## 功能清单

| 按钮 | 命令 | Level | 确认? |
|---|---|---|---|
| 清空 Config | `config:clear` | 常用 | 否 |
| 清空 Route | `route:clear` | 常用 | 否 |
| 清空 View | `view:clear` | 常用 | 否 |
| 清空 Event | `event:clear` | 常用 | 否 |
| 重置 Opcache | `opcache_reset()`(若可用) | 偶用,生产需要 | **是 — modal 说明"下一次请求需重编译,高 QPS 可能延迟尖峰"**(review 补) |
| ⚠️ 清空应用缓存 | `Cache::flush()` | 危险 | **Modal 二次确认** |

## Stats 数据源

### 缓存后端(phpredis 扁平结构)

项目 `REDIS_CLIENT=phpredis` → `Redis::info()` 返回**扁平** keys,不是 predis 的嵌套分组:

```php
$driver = config('cache.default');
if ($driver === 'redis') {
    try {
        $info = Redis::connection('cache')->info();

        // phpredis: root-level 'used_memory_human'. predis: nested 'Memory.used_memory_human'. Handle both.
        $memory = $info['used_memory_human'] ?? $info['Memory']['used_memory_human'] ?? 'n/a';
        $uptime = (int) ($info['uptime_in_seconds'] ?? $info['Server']['uptime_in_seconds'] ?? 0);

        // phpredis: 'db0', 'db1' etc. are root-level strings "keys=N,expires=X,avg_ttl=Y".
        // predis: 'Keyspace' => ['db0' => ['keys' => N, ...]].
        $keys = 0;
        foreach ($info as $k => $v) {
            if (is_array($v) && isset($v['keys'])) {
                $keys += (int) $v['keys'];
            } elseif (is_string($v) && str_starts_with((string) $k, 'db')) {
                $parsed = [];
                parse_str(str_replace(',', '&', $v), $parsed);
                $keys += (int) ($parsed['keys'] ?? 0);
            } elseif ($k === 'Keyspace' && is_array($v)) {
                foreach ($v as $db) { $keys += (int) ($db['keys'] ?? 0); }
            }
        }
    } catch (\Throwable $e) {
        return ['driver' => 'redis', 'connected' => false, 'error' => $e->getMessage()];
    }
}
```

失败时优雅降级:`['driver' => 'redis', 'connected' => false, 'error' => $msg]`。BGSAVE 等场景下 `info()` 可能慢,依赖 phpredis 客户端超时兜底 — 不额外加 timeout。

### Opcache
```php
function_exists('opcache_get_status') 且 opcache_get_status(false) 非 false
→ memory_usage.used_memory / free_memory
→ opcache_statistics.num_cached_scripts / opcache_hit_rate
```

### 操作历史
```php
Activity::where('log_name', 'cache')
    ->with('causer')
    ->latest('id')               // ← PK 已索引;表只有 log_name 单列 index,latest('created_at') 会 filesort
    ->limit(10)
    ->get();
```

每次清理调用:
```php
activity('cache')->causedBy(auth()->user())->event($cmd)->log($title);
```

**Causer null-safety**:user 被 forceDelete 后 `$activity->causer` 为 null,blade 用 `?->email ?? '系统'`。

## Octane 安全

- Stats 读 Redis 在 HTTP worker 内执行,无 singleton 累积
- `$lastClearedAt` 是 public Livewire 属性,随组件实例销毁,不跨 worker 污染
- `activity()->log()` 每次新建 Activity,无状态泄漏

## 测试矩阵(新增/保留)

- ✅ `flushApp` 清空 Cache(现有)
- ✅ guest redirect 到 `/console/login`(现有)
- 🆕 `flushApp` 写 `log_name='cache'`、`event='cache:flush'` 的 activity
- 🆕 `clearEvent` 调用 `Artisan::call('event:clear')` + 写 activity
- 🆕 `resetOpcache` probe 可覆盖(通过 protected `opcacheStatus()` override);opcache 不存在时 warning notification
- 🆕 `getCacheBackendStats()` 在非 Redis 驱动时返回 `['driver' => 'array']`(test env)
- 🆕 `getRecentActionsStats()` 返回 total + last_at
- 🆕 **非 super_admin 已登录** 访问 → 403(non-guest, non-admin)
- 🆕 `flushApp` action 存在 **modal** 二次确认(用 `assertActionExists` + `assertActionRequiresConfirmation`)

## 实现约束

- Filament 5 原生:`getHeaderActions(): array` 返回 `Action` 列表
- 无新 migration(activity_log 表已存在)
- `declare(strict_types=1);` 全覆盖
- `vendor/bin/pint --dirty --format agent` 合规
- 测试用 `php artisan test --compact --filter=CachePage`

## 验收

- `/console/cache` 视觉不再塌缩;按钮可见、有图标、危险按钮是红色 + modal
- Stats 正确显示 Redis + Opcache 信息;任一不可用时优雅降级(不 500)
- 每次清理在 `活动` 页面看得到
- 所有 CachePage tests 绿;无回归

## Out of scope(留到后续)

1. **Event 触发缓存键主动失效** — 缓存一致性改进,和本页无关
2. **历史统计图表**(过去 7 天清理频率) — Filament Widgets chart,非 MVP
3. **预热按钮**(`config:cache` / `route:cache`) — 部署流程管,UI 给了反而易误用
4. **Redis 全量运行指标**(memory_peak / total_commands_processed / connected_clients) — 那是 Pulse/Horizon 的地盘,Cache 页只展示"与缓存操作直接相关"的数据
