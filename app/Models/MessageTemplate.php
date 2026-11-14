<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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
        'media_path',
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

    /**
     * Whether this template has an existing image attached.
     */
    public function hasMedia(): bool
    {
        return $this->media_path !== null
            && trim($this->media_path) !== ''
            && Storage::disk('local')->exists($this->media_path);
    }

    /**
     * Authenticated route URL for previewing and copying the attached image in the CRM UI.
     */
    public function mediaUrl(): ?string
    {
        return $this->hasMedia() ? route('templates.media', $this) : null;
    }

    /**
     * Signed URL allowing external Meta Cloud API servers to fetch the template header image.
     */
    public function publicSignedMediaUrl(): ?string
    {
        if (! $this->hasMedia()) {
            return null;
        }

        return URL::temporarySignedRoute('templates.media.public', now()->addDays(7), [
            'template' => $this->id,
        ]);
    }

    /**
     * Replace the template image on local storage and persist the new path.
     */
    public function attachMedia(UploadedFile $file): void
    {
        $oldPath = $this->media_path;
        $newPath = $file->store('template-media', 'local');

        $this->update(['media_path' => $newPath]);

        if ($oldPath !== null && $oldPath !== '' && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }
    }

    /**
     * Delete the stored template image and clear the database reference.
     */
    public function removeMedia(): void
    {
        $oldPath = $this->media_path;
        $this->update(['media_path' => null]);

        if ($oldPath !== null && $oldPath !== '') {
            Storage::disk('local')->delete($oldPath);
        }
    }
}
