<?php

namespace App\Services\Transfer\Types;

use App\Models\User;
use App\Services\ActiveSessions;
use App\Services\Transfer\TransferType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserTransfer extends TransferType
{
    private array $lastActive = [];

    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Users';
    }

    public function singular(): string
    {
        return 'user';
    }

    public function viewPermission(): string
    {
        return 'admin.users.view';
    }

    public function listUrl(): string
    {
        return route('admin.users.index');
    }

    public function columns(): array
    {
        return [
            'name' => ['Name', true, ''],
            'email' => ['Email', true, ''],
            'status' => ['active or inactive', false, ''],
            'roles' => ['Roles, separated by |', false, ''],
            'joined' => ['Account created', false, ''],
            'last_active' => ['Last activity in the admin (signed-in sessions only)', false, ''],
        ];
    }

    public function exportQuery(): Builder
    {
        $this->lastActive = app(ActiveSessions::class)->lastActivity(User::pluck('id')->all());

        return User::query()->with('roles:id,name')->orderBy('name');
    }

    public function exportRow(Model $user): array
    {
        return [$user->name, $user->email, $user->status?->value ?? $user->status, $user->roles->pluck('name')->implode(' | '), $user->created_at, $this->lastActive[$user->id] ?? null];
    }
}
