<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\Backup\BackupRunner;
use App\Services\Backup\BackupSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BackupController extends Controller
{
    public function __construct(private BackupRunner $runner, private BackupSchedule $schedule) {}

    public function index()
    {
        Gate::authorize('admin.backups.view');

        $heartbeat = Cache::get('backup:scheduler-heartbeat');

        return view('admin.backups.index', [
            'backups' => Backup::with('user:id,name')->latest('id')->paginate(15),
            'settings' => $this->schedule->settings(),
            'scheduleText' => $this->schedule->describe(),
            'nextRun' => $this->schedule->nextRun(),
            'schedulerSeen' => $heartbeat ? Carbon::createFromTimestamp($heartbeat) : null,
            'running' => Backup::where('status', Backup::RUNNING)->latest('id')->first(),
            'totalSize' => (int) Backup::where('status', Backup::COMPLETED)->sum('size'),
        ]);
    }

    public function saveSettings(Request $request)
    {
        Gate::authorize('admin.backups.settings');

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'time' => ['required', 'date_format:H:i'],
            'weekday' => ['required_if:frequency,weekly', 'integer', 'between:1,7'],
            'monthday' => ['required_if:frequency,monthly', 'integer', 'between:1,28'],
            'include_database' => ['required', 'boolean'],
            'include_files' => ['required', 'boolean'],
            'keep' => ['required', 'integer', 'between:1,60'],
        ], [
            'time.date_format' => 'Enter the time as HH:MM, for example 02:30.',
        ]);

        if (! $validated['include_database'] && ! $validated['include_files']) {
            return back()->withErrors(['include_database' => 'Choose the database, the uploaded files, or both.'])->withInput();
        }

        $this->schedule->save([
            ...$validated,
            'enabled' => (bool) $validated['enabled'],
            'include_database' => (bool) $validated['include_database'],
            'include_files' => (bool) $validated['include_files'],
            'weekday' => (int) ($validated['weekday'] ?? 1),
            'monthday' => (int) ($validated['monthday'] ?? 1),
            'keep' => (int) $validated['keep'],
        ]);

        $this->runner->prune((int) $validated['keep']);

        return back()->with('success', 'Backup schedule saved. '.$this->schedule->describe().'.');
    }

    public function store(Request $request)
    {
        Gate::authorize('admin.backups.create');

        $validated = $request->validate([
            'include_database' => ['required', 'boolean'],
            'include_files' => ['required', 'boolean'],
        ]);

        $backup = $this->runner->start('manual', (bool) $validated['include_database'], (bool) $validated['include_files'], $request->user());

        return response()->json(['backup' => $backup->progressPayload()], 201);
    }

    public function step(Backup $backup)
    {
        Gate::authorize('admin.backups.create');

        $backup = $this->runner->step($backup, config('backup.step_seconds.web'));

        return response()->json(['backup' => $backup->progressPayload()]);
    }

    public function download(Backup $backup, string $file)
    {
        Gate::authorize('admin.backups.download');

        abort_unless($backup->status === Backup::COMPLETED && collect($backup->parts)->contains('file', $file), 404);

        $path = Backup::disk()->path($backup->path($file));
        abort_unless(is_file($path), 404);

        return response()->download($path, "{$backup->name}-{$file}");
    }

    public function destroy(Backup $backup)
    {
        Gate::authorize('admin.backups.delete');

        if ($backup->isRunning() && ! $backup->isStalled()) {
            return back()->with('error', 'This backup is still running. Wait for it to finish first.');
        }

        $backup->delete();

        return back()->with('success', 'Backup deleted.');
    }
}
