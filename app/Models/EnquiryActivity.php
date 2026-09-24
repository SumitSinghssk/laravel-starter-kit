<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryActivity extends Model
{
    public const NOTE = 'note';

    public const STATUS = 'status';

    public const REPLY = 'reply';

    public const ASSIGNED = 'assigned';

    public const FOLLOW_UP = 'follow_up';

    public const CREATED = 'created';

    public const EDITED = 'edited';

    protected $fillable = ['enquiry_id', 'user_id', 'type', 'body', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
