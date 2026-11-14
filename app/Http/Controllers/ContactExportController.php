<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contact;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        Gate::authorize('export-contacts');

        $count = Contact::query()->count();
        AuditLog::record('contacts.exported', null, ['total_rows' => $count]);

        $filename = 'aguia-das-promos-contatos-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'nome',
                'telefone_e164',
                'origem',
                'interesses',
                'autorizacao',
                'etapa',
                'grupo_destino',
                'ultimo_convite',
                'fonte_registro',
            ], ';');

            Contact::query()
                ->with('defaultGroup')
                ->orderBy('id')
                ->chunk(200, function ($contacts) use ($handle): void {
                    foreach ($contacts as $contact) {
                        fputcsv($handle, [
                            $contact->name,
                            $contact->phone_e164,
                            $contact->origin,
                            $contact->interests_text ?? '',
                            $contact->consent_status,
                            $contact->pipeline_stage,
                            $contact->defaultGroup?->name ?? '',
                            $contact->last_invited_at?->toIso8601String() ?? '',
                            $contact->record_source,
                        ], ';');
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
