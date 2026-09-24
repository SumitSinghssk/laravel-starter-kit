<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DbDownloadController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            Gate::authorize('admin.settings.download-db');

            $connection = config('database.default');
            $config = config("database.connections.{$connection}");

            $host = $config['host'];
            $port = $config['port'] ?? 3306;
            $database = $config['database'];
            $username = $config['username'];
            $password = $config['password'];

            $filename = $database.'_backup_'.now()->format('Y-m-d_H-i-s').'.sql';
            $filePath = storage_path('app/'.$filename);

            $mysqldump = $this->findMysqldump();

            if (! $mysqldump) {
                return back()->with('error', 'mysqldump not found on server. Please contact your hosting provider.');
            }

            $passwordArg = $password ? '--password='.escapeshellarg($password) : '';

            $command = sprintf(
                '%s --user=%s %s --host=%s --port=%s --single-transaction --routines --triggers %s > %s 2>&1',
                escapeshellarg($mysqldump),
                escapeshellarg($username),
                $passwordArg,
                escapeshellarg($host),
                escapeshellarg((string) $port),
                escapeshellarg($database),
                escapeshellarg($filePath)
            );

            exec($command, $output, $result);

            if ($result !== 0 || ! file_exists($filePath) || filesize($filePath) === 0) {
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }

                return back()->with('error', 'Backup failed. Check logs for details.');
            }

            $firstBytes = file_get_contents($filePath, false, null, 0, 200);
            if (stripos($firstBytes, '<html') !== false || stripos($firstBytes, '<!DOCTYPE') !== false) {
                unlink($filePath);

                return back()->with('error', 'Backup failed: received HTML instead of SQL.');
            }

            $fileSize = filesize($filePath);

            register_shutdown_function(function () use ($filePath) {
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            });

            return response()->streamDownload(function () use ($filePath) {
                $handle = fopen($filePath, 'rb');

                if (! $handle) {
                    return;
                }

                while (! feof($handle)) {
                    echo fread($handle, 1024 * 1024);
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                fclose($handle);
                unlink($filePath);

            }, $filename, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Content-Length' => $fileSize,
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]);

        } catch (\Throwable $e) {
            return back()->with('error', 'Something went wrong: '.$e->getMessage());
        }
    }

    private function findMysqldump(): ?string
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            $paths = [
                'D:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\laragon\\bin\\mysql\\mysql-8.0\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            ];

            foreach ($paths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            $output = shell_exec('where mysqldump 2>NUL');
            if ($output) {
                return trim(explode(PHP_EOL, $output)[0]);
            }
        } else {
            $paths = [
                '/usr/bin/mysqldump',
                '/usr/local/bin/mysqldump',
                '/usr/local/mysql/bin/mysqldump',
                '/opt/cpanel/ea-mysql80/root/usr/bin/mysqldump',
                '/opt/cpanel/ea-mysql57/root/usr/bin/mysqldump',
                '/usr/local/cpanel/3rdparty/bin/mysqldump',
            ];

            foreach ($paths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            $output = shell_exec('which mysqldump 2>/dev/null');
            if ($output) {
                return trim($output);
            }
        }

        return null;
    }
}
