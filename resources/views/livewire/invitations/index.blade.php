<div class="mx-auto max-w-[1440px] space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-[#171717]">Convites, Fila & Falhas</h1>
            <p class="text-sm text-neutral-600">Acompanhe envios assistidos, enfileirados, entregues por webhook, incertos e jobs com falha.</p>
        </div>

        @if (auth()->user()?->isAdmin())
            <button
                type="button"
                wire:click="toggleQueuePause"
                class="rounded-lg border px-4 py-2 text-xs font-bold {{ $queuePaused ? 'border-emerald-400 bg-emerald-50 text-emerald-900' : 'border-red-300 bg-red-50 text-red-900' }}"
            >
                {{ $queuePaused ? 'Retomar fila de envios' : 'Pausar fila de envios' }}
            </button>
        @endif
    </div>

    @if ($flashMessage)
        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
            {{ $flashMessage }}
        </div>
    @endif

    @if ($flashError)
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">
            {{ $flashError }}
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-neutral-200 bg-white p-4 text-xs">
            <span class="text-neutral-500">Estado da fila</span>
            <p class="mt-1 text-base font-bold text-[#171717]">{{ $queuePaused ? 'PAUSADA' : 'ATIVA' }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 text-xs">
            <span class="text-neutral-500">Jobs pendentes no banco</span>
            <p class="mt-1 text-base font-bold text-[#171717]">{{ $pendingJobsCount }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 text-xs">
            <span class="text-neutral-500">Heartbeat do worker</span>
            <p class="mt-1 text-base font-bold {{ $workerAlert ? 'text-red-700' : 'text-[#171717]' }}">
                {{ $workerAlert ? 'ALERTA: Worker parado' : ($heartbeatAt ? $heartbeatAt->format('d/m H:i') : 'Sem jobs recentes') }}
            </p>
        </div>
    </div>

    <!-- Filters -->
    <div class="grid gap-3 rounded-xl border border-neutral-200 bg-white p-4 sm:grid-cols-3">
        <select wire:model.live="statusFilter" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <option value="queued">Na fila</option>
            <option value="processing">Processando</option>
            <option value="sent">Aceito pela API</option>
            <option value="delivered">Entregue</option>
            <option value="read">Lido</option>
            <option value="assisted_opened">Conversa aberta (Assistido)</option>
            <option value="assisted_sent_manual">Confirmado manualmente</option>
            <option value="uncertain">Resultado incerto (Timeout)</option>
            <option value="failed">Falhou</option>
            <option value="cancelled">Cancelado</option>
        </select>

        <select wire:model.live="modeFilter" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm">
            <option value="">Todos os modos</option>
            <option value="assisted">Modo Assistido (wa.me)</option>
            <option value="official_api">API Oficial</option>
            <option value="simulated">Simulado (Homologação)</option>
        </select>

        <select wire:model.live="sourceFilter" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm">
            <option value="">Todas as fontes</option>
            <option value="real">Real</option>
            <option value="manual">Manual</option>
            <option value="demo">Demo / Simulado</option>
        </select>
    </div>

    <!-- Invitations Table -->
    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-left text-xs">
                <thead class="bg-[#F5F6F8] font-semibold uppercase text-neutral-600">
                    <tr>
                        <th class="px-4 py-3">Contato</th>
                        <th class="px-4 py-3">Grupo</th>
                        <th class="px-4 py-3">Modo / Fonte</th>
                        <th class="px-4 py-3">Status técnico</th>
                        <th class="px-4 py-3">ID Provedor</th>
                        <th class="px-4 py-3">Detalhe / Erro</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white">
                    @forelse ($invitations as $invitation)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-bold text-[#171717]">{{ $invitation->contact?->name ?? 'Excluído' }}</p>
                                <p class="font-mono text-[11px] text-neutral-500">{{ $invitation->contact?->phone_e164 }}</p>
                            </td>
                            <td class="px-4 py-3 text-neutral-700">{{ $invitation->group?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="font-semibold">{{ $invitation->mode }}</span>
                                <span class="ml-1 rounded bg-[#F5F6F8] px-1.5 py-0.5 text-[10px] uppercase text-neutral-600">{{ $invitation->record_source }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded border border-neutral-300 bg-[#F5F6F8] px-2 py-0.5 font-semibold text-[#171717]">
                                    {{ $invitation->statusLabel() }}
                                </span>
                                <div class="mt-0.5 text-[11px] text-neutral-500">{{ $invitation->created_at->format('d/m H:i') }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-[11px] text-neutral-600">
                                {{ $invitation->provider_message_id ? Str::limit($invitation->provider_message_id, 26) : '—' }}
                            </td>
                            <td class="max-w-xs px-4 py-3 text-[11px] text-red-700">
                                {{ $invitation->error_message ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($invitation->status === 'assisted_opened')
                                    <button type="button" wire:click="confirmManualSend({{ $invitation->id }})" class="rounded bg-[#171717] px-2.5 py-1 font-bold text-white">
                                        Confirmar envio manual
                                    </button>
                                @elseif (in_array($invitation->status, ['failed', 'uncertain', 'cancelled'], true) && $invitation->contact)
                                    <button type="button" wire:click="openResendModal({{ $invitation->id }})" class="rounded border border-neutral-300 bg-white px-2.5 py-1 font-semibold text-[#171717]">
                                        Reenviar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-sm text-neutral-500">
                                Nenhum convite registrado ainda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-neutral-200 px-5 py-3">
            {{ $invitations->links() }}
        </div>
    </div>

    <!-- Failed Jobs Table -->
    <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
        <div class="border-b border-neutral-200 px-5 py-3.5">
            <h2 class="text-sm font-bold text-[#171717]">Tabela de Jobs com Falha (failed_jobs)</h2>
        </div>
        @if ($failedJobs->isEmpty())
            <p class="p-5 text-xs text-neutral-500">Nenhum job com falha no banco.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-xs">
                    <thead class="bg-[#F5F6F8] font-semibold uppercase text-neutral-600">
                        <tr>
                            <th class="px-4 py-2.5">UUID</th>
                            <th class="px-4 py-2.5">Fila</th>
                            <th class="px-4 py-2.5">Erro</th>
                            <th class="px-4 py-2.5">Data</th>
                            <th class="px-4 py-2.5 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 bg-white">
                        @foreach ($failedJobs as $job)
                            <tr>
                                <td class="px-4 py-2.5 font-mono">{{ Str::limit($job->uuid, 16) }}</td>
                                <td class="px-4 py-2.5">{{ $job->queue }}</td>
                                <td class="max-w-md px-4 py-2.5 text-red-700">{{ Str::limit($job->exception, 140) }}</td>
                                <td class="px-4 py-2.5">{{ $job->failed_at }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    @if (auth()->user()?->isAdmin())
                                        <button type="button" wire:click="retryFailedJob('{{ $job->uuid }}')" class="rounded border border-neutral-300 bg-white px-2.5 py-1 font-semibold">
                                            Reprocessar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($showResendModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl border border-neutral-200 bg-white p-6">
                <h2 class="text-lg font-bold text-[#171717]">Reenvio auditado</h2>
                <form wire:submit="confirmResend" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold">Motivo do reenvio</label>
                        <input type="text" wire:model="resendReason" required class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showResendModal', false)" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-[#171717] px-4 py-2 text-xs font-bold text-white">Reenviar agora</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('open-wa-me', (event) => {
                if (event.url) {
                    window.open(event.url, '_blank', 'noopener,noreferrer');
                }
            });
        });
    </script>
</div>
