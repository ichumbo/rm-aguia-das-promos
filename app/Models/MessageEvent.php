<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'invitation_id',
        'contact_id',
        'provider_event_id',
        'event_type',
        'event_timestamp',
        'payload_summary',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'event_timestamp' => 'datetime',
            'payload_summary' => 'array',
        ];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
