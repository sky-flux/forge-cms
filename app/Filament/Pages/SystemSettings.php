<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\AppearanceSettings;
use App\Settings\BackupSettings;
use App\Settings\CommentSettings;
use App\Settings\FeedSettings;
use App\Settings\GeneralSettings;
use App\Settings\LegalSettings;
use App\Settings\MailSettings;
use App\Settings\MediaUploadSettings;
use App\Settings\PerformanceSettings;
use App\Settings\SecuritySettings;
use App\Settings\SeoSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SystemSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = '系统';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = '配置';

    protected static ?string $title = '配置';

    protected static ?string $slug = 'settings';

    protected string $view = 'filament.pages.system-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->data = [
            'general' => app(GeneralSettings::class)->toArray(),
            'backup' => app(BackupSettings::class)->toArray(),
            'comments' => app(CommentSettings::class)->toArray(),
            'seo' => app(SeoSettings::class)->toArray(),
            'media_upload' => app(MediaUploadSettings::class)->toArray(),
            'feed' => app(FeedSettings::class)->toArray(),
            'mail' => app(MailSettings::class)->toArray(),
            'appearance' => app(AppearanceSettings::class)->toArray(),
            'performance' => app(PerformanceSettings::class)->toArray(),
            'legal' => app(LegalSettings::class)->toArray(),
            'security' => app(SecuritySettings::class)->toArray(),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('SystemSettingsTabs')
                    ->tabs([
                        Tab::make('基本信息')
                            ->statePath('general')
                            ->schema([
                                TextInput::make('site_name')->required()->maxLength(128),
                                Textarea::make('site_description')->required()->rows(2)->maxLength(500),
                                TextInput::make('contact_email')->email()->required()->maxLength(255),
                                Textarea::make('default_seo_description')->required()->rows(2)->maxLength(500),
                                TextInput::make('default_og_image')
                                    ->url()
                                    ->maxLength(2048)
                                    ->nullable()
                                    ->helperText('Absolute URL to the default Open Graph image.'),
                            ]),
                        Tab::make('备份')
                            ->statePath('backup')
                            ->schema([
                                Toggle::make('enabled')->label('启用备份'),
                                Select::make('destination_disk')
                                    ->label('存储位置')
                                    ->options(fn () => collect(array_keys(config('filesystems.disks')))
                                        ->mapWithKeys(fn ($k) => [$k => $k])
                                        ->all())
                                    ->required(),
                                Toggle::make('include_storage_files')->label('包含上传文件'),
                                TextInput::make('keep_daily_days')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(365)
                                    ->label('每日保留天数'),
                                TextInput::make('keep_weekly_weeks')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(52)
                                    ->label('每周保留数'),
                                TextInput::make('keep_monthly_months')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(24)
                                    ->label('每月保留月数'),
                                TextInput::make('notify_email')
                                    ->email()
                                    ->nullable()
                                    ->label('失败通知邮箱'),
                                TextInput::make('schedule_hour')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(23)
                                    ->label('每日执行时刻 (0-23)'),
                            ]),
                        Tab::make('评论策略')
                            ->statePath('comments')
                            ->schema([
                                Select::make('default_status')
                                    ->options([
                                        'Pending' => 'Pending (审核)',
                                        'Approved' => 'Approved (自动通过)',
                                        'Trash' => 'Trash (丢弃)',
                                    ])
                                    ->required()
                                    ->label('默认状态'),
                                Toggle::make('allow_guests')->label('允许匿名评论'),
                                TextInput::make('max_depth')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(5)
                                    ->label('最大嵌套深度'),
                                TextInput::make('throttle_per_minute')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(60)
                                    ->label('每分钟限流'),
                                Toggle::make('notify_author_on_reply')->label('作者收到回复时通知'),
                                Toggle::make('honeypot_enabled')->label('启用蜜罐防爬'),
                            ]),
                        Tab::make('SEO')
                            ->statePath('seo')
                            ->schema([
                                TextInput::make('google_analytics_id')->nullable()->maxLength(64)->label('Google Analytics ID'),
                                TextInput::make('google_tag_manager_id')->nullable()->maxLength(64)->label('Google Tag Manager ID'),
                                TextInput::make('google_site_verification')->nullable()->maxLength(255)->label('Google Site Verification'),
                                TextInput::make('bing_site_verification')->nullable()->maxLength(255)->label('Bing Site Verification'),
                                TextInput::make('twitter_site_handle')->nullable()->maxLength(32)->label('Twitter Handle (@name)'),
                                TextInput::make('facebook_app_id')->nullable()->maxLength(32)->label('Facebook App ID'),
                                Textarea::make('robots_extra')->nullable()->rows(4)->label('robots.txt 追加内容')->helperText('追加到标准 robots.txt 后面的自定义行'),
                                Toggle::make('sitemap_include_categories')->label('Sitemap 包含分类页'),
                                Toggle::make('sitemap_include_tags')->label('Sitemap 包含标签页'),
                            ]),
                        Tab::make('媒体上传')
                            ->statePath('media_upload')
                            ->schema([
                                TextInput::make('max_upload_size_mb')->numeric()->minValue(1)->maxValue(500)->label('单文件最大 MB'),
                                TextInput::make('allowed_mime_types_csv')->required()->maxLength(1024)->label('允许的 MIME 类型(逗号分隔)'),
                                Toggle::make('auto_convert_to_webp')->label('自动转 WebP'),
                                TextInput::make('image_quality')->numeric()->minValue(1)->maxValue(100)->label('图片质量 (1-100)'),
                            ]),
                        Tab::make('RSS / Sitemap')
                            ->statePath('feed')
                            ->schema([
                                TextInput::make('items_per_feed')->numeric()->minValue(10)->maxValue(500)->label('Feed 条目数'),
                                TextInput::make('feed_cache_ttl_minutes')->numeric()->minValue(0)->maxValue(1440)->label('Feed 缓存分钟 (0=不缓存)'),
                                Toggle::make('include_excerpts_in_feed')->label('Feed 包含摘要'),
                                TextInput::make('sitemap_default_priority')->numeric()->step(0.1)->minValue(0)->maxValue(1)->label('Sitemap 默认权重 (0-1)'),
                                Select::make('sitemap_change_frequency')
                                    ->options([
                                        'always' => 'always',
                                        'hourly' => 'hourly',
                                        'daily' => 'daily',
                                        'weekly' => 'weekly',
                                        'monthly' => 'monthly',
                                        'yearly' => 'yearly',
                                        'never' => 'never',
                                    ])
                                    ->required()
                                    ->label('Sitemap 更新频率'),
                            ]),
                        Tab::make('邮件')
                            ->statePath('mail')
                            ->schema([
                                TextInput::make('from_name')->nullable()->maxLength(128)->label('发件人名称'),
                                TextInput::make('from_address')->email()->nullable()->label('发件人邮箱'),
                                TextInput::make('reply_to')->email()->nullable()->label('回复邮箱'),
                                Textarea::make('footer_template')->nullable()->rows(4)->label('邮件 Footer 模板(Markdown)'),
                            ]),
                        Tab::make('外观')
                            ->statePath('appearance')
                            ->schema([
                                TextInput::make('logo_url')->url()->nullable()->maxLength(2048)->label('Logo URL'),
                                TextInput::make('favicon_url')->url()->nullable()->maxLength(2048)->label('Favicon URL'),
                                TextInput::make('primary_color')->required()->maxLength(16)->label('主色 (#hex)'),
                                TextInput::make('footer_text')->nullable()->maxLength(255)->label('页脚文本'),
                            ]),
                        Tab::make('性能')
                            ->statePath('performance')
                            ->schema([
                                TextInput::make('post_cache_ttl_minutes')->numeric()->minValue(0)->maxValue(1440)->label('文章缓存(分钟)'),
                                TextInput::make('sitemap_cache_ttl_hours')->numeric()->minValue(0)->maxValue(168)->label('Sitemap 缓存(小时)'),
                                TextInput::make('scout_batch_size')->numeric()->minValue(50)->maxValue(5000)->label('Scout 批量大小'),
                            ]),
                        Tab::make('法律')
                            ->statePath('legal')
                            ->schema([
                                TextInput::make('terms_url')->url()->nullable()->label('服务条款 URL'),
                                TextInput::make('privacy_url')->url()->nullable()->label('隐私政策 URL'),
                                Toggle::make('cookie_banner_enabled')->label('启用 Cookie 横幅'),
                                Textarea::make('cookie_banner_text')->nullable()->rows(3)->label('Cookie 横幅文本'),
                                Toggle::make('gdpr_comment_opt_in')->label('评论需 GDPR 同意'),
                            ]),
                        Tab::make('安全策略')
                            ->statePath('security')
                            ->schema([
                                Toggle::make('require_2fa_for_super_admin')->label('super_admin 强制 2FA'),
                                TextInput::make('session_lifetime_minutes')->numeric()->minValue(5)->maxValue(10080)->label('Session 寿命(分钟)'),
                                TextInput::make('password_min_length')->numeric()->minValue(6)->maxValue(64)->label('密码最小长度'),
                                TextInput::make('max_login_attempts')->numeric()->minValue(1)->maxValue(20)->label('最大登录尝试'),
                                TextInput::make('lockout_minutes')->numeric()->minValue(1)->maxValue(1440)->label('锁定时长(分钟)'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        $general = app(GeneralSettings::class);
        foreach (($state['general'] ?? []) as $key => $value) {
            $general->{$key} = $value;
        }
        $general->save();

        $backup = app(BackupSettings::class);
        foreach (($state['backup'] ?? []) as $key => $value) {
            $backup->{$key} = $this->castSettingsValue($key, $value);
        }
        $backup->save();

        $comments = app(CommentSettings::class);
        foreach (($state['comments'] ?? []) as $key => $value) {
            $comments->{$key} = $this->castSettingsValue($key, $value);
        }
        $comments->save();

        $seo = app(SeoSettings::class);
        foreach (($state['seo'] ?? []) as $key => $value) {
            $seo->{$key} = $this->castSettingsValue($key, $value);
        }
        $seo->save();

        $mediaUpload = app(MediaUploadSettings::class);
        foreach (($state['media_upload'] ?? []) as $key => $value) {
            $mediaUpload->{$key} = $this->castSettingsValue($key, $value);
        }
        $mediaUpload->save();

        $feed = app(FeedSettings::class);
        foreach (($state['feed'] ?? []) as $key => $value) {
            $feed->{$key} = $this->castSettingsValue($key, $value);
        }
        $feed->save();

        $mail = app(MailSettings::class);
        foreach (($state['mail'] ?? []) as $key => $value) {
            $mail->{$key} = $this->castSettingsValue($key, $value);
        }
        $mail->save();

        $appearance = app(AppearanceSettings::class);
        foreach (($state['appearance'] ?? []) as $key => $value) {
            $appearance->{$key} = $this->castSettingsValue($key, $value);
        }
        $appearance->save();

        $performance = app(PerformanceSettings::class);
        foreach (($state['performance'] ?? []) as $key => $value) {
            $performance->{$key} = $this->castSettingsValue($key, $value);
        }
        $performance->save();

        $legal = app(LegalSettings::class);
        foreach (($state['legal'] ?? []) as $key => $value) {
            $legal->{$key} = $this->castSettingsValue($key, $value);
        }
        $legal->save();

        $security = app(SecuritySettings::class);
        foreach (($state['security'] ?? []) as $key => $value) {
            $security->{$key} = $this->castSettingsValue($key, $value);
        }
        $security->save();

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save')->submit('save'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    /**
     * Cast form-submitted values to the scalar types declared on the target
     * Settings classes (BackupSettings, CommentSettings, ...).
     *
     * Filament numeric inputs round-trip through HTML and come back as strings
     * or floats, which fails the typed properties on Settings classes.
     */
    private function castSettingsValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            // Integer-typed properties across settings classes.
            'keep_daily_days', 'keep_weekly_weeks', 'keep_monthly_months',
            'schedule_hour', 'max_depth', 'throttle_per_minute',
            'max_upload_size_mb', 'image_quality',
            'items_per_feed', 'feed_cache_ttl_minutes',
            'post_cache_ttl_minutes', 'sitemap_cache_ttl_hours', 'scout_batch_size',
            'session_lifetime_minutes', 'password_min_length',
            'max_login_attempts', 'lockout_minutes' => (int) $value,

            // Boolean-typed properties across settings classes.
            'enabled', 'include_storage_files',
            'allow_guests', 'notify_author_on_reply', 'honeypot_enabled',
            'sitemap_include_categories', 'sitemap_include_tags',
            'auto_convert_to_webp', 'include_excerpts_in_feed',
            'cookie_banner_enabled', 'gdpr_comment_opt_in',
            'require_2fa_for_super_admin' => (bool) $value,

            // Float-typed properties.
            'sitemap_default_priority' => (float) $value,

            default => $value,
        };
    }
}
