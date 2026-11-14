<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentEvent extends Model
{
    use HasFactory;

    public const STATUS_GRANTED = 'granted';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'contact_id',
        'status',
        'origin',
        'scope',
        'policy_version',
        'evidence_reference',
        'recorded_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
