<?php

use App\Http\Controllers\ContactExportController;
use App\Http\Controllers\LeadCaptureWebhookController;
use App\Http\Controllers\PublicCaptureController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\TemplateMediaController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Livewire\Capture\Index as CaptureIndex;
use App\Livewire\Contacts\Index as ContactsIndex;
use App\Livewire\Dashboard;
use App\Livewire\Groups\Index as GroupsIndex;
use App\Livewire\Invitations\Index as InvitationsIndex;
use App\Livewire\Settings\CrmSettings;
use App\Livewire\Templates\Index as TemplatesIndex;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', [PublicCaptureController::class, 'show'])->name('home');
Route::get('/inscrever', [PublicCaptureController::class, 'show'])->name('public.capture');
Route::post('/inscrever', [PublicCaptureController::class, 'store'])
    ->middleware('throttle:captacao')
    ->name('public.capture.store');

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->name('webhooks.whatsapp.handle');
Route::post('/webhooks/captacao', [LeadCaptureWebhookController::class, 'handle'])
    ->middleware('throttle:captacao')
    ->name('webhooks.capture.handle');
Route::get('/midia/modelos/{template}', [TemplateMediaController::class, 'show'])
    ->middleware('signed')
    ->name('templates.media.public');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/contatos', ContactsIndex::class)->name('contacts.index');
    Route::get('/grupos', GroupsIndex::class)->name('groups.index');
    Route::get('/convites', InvitationsIndex::class)->name('invitations.index');
    Route::get('/modelos', TemplatesIndex::class)->name('templates.index');
    Route::get('/modelos/{template}/imagem', [TemplateMediaController::class, 'show'])->name('templates.media');
    Route::get('/captacao', CaptureIndex::class)->name('capture.index');
    Route::get('/captacao/qrcode.svg', [QrCodeController::class, 'captureQr'])->name('capture.qr');
});

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/configuracoes', CrmSettings::class)->name('crm.settings');
    Route::get('/contatos/exportar', ContactExportController::class)->name('contacts.export');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
