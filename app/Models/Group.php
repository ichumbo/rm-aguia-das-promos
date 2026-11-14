<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'description',
        'invite_url',
        'is_active',
        'capacity_note',
        'revoked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'default_group_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function hasValidInviteUrl(): bool
    {
        if (! $this->is_active || $this->revoked_at !== null) {
            return false;
        }

        return is_string($this->invite_url)
            && filter_var($this->invite_url, FILTER_VALIDATE_URL) !== false
            && str_starts_with($this->invite_url, 'https://');
    }
}
