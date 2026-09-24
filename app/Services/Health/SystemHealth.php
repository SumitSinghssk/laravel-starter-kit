<?php

namespace App\Services\Health;

use App\Models\Backup;
use App\Services\Backup\BackupSchedule;
use App\Support\MailSettings;
use App\Support\Maintenance;
use FilesystemIterator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

class SystemHealth
{
    public const SUMMARY_KEY = 'system-health:summary';

    public const WORKER_KEY = 'system-health:worker-heartbeat';

    public const SCHEDULER_KEY = 'system-health:scheduler-heartbeat';

    public const OK = 'ok';

    public const INFO = 'info';

    public const WARNING = 'warning';

    public const ERROR = 'error';

    public const PHP_SECURITY_ENDS = [
        '8.0' => '2023-11-26',
        '8.1' => '2025-12-31',
        '8.2' => '2026-12-31',
        '8.3' => '2027-12-31',
        '8.4' => '2028-12-31',
        '8.5' => '2029-12-31',
    ];

    public const REQUIRED_EXTENSIONS = [
        'ctype' => 'Laravel',
        'curl' => 'Laravel',
        'fileinfo' => 'file uploads',
        'mbstring' => 'Laravel',
        'openssl' => 'encryption and HTTPS',
        'pdo' => 'the database',
        'tokenizer' => 'Laravel',
        'xml' => 'Laravel',
        'gd' => 'image resizing',
        'zip' => 'backups',
    ];

    public const OPTIONAL_EXTENSIONS = [
        'intl' => 'number and date formatting in other languages',
    ];

    private const GROUPS = [
        'app' => ['label' => 'Application', 'icon' => 'server'],
        'php' => ['label' => 'PHP', 'icon' => 'code'],
        'database' => ['label' => 'Database', 'icon' => 'database'],
        'storage' => ['label' => 'Storage & files', 'icon' => 'hard-drive'],
        'queue' => ['label' => 'Queue', 'icon' => 'list'],
        'scheduler' => ['label' => 'Scheduler', 'icon' => 'clock'],
        'mail' => ['label' => 'Email', 'icon' => 'mail'],
        'cache' => ['label' => 'Cache & sessions', 'icon' => 'zap'],
        'backups' => ['label' => 'Backups', 'icon' => 'save'],
        'security' => ['label' => 'Security', 'icon' => 'shield-check'],
    ];

    private array $extra = [];

    public function __construct(private ?string $requestHost = null) {}

    public static function forRequest(): self
    {
        return new self(app()->runningInConsole() ? null : request()->getHost());
    }

    public function run(): array
    {
        $groups = [];

        foreach (self::GROUPS as $key => $group) {
            $this->extra = [];

            try {
                $checks = $this->{$key}();
            } catch (Throwable $e) {
                $checks = [$this->check("{$key}.failed", 'Check', self::ERROR, null, 'This check could not run: '.Str::limit($e->getMessage(), 200))];
            }

            $groups[$key] = [...$group, 'key' => $key, 'checks' => $checks, 'status' => $this->worst($checks), 'extra' => $this->extra];
        }

        $summary = $this->summarize($groups);
        Cache::forever(self::SUMMARY_KEY, $summary);

        return ['groups' => $groups, 'summary' => $summary];
    }

    public static function summary(): ?array
    {
        $summary = Cache::get(self::SUMMARY_KEY);

        return is_array($summary) ? $summary : null;
    }

    public static function recordWorker(): void
    {
        static $last = 0;

        if (time() - $last >= 30) {
            $last = time();
            rescue(fn () => Cache::forever(self::WORKER_KEY, $last), report: false);
        }
    }

    public static function recordScheduler(): void
    {
        Cache::forever(self::SCHEDULER_KEY, now()->timestamp);
    }

    public static function phpBinary(): string
    {
        if (preg_match('/^php[\d.]*(-cli)?(\.exe)?$/i', basename(PHP_BINARY))) {
            return PHP_BINARY;
        }

        $name = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
        $ini = php_ini_loaded_file();

        foreach (array_filter([$ini ? dirname($ini) : null, PHP_BINDIR, dirname(PHP_BINARY)]) as $folder) {
            if (is_file($folder.DIRECTORY_SEPARATOR.$name)) {
                return $folder.DIRECTORY_SEPARATOR.$name;
            }
        }

        return 'php';
    }

