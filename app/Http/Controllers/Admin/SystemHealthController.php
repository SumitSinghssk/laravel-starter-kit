<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Health\SystemHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Throwable;

class SystemHealthController extends Controller
{
    public function index()
    {
        Gate::authorize('admin.system-health.view');

        $result = SystemHealth::forRequest()->run();

        return view('admin.system-health.index', [
            ...$result,
            'report' => SystemHealth::report($result),
        ]);
    }

    public function sizes()
    {
        Gate::authorize('admin.system-health.view');

        return response()->json(['folders' => SystemHealth::forRequest()->folderSizes()]);
    }

    public function mailCheck()
    {
        Gate::authorize('admin.system-health.view');

        $result = SystemHealth::forRequest()->testMailConnection();

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function storageLink()
    {
        Gate::authorize('admin.system-health.manage');

        $health = SystemHealth::forRequest();
        $failed = [];

        foreach (config('filesystems.links', []) as $link => $target) {
            if (file_exists($link) && realpath($link) !== realpath($target)) {
                return back()->with('error', 'public/'.basename($link).' is a normal folder, so it can\'t be replaced automatically. Move anything you need out of it, delete it, then try again.');
            }
        }

        try {
            Artisan::call('storage:link');
        } catch (Throwable $e) {
            return back()->with('error', 'Could not create the link: '.$e->getMessage());
        }

        foreach (config('filesystems.links', []) as $link => $target) {
            if ($health->storageLink($link, $target)['status'] !== SystemHealth::OK) {
                $failed[] = basename($link);
            }
        }

        return $failed === []
            ? back()->with('success', 'Storage link created. Uploaded files are now publicly reachable.')
            : back()->with('error', 'The link could not be created. Run php artisan storage:link on the server (on Windows, as administrator).');
    }

    public function retryFailed(Request $request)
    {
        Gate::authorize('admin.system-health.manage');

        $validated = $request->validate(['id' => ['required', 'string', 'max:100']]);

        Artisan::call('queue:retry', ['id' => [$validated['id']]]);

        return redirect()->to(route('admin.system-health.index').'#queue')
            ->with('success', $validated['id'] === 'all' ? 'All failed jobs were sent back to the queue.' : 'The job was sent back to the queue.');
    }

    public function deleteFailed(Request $request)
    {
        Gate::authorize('admin.system-health.manage');

        $validated = $request->validate(['id' => ['required', 'string', 'max:100']]);

        $validated['id'] === 'all'
            ? Artisan::call('queue:flush')
            : Artisan::call('queue:forget', ['id' => $validated['id']]);

        return redirect()->to(route('admin.system-health.index').'#queue')
            ->with('success', $validated['id'] === 'all' ? 'All failed jobs were deleted.' : 'The failed job was deleted.');
    }
}
