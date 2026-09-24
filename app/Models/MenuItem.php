<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    protected $fillable = ['menu_id', 'parent_id', 'position', 'label', 'url', 'style', 'image', 'description', 'new_tab', 'is_active'];

    protected $casts = [
        'new_tab' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