    public static function report(array $result): string
    {
        $lines = [
            config('app.name').' system health',
            'Checked '.Carbon::createFromTimestamp($result['summary']['checked_at'])->toDayDateTimeString().' ('.config('app.timezone').')',
            'Overall: '.strtoupper($result['summary']['status']).' - '.$result['summary']['errors'].' problem(s), '.$result['summary']['warnings'].' warning(s)',
            '',
        ];

        foreach ($result['groups'] as $group) {
            $lines[] = '## '.$group['label'];

            foreach ($group['checks'] as $check) {
                $lines[] = sprintf('[%s] %s: %s', strtoupper($check['status']), $check['label'], trim(($check['value'] ? $check['value'].'. ' : '').$check['message']));
            }

            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    public function app(): array
    {
        $checks = [$this->phpVersion()];

        $checks[] = $this->check('app.laravel', 'Laravel', self::INFO, app()->version(), 'Framework version.');
        $checks[] = $this->check('app.environment', 'Environment', self::INFO, app()->environment(), app()->isProduction() ? 'Running as the live site.' : 'Not marked as production (APP_ENV).');
        $checks[] = $this->check('app.timezone', 'Timezone', self::INFO, config('app.timezone'), 'Used for dates, schedules and backups.');

        if ($this->requestHost) {
            $configured = parse_url((string) config('app.url'), PHP_URL_HOST);
            $checks[] = $configured && strcasecmp($configured, $this->requestHost) !== 0
                ? $this->check('app.url', 'App URL', self::WARNING, config('app.url'), "It doesn't match the address you're using ({$this->requestHost}). Links in emails, the sitemap and image URLs use APP_URL.", 'Set APP_URL in .env to the site\'s real address.')
                : $this->check('app.url', 'App URL', self::OK, config('app.url'), 'Matches the address you are using.');
        }

        $cached = app()->configurationIsCached() && app()->routesAreCached();
        $checks[] = app()->isProduction() && ! $cached
            ? $this->check('app.optimized', 'Config & route cache', self::WARNING, 'Not cached', 'Caching makes every page load faster on the live site.', 'Run: php artisan optimize')
            : $this->check('app.optimized', 'Config & route cache', $cached ? self::OK : self::INFO, $cached ? 'Cached' : 'Not cached', $cached ? 'Pages load a little faster.' : 'Fine while developing. Run php artisan optimize on the live site.');

        $checks[] = Maintenance::isOn()
            ? $this->check('app.maintenance', 'Maintenance mode', self::WARNING, 'On', 'Visitors see the maintenance page.', 'Turn it off in Settings → Maintenance mode when you are done.')
            : $this->check('app.maintenance', 'Maintenance mode', self::OK, 'Off', 'The site is open to visitors.');

        $checks[] = $this->recentErrors();

        return $checks;
    }

    public function php(): array
    {
        $checks = [];

        $driver = config('database.connections.'.config('database.default').'.driver');
        $required = [...self::REQUIRED_EXTENSIONS, "pdo_{$driver}" => 'the database'];
        $missing = array_keys(array_filter($required, fn ($why, $ext) => ! extension_loaded($ext), ARRAY_FILTER_USE_BOTH));
        $optional = array_keys(array_filter(self::OPTIONAL_EXTENSIONS, fn ($why, $ext) => ! extension_loaded($ext), ARRAY_FILTER_USE_BOTH));

        $checks[] = match (true) {
            $missing !== [] => $this->check('php.extensions', 'Extensions', self::ERROR, 'Missing: '.implode(', ', $missing), 'Needed for '.collect($missing)->map(fn ($ext) => $required[$ext])->unique()->implode(', ').'.', 'Enable them in php.ini (extension=…) or ask your host, then restart the web server.'),
            $optional !== [] => $this->check('php.extensions', 'Extensions', self::WARNING, 'Missing (optional): '.implode(', ', $optional), 'All required extensions are loaded. Optional ones help with '.collect($optional)->map(fn ($ext) => self::OPTIONAL_EXTENSIONS[$ext])->implode(', ').'.', 'Enable them in php.ini if you need them.'),
            default => $this->check('php.extensions', 'Extensions', self::OK, count($required).' required loaded', 'Everything the app needs is available.'),
        };

        $memory = $this->iniBytes('memory_limit');
        $checks[] = $memory !== -1 && $memory < 128 * 1024 * 1024
            ? $this->check('php.memory', 'Memory limit', self::WARNING, ini_get('memory_limit'), 'Large images and backups may run out of memory.', 'Set memory_limit = 256M in php.ini.')
            : $this->check('php.memory', 'Memory limit', self::OK, $memory === -1 ? 'Unlimited' : ini_get('memory_limit'), 'Enough for images and backups.');

        $upload = $this->iniBytes('upload_max_filesize');
        $post = $this->iniBytes('post_max_size');
        $effective = $post > 0 ? min($upload, $post) : $upload;
        $checks[] = match (true) {
            $post > 0 && $post < $upload => $this->check('php.upload', 'Upload size', self::WARNING, 'post_max_size '.ini_get('post_max_size').' < upload_max_filesize '.ini_get('upload_max_filesize'), 'Uploads are cut off at the smaller of the two.', 'Make post_max_size a little bigger than upload_max_filesize in php.ini.'),
            $effective < 8 * 1024 * 1024 => $this->check('php.upload', 'Upload size', self::WARNING, Number::fileSize($effective), 'Images and files bigger than this are rejected by the server.', 'Set upload_max_filesize = 32M and post_max_size = 40M in php.ini.'),
            default => $this->check('php.upload', 'Upload size', self::OK, Number::fileSize($effective), 'Largest single upload the server accepts.'),
        };

        $time = (int) ini_get('max_execution_time');
        $checks[] = $time > 0 && $time < 30
            ? $this->check('php.time', 'Max execution time', self::WARNING, "{$time}s", 'Slow requests such as big imports may be cut off.', 'Set max_execution_time = 60 in php.ini.')
            : $this->check('php.time', 'Max execution time', self::OK, $time === 0 ? 'Unlimited' : "{$time}s", 'Long tasks like backups run in small steps, so this is enough.');

        $opcache = function_exists('opcache_get_status') && (@opcache_get_status(false)['opcache_enabled'] ?? false);
        $checks[] = $this->check('php.opcache', 'OPcache', $opcache ? self::OK : (app()->isProduction() ? self::WARNING : self::INFO), $opcache ? 'On' : 'Off', $opcache ? 'PHP code is kept compiled in memory.' : 'Turning it on makes the live site noticeably faster.', $opcache ? null : 'Set opcache.enable = 1 in php.ini.');

        return $checks;
    }

    public function database(): array
    {
        $connection = DB::connection();

        try {
            $version = (string) $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (Throwable $e) {
            return [$this->check('database.connection', 'Connection', self::ERROR, 'Failed', 'Could not connect: '.Str::limit($e->getMessage(), 200), 'Check the DB_* settings in .env and that the database server is running.')];
        }

        $name = match ($connection->getDriverName()) {
            'mysql', 'mariadb' => str_contains(strtolower($version), 'mariadb') ? 'MariaDB' : 'MySQL',
            'pgsql' => 'PostgreSQL',
            'sqlite' => 'SQLite',
            'sqlsrv' => 'SQL Server',
            default => $connection->getDriverName(),
        };

        $checks = [$this->check('database.connection', 'Connection', self::OK, $name.' '.Str::before($version, '-'), 'Connected to “'.$connection->getDatabaseName().'”.')];

        $size = match ($connection->getDriverName()) {
            'mysql', 'mariadb' => (int) $connection->selectOne('select coalesce(sum(data_length + index_length), 0) as size from information_schema.tables where table_schema = database()')->size,
            'sqlite' => is_file($connection->getDatabaseName()) ? filesize($connection->getDatabaseName()) : null,
            'pgsql' => (int) $connection->selectOne('select pg_database_size(current_database()) as size')->size,
            default => null,
        };
        if ($size !== null) {
            $checks[] = $this->check('database.size', 'Size', self::INFO, Number::fileSize($size, 1), 'Tables and indexes together.');
        }

        $migrator = app('migrator');
        $pending = [];
        if ($migrator->repositoryExists()) {
            $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);
            $pending = array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
        }
        $checks[] = $pending === []
            ? $this->check('database.migrations', 'Migrations', self::OK, 'Up to date', 'Every database change has been applied.')
            : $this->check('database.migrations', 'Migrations', self::ERROR, count($pending).' not run', 'Parts of the app may break until they run: '.Str::limit(implode(', ', $pending), 160), 'Run: php artisan migrate');

        return $checks;
    }

    public function storage(): array
    {
        $checks = [];

        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        if ($free !== false && $total) {
            $percent = $free / $total * 100;
            $this->extra['disk'] = ['free' => (int) $free, 'total' => (int) $total, 'used_percent' => round(100 - $percent, 1)];
            $value = Number::fileSize($free, 1).' free of '.Number::fileSize($total, 1);
            $checks[] = match (true) {
                $percent < 5 || $free < 500 * 1024 * 1024 => $this->check('storage.disk', 'Disk space', self::ERROR, $value, 'Almost full. Uploads, backups and logs will start failing.', 'Delete old backups or logs, or ask your host for more space.'),
                $percent < 15 || $free < 2 * 1024 * 1024 * 1024 => $this->check('storage.disk', 'Disk space', self::WARNING, $value, 'Getting low.', 'Delete old backups or logs, or ask your host for more space.'),
                default => $this->check('storage.disk', 'Disk space', self::OK, $value, round($percent).'% free.'),
            };
        } else {
            $checks[] = $this->check('storage.disk', 'Disk space', self::INFO, 'Unknown', 'The server does not report disk space.');
        }

        foreach (config('filesystems.links', []) as $link => $target) {
            $checks[] = $this->storageLink($link, $target);
        }

        $folders = ['storage/app', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'];
        $notWritable = array_values(array_filter($folders, fn ($folder) => ! is_writable(base_path($folder))));
        $checks[] = $notWritable === []
            ? $this->check('storage.writable', 'Writable folders', self::OK, 'All writable', 'The app can save uploads, cache and logs.')
            : $this->check('storage.writable', 'Writable folders', self::ERROR, 'Not writable: '.implode(', ', $notWritable), 'Uploads, caching or logging will fail.', 'Give the web server user write permission to these folders.');

        return $checks;
    }

    public function storageLink(string $link, string $target): array
    {
        $label = 'Storage link';
        $name = Str::after($link, public_path().DIRECTORY_SEPARATOR) ?: $link;
        $linked = file_exists($link) && realpath($link) === realpath($target);
        $isLink = is_link($link) || ($linked && realpath($link) !== $link);

        $this->extra['links'][] = ['link' => $name, 'ok' => $linked && $isLink, 'folder' => file_exists($link) && ! $linked];

        return match (true) {
            $linked && $isLink => $this->check('storage.link', $label, self::OK, "public/{$name} → storage", 'Uploaded images and files are publicly reachable.'),
            file_exists($link) => $this->check('storage.link', $label, self::ERROR, "public/{$name} is a normal folder", 'Uploaded files go to storage, but visitors are served this folder instead, so new images show as broken.', "Move anything you need out of public/{$name}, delete it, then create the link."),
            default => $this->check('storage.link', $label, self::ERROR, 'Missing', 'Uploaded images and files will show as broken on the site.', 'Use “Create link” below, or run: php artisan storage:link'),
        };
    }

    public function queue(): array
    {
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");

        if ($driver === 'sync') {
            $checks = [$this->check('queue.driver', 'Driver', self::INFO, 'sync', 'Jobs run straight away during the request, so no worker is needed.')];
        } else {
            $checks = [$this->check('queue.driver', 'Driver', self::INFO, $driver, 'Queued jobs wait here until a worker runs them.')];

            $pending = (int) rescue(fn () => Queue::connection($connection)->size(), 0, false);
            $oldest = $driver === 'database'
                ? rescue(fn () => DB::connection(config("queue.connections.{$connection}.connection"))->table(config("queue.connections.{$connection}.table", 'jobs'))->whereNull('reserved_at')->min('available_at'), null, false)
                : null;
            $waiting = $oldest ? Carbon::createFromTimestamp($oldest) : null;
            $stuck = $pending > 0 && $waiting && $waiting->lt(now()->subMinutes(5));

            $checks[] = match (true) {
                $stuck => $this->check('queue.pending', 'Waiting jobs', self::ERROR, "{$pending} waiting", 'The oldest has waited since '.$waiting->diffForHumans().'. No worker seems to be running them.', 'Start a worker: php artisan queue:work --tries=3'),
                $pending > 0 => $this->check('queue.pending', 'Waiting jobs', self::OK, "{$pending} waiting", 'Recently added; a worker should pick them up shortly.'),
                default => $this->check('queue.pending', 'Waiting jobs', self::OK, 'None', 'Nothing is waiting.'),
            };

            $seen = Cache::get(self::WORKER_KEY);
            $seenAt = $seen ? Carbon::createFromTimestamp($seen) : null;
            $checks[] = match (true) {
                $seenAt && $seenAt->gt(now()->subMinutes(3)) => $this->check('queue.worker', 'Worker', self::OK, 'Running', 'Last seen '.$seenAt->diffForHumans().'.'),
                $stuck => $this->check('queue.worker', 'Worker', self::ERROR, 'Not running', $seenAt ? 'Last seen '.$seenAt->diffForHumans().'.' : 'Never seen on this server.', 'Keep php artisan queue:work running with Supervisor (Linux) or a scheduled task (Windows).'),
                default => $this->check('queue.worker', 'Worker', self::INFO, 'Not running', ($seenAt ? 'Last seen '.$seenAt->diffForHumans().'. ' : '').'Nothing in this app is waiting to be queued, so that is fine for now.'),
            };
        }

        $checks[] = $this->failedJobs();

        return $checks;
    }

    public function failedJobs(): array
    {
        $table = config('queue.failed.table', 'failed_jobs');
        $database = config('queue.failed.database');

        if (! Schema::connection($database)->hasTable($table)) {
            return $this->check('queue.failed', 'Failed jobs', self::INFO, 'Not tracked', 'There is no failed jobs table.');
        }

        $query = DB::connection($database)->table($table);
        $count = (clone $query)->count();

        $this->extra['failed'] = (clone $query)->latest('failed_at')->limit(20)->get()->map(fn ($job) => [
            'id' => (string) ($job->uuid ?? $job->id),
            'name' => Str::afterLast(json_decode($job->payload, true)['displayName'] ?? 'Unknown job', '\\'),
            'queue' => $job->queue,
            'error' => Str::limit(trim(Str::before($job->exception, "\n")), 220),
            'failed_at' => Carbon::parse($job->failed_at),
        ])->all();
        $this->extra['failed_total'] = $count;

        return $count === 0
            ? $this->check('queue.failed', 'Failed jobs', self::OK, 'None', 'No job has failed.')
            : $this->check('queue.failed', 'Failed jobs', self::WARNING, (string) $count, 'These jobs gave up after their retries. Retry them once the cause is fixed, or delete them.', 'See the list below.');
    }

    public function scheduler(): array
    {
        $beats = array_filter([Cache::get(self::SCHEDULER_KEY), Cache::get('backup:scheduler-heartbeat')]);
        $seenAt = $beats ? Carbon::createFromTimestamp(max($beats)) : null;
        $running = $seenAt && $seenAt->gt(now()->subMinutes(3));
        $backupsAuto = (bool) (app(BackupSchedule::class)->settings()['enabled'] ?? false);

        $this->extra['running'] = $running;
        $this->extra['tasks'] = $this->scheduledTasks();
        $this->extra['command'] = PHP_OS_FAMILY === 'Windows'
            ? 'schtasks /Create /F /SC MINUTE /MO 1 /TN "Laravel scheduler" /TR "\"'.self::phpBinary().'\" \"'.base_path('artisan').'\" schedule:run"'
            : '* * * * * cd '.base_path().' && '.self::phpBinary().' artisan schedule:run >> /dev/null 2>&1';
        $this->extra['windows'] = PHP_OS_FAMILY === 'Windows';

        $needs = 'Scheduled tasks (backups, trash clean-up, upload clean-up) only run while it does.';

        return [
            $running
                ? $this->check('scheduler.running', 'Status', self::OK, 'Running', 'Last run '.$seenAt->diffForHumans().'.')
                : $this->check('scheduler.running', 'Status', $backupsAuto ? self::ERROR : self::WARNING, $seenAt ? 'Stopped' : 'Not set up', ($seenAt ? 'Last run '.$seenAt->diffForHumans().'. ' : 'It has never run on this server. ').$needs.($backupsAuto ? ' Automatic backups are turned on but will not happen.' : ''), 'Set it up with the command below.'),
            $this->check('scheduler.tasks', 'Tasks', self::INFO, count($this->extra['tasks']).' scheduled', 'Listed below with their next run.'),
        ];
    }

    public function scheduledTasks(): array
    {
        Artisan::all();

        return collect(app(Schedule::class)->events())->map(function ($event) {
            $command = $event->command ? trim(preg_replace('/^.*?artisan[\'"]?\s*/', '', $event->command)) : null;

            return [
                'name' => $event->description ?: ($command ?: 'Closure'),
                'command' => $command,
                'expression' => $event->expression,
                'next' => rescue(fn () => Carbon::instance($event->nextRunDate()), null, false),
            ];
        })->reject(fn ($task) => $task['name'] === 'health:heartbeat')->values()->all();
    }

    public function mail(): array
    {
        $mailer = config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport", $mailer);
        $fromSettings = MailSettings::settings()['enabled'] && filled(MailSettings::settings()['host']);
        $host = config("mail.mailers.{$mailer}.host");

        $this->extra['smtp'] = $transport === 'smtp';
        $this->extra['from_settings'] = $fromSettings;

        $checks = [match (true) {
            in_array($transport, ['log', 'array'], true) => $this->check('mail.mailer', 'Mailer', self::WARNING, $transport, 'Emails are written to the log file, not delivered to anyone: contact form alerts and password resets never arrive.', 'Set up SMTP in Settings → Email (SMTP).'),
            $transport === 'smtp' => $this->check('mail.mailer', 'Mailer', self::OK, "SMTP · {$host}:".config("mail.mailers.{$mailer}.port"), $fromSettings ? 'Configured in Settings → Email (SMTP).' : 'Configured in .env.'),
            default => $this->check('mail.mailer', 'Mailer', self::OK, $transport, 'Sending through '.$transport.'.'),
        }];

        $from = (string) config('mail.from.address');
        $checks[] = match (true) {
            blank($from) => $this->check('mail.from', 'From address', self::ERROR, 'Not set', 'Emails cannot be sent without one.', 'Set it in Settings → Email (SMTP).'),
            Str::endsWith($from, ['@example.com', '@example.org', '@example.net']) => $this->check('mail.from', 'From address', self::WARNING, $from, 'Still the example address, so mail servers will likely reject or junk it.', 'Set a real address in Settings → Email (SMTP).'),
            default => $this->check('mail.from', 'From address', self::OK, $from, 'Emails are sent from this address'.(filled(config('mail.from.name')) ? ' as “'.config('mail.from.name').'”' : '').'.'),
        };

        return $checks;
    }

    public function testMailConnection(): array
    {
        $mailer = config('mail.default');
        $config = config("mail.mailers.{$mailer}", []);

        if (($config['transport'] ?? $mailer) !== 'smtp') {
            return ['ok' => false, 'message' => 'The site is not sending through SMTP ('.$mailer.'), so there is no mail server to connect to. Set one up in Settings → Email (SMTP).'];
        }

        $started = microtime(true);

        try {
            $transport = Mail::build([...$config, 'timeout' => min((int) ($config['timeout'] ?? 15), 15)])->getSymfonyTransport();

            if (! $transport instanceof SmtpTransport) {
                return ['ok' => false, 'message' => 'This mailer cannot be tested with a connection check.'];
            }

            $transport->start();
            $transport->stop();

            return ['ok' => true, 'message' => 'Connected to '.$config['host'].':'.$config['port'].(filled($config['username'] ?? null) ? ' and logged in' : '').'. The server is ready to send.', 'seconds' => round(microtime(true) - $started, 1)];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => MailSettings::explain($e->getMessage(), $config), 'detail' => Str::limit(trim(preg_replace('/\s+/', ' ', $e->getMessage())), 500), 'seconds' => round(microtime(true) - $started, 1)];
        }
    }

    public function cache(): array
    {
        $store = config('cache.default');
        $key = 'system-health:probe:'.Str::random(8);
        $works = rescue(function () use ($key) {
            Cache::put($key, 'ok', 60);
            $value = Cache::get($key);
            Cache::forget($key);

            return $value === 'ok';
        }, false, false);

        $checks = [$works
            ? $this->check('cache.store', 'Cache', self::OK, $store, 'Saving and reading back works.')
            : $this->check('cache.store', 'Cache', self::ERROR, $store, 'Could not save and read back a value. Settings, menus and themes are cached, so the site may be slow or out of date.', 'Check the CACHE_STORE setting and that storage/framework/cache is writable.')];

        $driver = config('session.driver');
        $sessions = $driver === 'database' && Schema::hasTable(config('session.table', 'sessions'))
            ? DB::table(config('session.table', 'sessions'))->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->timestamp)->count()
            : null;
        $checks[] = $this->check('cache.sessions', 'Sessions', self::INFO, $driver, $sessions === null ? 'Where logins are remembered.' : "{$sessions} active in the last ".config('session.lifetime').' minutes.');

        return $checks;
    }

    public function backups(): array
    {
        $settings = app(BackupSchedule::class)->settings();
        $auto = (bool) ($settings['enabled'] ?? false);
        $last = Backup::where('status', Backup::COMPLETED)->latest('finished_at')->first();
        $latest = Backup::whereIn('status', [Backup::COMPLETED, Backup::FAILED])->latest('id')->first();

        $allowedDays = $auto ? ['daily' => 2, 'weekly' => 8, 'monthly' => 32][$settings['frequency'] ?? 'daily'] ?? 2 : 30;
        $age = $last?->finished_at;

        $checks = [match (true) {
            ! $last => $this->check('backups.last', 'Last backup', self::WARNING, 'Never', 'There is no backup to restore from if something goes wrong.', 'Make one on the Backups page, and turn on automatic backups.'),
            $age->lt(now()->subDays($allowedDays)) => $this->check('backups.last', 'Last backup', self::WARNING, $age->diffForHumans(), $auto ? 'Older than the schedule expects. Check the scheduler.' : 'Over a month old.', $auto ? null : 'Make a fresh backup, or turn on automatic backups.'),
            default => $this->check('backups.last', 'Last backup', self::OK, $age->diffForHumans(), Number::fileSize($last->size, 1).', '.$age->toDayDateTimeString().'.'),
        }];

        if ($latest && $latest->status === Backup::FAILED) {
            $checks[] = $this->check('backups.failed', 'Latest attempt', self::WARNING, 'Failed', Str::limit((string) $latest->error, 200) ?: 'The last backup did not finish.', 'Open the Backups page for details.');
        }

        $checks[] = $this->check('backups.auto', 'Automatic backups', $auto ? self::OK : self::INFO, $auto ? 'On' : 'Off', $auto ? app(BackupSchedule::class)->describe().'.' : 'Backups are only made when someone clicks “Back up now”.');

        return $checks;
    }

    public function security(): array
    {
        $checks = [];

        $checks[] = filled(config('app.key'))
            ? $this->check('security.key', 'App key', self::OK, 'Set', 'Sessions, cookies and saved passwords are encrypted.')
            : $this->check('security.key', 'App key', self::ERROR, 'Missing', 'Encryption does not work without it.', 'Run: php artisan key:generate');

        $debug = (bool) config('app.debug');
        $checks[] = match (true) {
            $debug && app()->isProduction() => $this->check('security.debug', 'Debug mode', self::ERROR, 'On', 'Error pages show code, file paths and settings to anyone on the live site.', 'Set APP_DEBUG=false in .env.'),
            $debug => $this->check('security.debug', 'Debug mode', self::INFO, 'On', 'Fine while developing. Turn it off (APP_DEBUG=false) on the live site.'),
            default => $this->check('security.debug', 'Debug mode', self::OK, 'Off', 'Visitors see friendly error pages.'),
        };

        $https = str_starts_with((string) config('app.url'), 'https://');
        $checks[] = match (true) {
            $https => $this->check('security.https', 'HTTPS', self::OK, 'On', 'APP_URL uses https.'),
            app()->isProduction() => $this->check('security.https', 'HTTPS', self::WARNING, 'Off', 'Logins and form data travel unencrypted, and browsers mark the site “Not secure”.', 'Install an SSL certificate and set APP_URL to https://…'),
            default => $this->check('security.https', 'HTTPS', self::INFO, 'Off', 'Fine while developing. Use https on the live site.'),
        };

        $checks[] = file_exists(public_path('.env'))
            ? $this->check('security.env', '.env file', self::ERROR, 'Inside public folder', 'Anyone could download your passwords and keys.', 'Move .env out of the public folder right away and change the passwords in it.')
            : $this->check('security.env', '.env file', self::OK, 'Private', 'Not inside the public folder.');

        return $checks;
    }

    public function folderSizes(): array
    {
        $deadline = microtime(true) + 8;

        $folders = [
            'uploads' => ['label' => 'Uploaded files', 'path' => storage_path('app/public')],
            'backups' => ['label' => 'Backups', 'path' => Backup::disk()->path('')],
            'logs' => ['label' => 'Log files', 'path' => storage_path('logs')],
            'cache' => ['label' => 'Cache & compiled views', 'path' => storage_path('framework')],
        ];

        return collect($folders)->map(function ($folder) use ($deadline) {
            [$bytes, $complete] = $this->directorySize($folder['path'], $deadline);

            return ['label' => $folder['label'], 'bytes' => $bytes, 'size' => Number::fileSize($bytes, 1), 'complete' => $complete];
        })->all();
    }

    private function directorySize(string $path, float $deadline): array
    {
        if (! is_dir($path)) {
            return [0, true];
        }

        $bytes = 0;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);

        try {
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $bytes += $file->getSize();
                }

                if (microtime(true) > $deadline) {
                    return [$bytes, false];
                }
            }
        } catch (Throwable) {
            return [$bytes, false];
        }

        return [$bytes, true];
    }

    private function phpVersion(): array
    {
        $composer = json_decode((string) @file_get_contents(base_path('composer.json')), true);
        preg_match('/(\d+\.\d+)/', (string) Arr::get($composer, 'require.php', ''), $match);
        $minimum = $match[1] ?? '8.2';
        $branch = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        $ends = isset(self::PHP_SECURITY_ENDS[$branch]) ? Carbon::parse(self::PHP_SECURITY_ENDS[$branch])->endOfDay() : null;

        return match (true) {
            version_compare(PHP_VERSION, $minimum, '<') => $this->check('app.php', 'PHP version', self::ERROR, PHP_VERSION, "The app needs PHP {$minimum} or newer.", "Upgrade PHP to {$minimum}+ in your hosting control panel."),
            $ends && $ends->isPast() => $this->check('app.php', 'PHP version', self::WARNING, PHP_VERSION, "PHP {$branch} stopped getting security fixes on ".$ends->toFormattedDateString().'.', 'Upgrade to a newer PHP version in your hosting control panel.'),
            $ends && $ends->lt(now()->addMonths(6)) => $this->check('app.php', 'PHP version', self::WARNING, PHP_VERSION, "Works, but PHP {$branch} stops getting security fixes on ".$ends->toFormattedDateString().'.', 'Plan an upgrade to a newer PHP version.'),
            default => $this->check('app.php', 'PHP version', self::OK, PHP_VERSION, "Meets the app's requirement (PHP {$minimum}+)".($ends ? ' and gets security fixes until '.$ends->toFormattedDateString() : '').'.'),
        };
    }

    private function recentErrors(): array
    {
        $files = array_filter([storage_path('logs/laravel.log'), storage_path('logs/laravel-'.now()->format('Y-m-d').'.log'), storage_path('logs/laravel-'.now()->subDay()->format('Y-m-d').'.log')], 'is_file');
        $since = now()->subDay();
        $count = 0;
        $last = null;

        foreach ($files as $file) {
            $handle = fopen($file, 'r');
            $size = filesize($file);
            fseek($handle, max(0, $size - 2 * 1024 * 1024));
            $chunk = stream_get_contents($handle);
            fclose($handle);

            preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\] [\w-]+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $chunk, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $at = rescue(fn () => Carbon::parse($match[1]), null, false);
                if ($at && $at->gte($since)) {
                    $count++;
                    $last = $match[3];
                }
            }
        }

        return $count === 0
            ? $this->check('app.errors', 'Errors in the log', self::OK, 'None today', 'No errors were logged in the last 24 hours.')
            : $this->check('app.errors', 'Errors in the log', self::WARNING, "{$count} in 24 hours", 'Latest: '.Str::limit($last, 160), 'Open Settings → Application logs to read them.');
    }

    private function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function check(string $key, string $label, string $status, ?string $value, string $message, ?string $fix = null): array
    {
        return compact('key', 'label', 'status', 'value', 'message', 'fix');
    }

    private function worst(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        return match (true) {
            in_array(self::ERROR, $statuses, true) => self::ERROR,
            in_array(self::WARNING, $statuses, true) => self::WARNING,
            default => self::OK,
        };
    }

    private function summarize(array $groups): array
    {
        $statuses = collect($groups)->flatMap(fn ($group) => array_column($group['checks'], 'status'));
        $errors = $statuses->filter(fn ($s) => $s === self::ERROR)->count();
        $warnings = $statuses->filter(fn ($s) => $s === self::WARNING)->count();

        return [
            'status' => $errors ? self::ERROR : ($warnings ? self::WARNING : self::OK),
            'errors' => $errors,
            'warnings' => $warnings,
            'passed' => $statuses->filter(fn ($s) => $s === self::OK)->count(),
            'total' => $statuses->count(),
            'checked_at' => now()->timestamp,
        ];
    }
}
