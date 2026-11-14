<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuppressionEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'phone_e164',
        'reason',
        'source',
        'retain_until',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'retain_until' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
