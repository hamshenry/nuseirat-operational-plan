<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternalMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sender_id',
        'recipient_user_id',
        'recipient_department_id',
        'subject',
        'body',
        'parent_message_id',
        'is_read',
        'read_at',
        'deleted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function recipientDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'recipient_department_id');
    }

    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_message_id');
    }
}
