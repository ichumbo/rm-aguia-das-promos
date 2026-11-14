<div class="mx-auto max-w-[1440px] space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-[#171717]">Convites, Fila & Falhas</h1>
            <p class="text-sm text-neutral-600">
                Passo a passo simples: <strong>1) Envie para os prontos</strong> · <strong>2) Confirme os envios assistidos</strong> · <strong>3) Consulte o histórico técnico</strong>.
            </p>
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

    <!-- Step Tabs -->
    <div class="flex flex-wrap gap-2 rounded-xl border border-neutral-200 bg-white p-2">
        <button
            type="button"
            wire:click="setTab('ready')"
            class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-bold transition {{ $activeTab === 'ready' ? 'bg-[#F9C80E] text-[#171717]' : 'text-neutral-700 hover:bg-[#F5F6F8]' }}"
        >
            <span>1. Prontos para convidar</span>
            <span class="rounded-full bg-white/90 px-2 py-0.5 text-[11px] font-bold text-[#171717]">{{ $readyCount }}</span>
        </button>

        <button
            type="button"
            wire:click="setTab('pending_confirm')"
            class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-bold transition {{ $activeTab === 'pending_confirm' ? 'bg-[#171717] text-white' : 'text-neutral-700 hover:bg-[#F5F6F8]' }}"
        >
            <span>2. Aguardando confirmação</span>
            <span class="rounded-full {{ $activeTab === 'pending_confirm' ? 'bg-[#F9C80E] text-[#171717]' : 'bg-neutral-200 text-[#171717]' }} px-2 py-0.5 text-[11px] font-bold">{{ $pendingConfirmCount }}</span>
        </button>

        <button
            type="button"
            wire:click="setTab('history')"
            class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-bold transition {{ $activeTab === 'history' ? 'bg-[#171717] text-white' : 'text-neutral-700 hover:bg-[#F5F6F8]' }}"
        >
            <span>3. Histórico &amp; Fila técnica</span>
            <span class="rounded-full {{ $activeTab === 'history' ? 'bg-white/20 text-white' : 'bg-neutral-200 text-[#171717]' }} px-2 py-0.5 text-[11px] font-bold">{{ $historyCount }}</span>
        </button>
    </div>

    @if ($activeTab === 'ready')
        <!-- Tab 1: Ready to invite -->
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-bold text-[#171717]">Contatos autorizados aguardando convite</h2>
                    <p class="text-xs text-neutral-600">Escolha o modelo de mensagem abaixo e clique em <strong>{{ $primaryLabel }}</strong>. O texto já vai preenchido com nome, categoria e link do grupo.</p>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-neutral-700">Modelo:</label>
                    <select wire:model.live="selectedTemplateId" class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-medium text-[#171717]">
                        @foreach ($templates as $tpl)
                            <option value="{{ $tpl->id }}">
                                {{ $tpl->display_name }} {{ $tpl->is_default ? '(Padrão)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-xs">
                    <thead class="bg-[#F5F6F8] font-semibold uppercase text-neutral-600">
                        <tr>
                            <th class="px-4 py-3">Contato</th>
                            <th class="px-4 py-3">Telefone</th>
                            <th class="px-4 py-3">Interesse</th>
                            <th class="px-4 py-3">Grupo de destino</th>
                            <th class="px-4 py-3 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 bg-white">
                        @forelse ($readyContacts as $contact)
                            <tr wire:key="ready-contact-{{ $contact->id }}">
                                <td class="px-4 py-3">
                                    <p class="font-bold text-[#171717]">{{ $contact->name }}</p>
                                    <span class="text-[11px] text-neutral-500">{{ $contact->origin }}</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-neutral-700">{{ $contact->phone_e164 }}</td>
                                <td class="px-4 py-3 text-neutral-700">{{ $contact->interests_text ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <select
                                        wire:change="updateContactGroup({{ $contact->id }}, $event.target.value)"
                                        class="max-w-[200px] rounded border border-neutral-300 bg-white px-2 py-1 text-xs text-[#171717]"
                                    >
                                        <option value="">Padrão ativo</option>
                                        @foreach ($activeGroups as $g)
                                            <option value="{{ $g->id }}" @selected($contact->default_group_id === $g->id)>{{ $g->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        wire:click="inviteContact({{ $contact->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="inviteContact({{ $contact->id }})"
                                        class="whitespace-nowrap rounded-lg bg-[#F9C80E] px-3.5 py-2 text-xs font-bold text-[#171717] hover:bg-amber-400 disabled:opacity-50"
                                    >
                                        <span wire:loading.remove wire:target="inviteContact({{ $contact->id }})">{{ $primaryLabel }}</span>
                                        <span wire:loading wire:target="inviteContact({{ $contact->id }})">Abrindo...</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-neutral-500">
                                    Nenhum contato autorizado aguardando convite no momento.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($readyContacts->hasPages())
                <div class="border-t border-neutral-200 px-5 py-3">
                    {{ $readyContacts->links() }}
                </div>
            @endif
        </div>
    @elseif ($activeTab === 'pending_confirm')
        <!-- Tab 2: Pending Manual Confirmation -->
        <div class="space-y-3">
            @forelse ($pendingConfirmInvitations as $invitation)
                <article wire:key="pending-invite-{{ $invitation->id }}" class="rounded-xl border border-neutral-200 bg-white p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-[#171717]">{{ $invitation->contact?->name ?? 'Excluído' }}</span>
                                <span class="font-mono text-xs text-neutral-600">{{ $invitation->contact?->phone_e164 }}</span>
                                <span class="rounded bg-[#F5F6F8] px-2 py-0.5 text-[11px] font-semibold text-neutral-700">{{ $invitation->group?->name ?? '—' }}</span>
                            </div>
                            @if ($invitation->rendered_message)
                                <p class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-2.5 text-xs text-neutral-800">
                                    {{ $invitation->rendered_message }}
                                </p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            @if ($invitation->rendered_message)
                                <button
                                    type="button"
                                    data-msg="{{ $invitation->rendered_message }}"
                                    onclick="navigator.clipboard.writeText(this.dataset.msg); this.textContent='Copiado!'; setTimeout(() => this.textContent='Copiar texto', 1500);"
                                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-[#171717] hover:bg-neutral-50"
                                >
                                    Copiar texto
                                </button>
                            @endif

                            @if ($invitation->wa_me_url)
                                <a
                                    href="{{ $invitation->wa_me_url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-[#171717] hover:bg-neutral-50"
                                >
                                    Reabrir WhatsApp
                                </a>
                            @endif

                            <button
                                type="button"
                                wire:click="confirmManualSend({{ $invitation->id }})"
                                class="rounded-lg bg-[#171717] px-3.5 py-2 text-xs font-bold text-white hover:bg-neutral-800"
                            >
                                Confirmar envio manual
                            </button>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500">
                    Nenhuma conversa aberta aguardando confirmação manual.
                </div>
            @endforelse

            <div>
                {{ $pendingConfirmInvitations->links() }}
            </div>
        </div>
    @else
        <!-- Tab 3: Technical History & Queue -->
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
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-semibold">{{ $invitation->mode }}</span>
                                    <span class="ml-1 inline-flex whitespace-nowrap rounded bg-[#F5F6F8] px-1.5 py-0.5 text-[10px] uppercase text-neutral-600">{{ $invitation->record_source }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex whitespace-nowrap rounded border border-neutral-300 bg-[#F5F6F8] px-2 py-0.5 font-semibold text-[#171717]">
                                        {{ $invitation->statusLabel() }}
                                    </span>
                                    <div class="mt-0.5 text-[11px] text-neutral-500">{{ $invitation->created_at->format('d/m H:i') }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-neutral-600 whitespace-nowrap">
                                    {{ $invitation->provider_message_id ? Str::limit($invitation->provider_message_id, 26) : '—' }}
                                </td>
                                <td class="max-w-xs px-4 py-3 text-[11px] text-red-700">
                                    {{ $invitation->error_message ?: '—' }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if ($invitation->status === 'assisted_opened')
                                        <button type="button" wire:click="confirmManualSend({{ $invitation->id }})" class="whitespace-nowrap rounded bg-[#171717] px-2.5 py-1 font-bold text-white">
                                            Confirmar envio manual
                                        </button>
                                    @elseif (in_array($invitation->status, ['failed', 'uncertain', 'cancelled'], true) && $invitation->contact)
                                        <button type="button" wire:click="openResendModal({{ $invitation->id }})" class="whitespace-nowrap rounded border border-neutral-300 bg-white px-2.5 py-1 font-semibold text-[#171717]">
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
            @if ($invitations->hasPages())
                <div class="border-t border-neutral-200 px-5 py-3">
                    {{ $invitations->links() }}
                </div>
            @endif
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
    @endif

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
