<?php

namespace App\Services\Transfer\Types;

use App\Models\NotFoundLog;
use App\Services\Transfer\TransferType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NotFoundTransfer extends TransferType
{
    public function key(): string
    {
        return 'not-found';
    }

    public function label(): string
    {
        return '404 errors';
    }

    public function singular(): string
    {
        return '404 error';
    }

    public function viewPermission(): string
    {
        return 'admin.not-found.view';
    }

    public function listUrl(): string
    {
        return route('admin.not-found.index');
    }

    public function columns(): array
    {
        return [
            'url' => ['Path that was not found', true, ''],
            'hits' => ['How many times', false, ''],
            'first_seen' => ['First time', false, ''],
            'last_seen' => ['Last time', false, ''],
            'came_from' => ['Last referring page', false, ''],
            'ignored' => ['yes if ignored', false, ''],
        ];
    }

    public function exportQuery(): Builder
    {
        return NotFoundLog::query()->orderByDesc('hits');
    }

    public function exportRow(Model $log): array
    {
        return [$log->path, $log->hits, $log->first_seen_at, $log->last_seen_at, $log->last_referrer, $log->ignored];
    }
}
