<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    use HasFactory;

    public const STAGE_NOVO = 'novo';
    public const STAGE_AUTORIZADO = 'autorizado';
    public const STAGE_CONVITE_ENVIADO = 'convite_enviado';
    public const STAGE_RESPONDEU = 'respondeu';
    public const STAGE_ENTRADA_CONFIRMADA = 'entrada_confirmada';
    public const STAGE_NAO_INTERESSADO = 'nao_interessado';

    public const CONSENT_PENDENTE = 'pendente';
    public const CONSENT_AUTORIZADO = 'autorizado';
    public const CONSENT_REVOGADO = 'revogado';

    public const SOURCE_REAL = 'real';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_DEMO = 'demo';

    protected $fillable = [
        'name',
        'phone_e164',
        'origin',
        'interests',
        'interests_text',
        'owner_id',
        'default_group_id',
        'pipeline_stage',
        'consent_status',
        'last_inbound_at',
        'last_invited_at',
        'joined_confirmed_at',
        'joined_confirmed_by',
        'notes',
        'record_source',
    ];

    protected function casts(): array
    {
        return [
            'interests' => 'array',
            'last_inbound_at' => 'datetime',
            'last_invited_at' => 'datetime',
            'joined_confirmed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function defaultGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'default_group_id');
    }

    public function joinedConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joined_confirmed_by');
    }

    public function consentEvents(): HasMany
    {
        return $this->hasMany(ConsentEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function suppressionEntry(): HasOne
    {
        return $this->hasOne(SuppressionEntry::class, 'phone_e164', 'phone_e164');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function messageEvents(): HasMany
    {
        return $this->hasMany(MessageEvent::class)->orderByDesc('event_timestamp')->orderByDesc('id');
    }

    public function isSuppressed(): bool
    {
        return SuppressionEntry::query()
            ->where('phone_e164', $this->phone_e164)
            ->orWhere('contact_id', $this->id)
            ->exists();
    }

    public function isWithin24hWindow(): bool
    {
        return $this->last_inbound_at !== null
            && $this->last_inbound_at->greaterThan(now()->subHours(24));
    }

    public function stageLabel(): string
    {
        return config("aguia.pipeline_stages.{$this->pipeline_stage}", ucfirst(str_replace('_', ' ', $this->pipeline_stage)));
    }

    /**
     * Scope contacts that have granted consent and have no active suppression block.
     */
    public function scopeEligible(Builder $query): Builder
    {
        return $query
            ->where('consent_status', self::CONSENT_AUTORIZADO)
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('suppression_entries')
                    ->whereColumn('suppression_entries.phone_e164', 'contacts.phone_e164');
            });
    }

    /**
     * Scope eligible contacts that do not yet have an active invitation.
     */
    public function scopeReadyToInvite(Builder $query): Builder
    {
        return $query
            ->eligible()
            ->notSentYet();
    }

    /**
     * Scope contacts without any active or sent invitation yet.
     */
    public function scopeNotSentYet(Builder $query): Builder
    {
        return $query->whereDoesntHave('invitations', fn ($inv) => $inv->whereNotIn('status', [
            Invitation::STATUS_FAILED,
            Invitation::STATUS_CANCELLED,
        ]));
    }

    /**
     * Scope contacts with at least one confirmed/sent invitation.
     */
    public function scopeAlreadySent(Builder $query): Builder
    {
        return $query->whereHas('invitations', fn ($inv) => $inv->whereIn('status', Invitation::SENT_STATUSES));
    }
}

