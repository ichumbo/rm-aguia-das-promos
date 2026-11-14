<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? 'Águia das Promos CRM' }}</title>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])

@php
    try {
        $palettePrimary = \App\Models\AppSetting::getValue('palette_primary', config('aguia.palette.primary'));
        $paletteDark = \App\Models\AppSetting::getValue('palette_dark', config('aguia.palette.dark'));
        $paletteSurface = \App\Models\AppSetting::getValue('palette_surface', config('aguia.palette.surface'));
        $paletteBg = \App\Models\AppSetting::getValue('palette_bg', config('aguia.palette.background'));
    } catch (\Throwable) {
        $palettePrimary = '#F9C80E';
        $paletteDark = '#171717';
        $paletteSurface = '#FFFFFF';
        $paletteBg = '#F5F6F8';
    }
@endphp
<style>
    :root {
        --aguia-primary: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) $palettePrimary) ? $palettePrimary : '#F9C80E' }};
        --aguia-dark: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) $paletteDark) ? $paletteDark : '#171717' }};
        --aguia-surface: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) $paletteSurface) ? $paletteSurface : '#FFFFFF' }};
        --aguia-bg: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) $paletteBg) ? $paletteBg : '#F5F6F8' }};
    }
</style>
