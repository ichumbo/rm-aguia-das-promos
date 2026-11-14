<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplateMediaController extends Controller
{
    public function show(MessageTemplate $template): StreamedResponse
    {
        $path = $template->media_path;

        if ($path === null || $path === '' || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path, basename($path), [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
