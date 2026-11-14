<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invitation extends Model
{
    use HasFactory;

    public const MODE_ASSISTED = 'assisted';
    public const MODE_OFFICIAL_API = 'official_api';
    public const MODE_SIMULATED = 'simulated';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNCERTAIN = 'uncertain';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ASSISTED_OPENED = 'assisted_opened';
    public const STATUS_ASSISTED_SENT_MANUAL = 'assisted_sent_manual';

    public const SENT_STATUSES = [
        self::STATUS_SENT,
        self::STATUS_DELIVERED,
        self::STATUS_READ,
        self::STATUS_ASSISTED_SENT_MANUAL,
    ];

    protected $fillable = [
        'contact_id',
        'group_id',
        'message_template_id',
        'user_id',
        'mode',
        'idempotency_key',
        'active_dedup_key',
        'is_resend',
        'resend_reason',
        'provider_message_id',
        'status',
        'status_origin',
        'record_source',
        'rendered_message',
        'wa_me_url',
        'error_code',
        'error_message',
        'queued_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'cancelled_at',
        'uncertain_at',
    ];

    protected function casts(): array
    {
        return [
            'is_resend' => 'boolean',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'uncertain_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MessageEvent::class)->orderByDesc('event_timestamp')->orderByDesc('id');
    }

    public function statusRank(): int
    {
        return match ($this->status) {
            self::STATUS_QUEUED => 10,
            self::STATUS_PROCESSING => 20,
            self::STATUS_ASSISTED_OPENED => 25,
            self::STATUS_SENT, self::STATUS_ASSISTED_SENT_MANUAL => 30,
            self::STATUS_DELIVERED => 40,
            self::STATUS_READ => 50,
            self::STATUS_FAILED, self::STATUS_UNCERTAIN, self::STATUS_CANCELLED => 25,
            default => 0,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED => 'Na fila',
            self::STATUS_PROCESSING => 'Processando',
            self::STATUS_SENT => 'Aceito pela API',
            self::STATUS_DELIVERED => 'Entregue',
            self::STATUS_READ => 'Lido',
            self::STATUS_FAILED => 'Falhou',
            self::STATUS_UNCERTAIN => 'Resultado incerto',
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_ASSISTED_OPENED => 'Conversa aberta',
            self::STATUS_ASSISTED_SENT_MANUAL => 'Confirmado manualmente',
            default => ucfirst($this->status),
        };
    }
}

