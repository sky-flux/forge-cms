# MVP Completion — Batch 3 Spec

**Date:** 2026-04-19
**Scope:** UI-configurable backup (`spatie/laravel-backup`) + ops polish (Scout queue, MediaPolicy test, items_count label i18n). **Reverb 保留闲置** (no removal).
**Merge strategy:** linear main via `git rebase` + `git merge --ff-only`. NO `--no-ff` merges.

## Worktree G — `feat/mvp-backup-settings` (4 tasks, sequential)

### T1 — `BackupSettings` class + migration

- Create `app/Settings/BackupSettings.php` with:
  ```php
  public bool $enabled = false;
  public string $destination_disk = 'local';
  public bool $include_storage_files = true;
  public int $keep_daily_days = 7;
  public int $keep_weekly_weeks = 4;
  public int $keep_monthly_months = 3;
  public ?string $notify_email = null;
  public int $schedule_hour = 2;  // 0-23
  public static function group(): string { return 'backup'; }
  ```
- `php artisan make:settings-migration CreateBackupSettings` → populate 8 defaults
- Register `BackupSettings::class` in `config/settings.php` `settings` array
- Test: `BackupSettings` resolves with expected defaults; persist survives `forgetInstance`

### T2 — Filament `SystemSettings` page → Tabs layout

- Refactor `app/Filament/Pages/SystemSettings.php` `form()` to use `Filament\Schemas\Components\Tabs` with 2 tabs: **"基本信息"** (existing GeneralSettings fields) + **"备份"** (new BackupSettings fields)
- 备份 tab fields:
  - `Toggle::make('backup.enabled')->label('启用备份')`
  - `Select::make('backup.destination_disk')->label('存储位置')->options(fn () => collect(config('filesystems.disks'))->keys()->mapWithKeys(fn ($k) => [$k => $k]))`
  - `Toggle::make('backup.include_storage_files')->label('包含上传文件')`
  - `TextInput::make('backup.keep_daily_days')->numeric()->minValue(1)->maxValue(365)->label('每日保留天数')`
  - `TextInput::make('backup.keep_weekly_weeks')->numeric()->minValue(0)->maxValue(52)->label('每周保留数')`
  - `TextInput::make('backup.keep_monthly_months')->numeric()->minValue(0)->maxValue(24)->label('每月保留月数')`
  - `TextInput::make('backup.notify_email')->email()->label('失败通知邮箱')`
  - `TextInput::make('backup.schedule_hour')->numeric()->minValue(0)->maxValue(23)->label('每日执行时刻 (0-23)')`
- `mount()` populates both settings:
  ```php
  $this->form->fill([
      ...app(GeneralSettings::class)->toArray(),
      'backup' => app(BackupSettings::class)->toArray(),
  ]);
  ```
- `save()` writes back to BOTH settings groups (iterate keys, assign, save each)
- Test: Livewire test fills both tabs + saves → both settings persist

### T3 — Schedule reads BackupSettings + runs backup commands

- In `bootstrap/app.php::withSchedule`:
  ```php
  $schedule->call(function (): void {
      $settings = app(\App\Settings\BackupSettings::class);
      if (! $settings->enabled) {
          return;
      }

      config([
          'backup.destination.disks' => [$settings->destination_disk],
          'backup.cleanup.default_strategy.keep_daily_backups_for_days' => $settings->keep_daily_days,
          'backup.cleanup.default_strategy.keep_weekly_backups_for_weeks' => $settings->keep_weekly_weeks,
          'backup.cleanup.default_strategy.keep_monthly_backups_for_months' => $settings->keep_monthly_months,
          'backup.source.files.include' => $settings->include_storage_files ? [storage_path()] : [],
          'backup.notifications.notifications.' . \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => $settings->notify_email ? ['mail'] : [],
          'backup.notifications.mail.to' => $settings->notify_email,
      ]);

      \Illuminate\Support\Facades\Artisan::call('backup:clean');
      \Illuminate\Support\Facades\Artisan::call('backup:run', $settings->include_storage_files ? [] : ['--only-db' => true]);
  })->name('forge.backup')->dailyAt(sprintf('%02d:00', app(\App\Settings\BackupSettings::class)->schedule_hour))->onOneServer();
  ```
- **Octane-safe**: `config([...])` only mutates within the CLI process — `schedule:run` is a separate `php artisan` invocation, not inside an Octane worker.
- Test: when enabled=false, no backup commands fire; when enabled=true, `Artisan::call('backup:clean')` + `backup:run` fire. Use `Artisan::fake()` or spy on the command bus.

### T4 — Integration test

- `tests/Feature/Backup/BackupSettingsFlowTest.php` — asserts:
  - Enabled toggle persists via Livewire form submit
  - Schedule's closure no-ops when disabled
  - Schedule's closure calls `Artisan::call('backup:run', ...)` when enabled
  - `backup.notifications.mail.to` is set from settings

## Worktree H — `feat/mvp-ops-polish` (3 tasks, sequential)

### T1 — Scout async queue

- `.env.example`: set `SCOUT_QUEUE=true`
- `config/scout.php`: change default `'queue' => env('SCOUT_QUEUE', false)` → `true`
- Test: after `Post::factory()->published()->create()`, `Queue::assertPushed(\Laravel\Scout\Jobs\MakeSearchable::class)` via `Queue::fake()`

### T2 — MediaPolicy non-super_admin denial test

- File: `tests/Feature/Admin/MediaPolicyTest.php`
- Cases:
  - (a) non-super_admin user without `view_any_media` permission: `$user->can('viewAny', Media::class)` → false
  - (b) non-super_admin user WITH `view_any_media` permission: `$user->can('viewAny', Media::class)` → true
  - (c) super_admin: always true (Gate::before bypass)

### T3 — `items_count` label i18n

- File: `app/Filament/Resources/DictionaryTypes/Tables/DictionaryTypesTable.php`
- Change `TextColumn::make('items_count')->label('Items')` → `->label('字典项数')`
- Existing DictionaryTypeResourceTest doesn't assert the label string, so no test change needed

## Linear merge order

```bash
# Merge H first (smaller, less conflict surface):
git checkout main
git merge --ff-only feat/mvp-ops-polish

# Rebase G onto the new main:
cd .worktrees/mvp-backup-settings
git rebase main

# Merge G:
git checkout main
git merge --ff-only feat/mvp-backup-settings
```

If `--ff-only` refuses (main moved during implementation), rebase the offending branch onto main first.

## Acceptance

- `系统 → 配置` has 2 tabs: 基本信息 + 备份
- Backup settings persist and control the scheduler
- `SCOUT_QUEUE=true` by default
- MediaPolicy denial covered by test
- 字典表 items 列显示中文
- `main` history: each commit shows as a SINGLE line, NO merge commits for Batch 3
