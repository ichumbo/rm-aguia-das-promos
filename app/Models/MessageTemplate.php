<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    use HasFactory;

    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PAUSED = 'PAUSED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_DISABLED = 'DISABLED';

    protected $attributes = [
        'category' => 'MARKETING',
        'language' => 'pt_BR',
        'status' => self::STATUS_APPROVED,
        'is_default' => false,
    ];

    protected $fillable = [
        'name',
        'display_name',
        'category',
        'language',
        'status',
        'body_text',
        'variables',
        'meta_template_id',
        'is_default',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_default' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Extract {{variable}} placeholders in order of appearance.
     *
     * @return list<string>
     */
    public function extractPlaceholders(): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $this->body_text, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Render template message with supplied variables.
     *
     * @param array<string, string> $values
     */
    public function render(array $values): string
    {
        $text = $this->body_text;

        foreach ($values as $key => $value) {
            $text = preg_replace('/\{\{\s*'.preg_quote($key, '/').'\s*\}\}/', $value, $text) ?? $text;
        }

        return $text;
    }
}
