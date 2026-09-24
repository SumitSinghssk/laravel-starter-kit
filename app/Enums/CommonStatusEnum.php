<?php

namespace App\Enums;

enum CommonStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }

    public static function dotOptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => [
            'label' => $case->label(),
            'dot' => $case === self::ACTIVE ? 'bg-emerald-500' : 'bg-slate-400',
        ]])->all();
    }
}
