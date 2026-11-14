<div class="mx-auto max-w-[1440px] space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-[#171717]">Dashboard Operacional</h1>
            <p class="text-sm text-neutral-600">Visão separada entre operação real (API), registros manuais (modo assistido) e dados de demonstração.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('contacts.index') }}" wire:navigate class="rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-xs font-semibold text-[#171717] hover:bg-neutral-50">
                Gerenciar contatos
            </a>
            <a href="{{ route('invitations.index') }}" wire:navigate class="rounded-lg bg-[#171717] px-3.5 py-2 text-xs font-semibold text-white hover:bg-neutral-800">
                Ver fila de convites
            </a>
        </div>
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

    @if (! $isApiConfigured)
        <div class="rounded-lg border border-amber-400 bg-amber-50 p-4 text-sm text-[#171717]">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <span class="inline-block rounded bg-[#F9C80E] px-2 py-0.5 text-xs font-bold text-[#171717]">API não configurada</span>
                    <span class="ml-2 font-semibold">Credenciais da Meta Cloud API pendentes no servidor.</span>
                </div>
                @if (auth()->user()?->isAdmin())
                    <a href="{{ route('crm.settings') }}" wire:navigate class="text-xs font-bold underline">Ver checklist de ativação</a>
                @endif
            </div>
            <p class="mt-2 text-xs text-neutral-700">
                Variáveis pendentes no <code>.env</code>:
                {{ collect($missingCredentials)->pluck('env')->implode(', ') }}.
                O <strong>Modo Assistido (wa.me)</strong> permanece disponível para operação imediata.
            </p>
        </div>
    @endif

    @if ($workerAlert || $queuePaused || $failedJobsCount > 0)
        <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-900">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="font-semibold">
                    @if ($queuePaused)
                        Fila de envios PAUSADA.
                    @endif
                    @if ($workerAlert)
                        Alerta de worker parado: há {{ $pendingJobsCount }} job(s) aguardando processamento sem heartbeat recente do worker.
                    @endif
                    @if ($failedJobsCount > 0)
                        Existem {{ $failedJobsCount }} job(s) com falha registrados.
                    @endif
                </div>
                <a href="{{ route('invitations.index') }}" wire:navigate class="rounded bg-red-900 px-3 py-1 text-xs font-semibold text-white">
                    Inspecionar fila e falhas
                </a>
            </div>
        </div>
    @endif

    <!-- Bloco 1: Métricas Reais -->
    <section class="rounded-xl border border-neutral-200 bg-white p-5">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wide text-[#171717]">1. Métricas Reais (Operação & API Oficial)</h2>
                <p class="text-xs text-neutral-500">Exclui registros manuais, modo simulado e dados de demonstração.</p>
            </div>
            <span class="rounded bg-[#171717] px-2 py-0.5 text-[11px] font-semibold text-white">Dados Reais</span>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3.5">
                <p class="text-xs font-medium text-neutral-600">Contatos autorizados</p>
                <p class="mt-1 text-2xl font-bold text-[#171717]">{{ $realMetrics['authorized_contacts'] }}</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3.5">
                <p class="text-xs font-medium text-neutral-600">Convites aceitos (API)</p>
                <p class="mt-1 text-2xl font-bold text-[#171717]">{{ $realMetrics['api_sent'] }}</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3.5">
                <p class="text-xs font-medium text-neutral-600">Entregues (Webhook)</p>
                <p class="mt-1 text-2xl font-bold text-[#171717]">{{ $realMetrics['delivered'] }}</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3.5">
                <p class="text-xs font-medium text-neutral-600">Respostas recebidas</p>
                <p class="mt-1 text-2xl font-bold text-[#171717]">{{ $realMetrics['responses'] }}</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3.5">
                <p class="text-xs font-medium text-neutral-600">Entradas confirmadas</p>
                <p class="mt-1 text-2xl font-bold text-[#171717]">{{ $realMetrics['manual_entries_confirmed'] }}</p>
                <span class="text-[11px] text-neutral-500">Confirmação manual</span>
            </div>
        </div>
    </section>

    <!-- Bloco 2 e 3: Registros Manuais vs Demonstração/Simulação -->
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-[#171717]">2. Registros Manuais (Modo Assistido)</h2>
                <span class="rounded bg-[#F9C80E] px-2 py-0.5 text-[11px] font-bold text-[#171717]">Origem Manual</span>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3">
                    <p class="text-xs text-neutral-600">Cadastros manuais</p>
                    <p class="mt-1 text-xl font-bold text-[#171717]">{{ $manualMetrics['manual_contacts'] }}</p>
                </div>
                <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3">
                    <p class="text-xs text-neutral-600">Conversas abertas</p>
                    <p class="mt-1 text-xl font-bold text-[#171717]">{{ $manualMetrics['assisted_opened'] }}</p>
                </div>
                <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3">
                    <p class="text-xs text-neutral-600">Envios confirmados</p>
                    <p class="mt-1 text-xl font-bold text-[#171717]">{{ $manualMetrics['assisted_confirmed'] }}</p>
                </div>
                <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3">
                    <p class="text-xs text-neutral-600">Entradas manuais</p>
                    <p class="mt-1 text-xl font-bold text-[#171717]">{{ $manualMetrics['manual_entries_confirmed'] }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-[#171717]">3. Dados de Demonstração & Simulação</h2>
                <span class="rounded border border-neutral-300 bg-[#F5F6F8] px-2 py-0.5 text-[11px] font-semibold text-neutral-700">Não contabilizado como real</span>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3">
                    <p class="text-xs text-neutral-600">Contatos de demonstração</p>
                    <p class="mt-1 text-xl font-bold text-[#171717]">{{ $demoMetrics['demo_contacts'] }}</p>
                </div>
                <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3">
                    <p class="text-xs text-neutral-600">Convites simulados / demo</p>
                    <p class="mt-1 text-xl font-bold text-[#171717]">{{ $demoMetrics['simulated_invites'] }}</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-neutral-600">
                Saúde da fila: <strong>{{ $pendingJobsCount }}</strong> pendente(s) · <strong>{{ $failedJobsCount }}</strong> falha(s) · Último heartbeat do worker: {{ $heartbeatAt ? $heartbeatAt->format('d/m H:i') : 'ainda não registrado' }}.
            </p>
        </section>
    </div>

    <!-- Próximos Contatos Elegíveis -->
    <section class="rounded-xl border border-neutral-200 bg-white">
        <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
            <div>
                <h2 class="text-base font-bold text-[#171717]">Próximos contatos elegíveis para convite</h2>
                <p class="text-xs text-neutral-600">Apenas contatos com autorização registrada, sem descadastro e prontos para envio individual.</p>
            </div>
            <a href="{{ route('contacts.index', ['eligible' => '1']) }}" wire:navigate class="text-xs font-bold text-[#171717] underline">
                Ver todos elegíveis
            </a>
        </div>

        @if ($eligibleQueue->isEmpty())
            <div class="p-6 text-center text-sm text-neutral-600">
                Nenhum contato autorizado aguardando primeiro convite no momento.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                    <thead class="bg-[#F5F6F8] text-xs font-semibold uppercase text-neutral-600">
                        <tr>
                            <th class="px-5 py-3">Contato</th>
                            <th class="px-5 py-3">Telefone</th>
                            <th class="px-5 py-3">Interesses</th>
                            <th class="px-5 py-3">Grupo de destino</th>
                            <th class="px-5 py-3">Fonte</th>
                            <th class="px-5 py-3 text-right">Ação principal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 bg-white">
                        @foreach ($eligibleQueue as $contact)
                            <tr>
                                <td class="px-5 py-3 font-semibold text-[#171717]">{{ $contact->name }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-neutral-700">{{ $contact->phone_e164 }}</td>
                                <td class="px-5 py-3 text-xs text-neutral-700">{{ $contact->interests_text ?: '—' }}</td>
                                <td class="px-5 py-3 text-xs text-neutral-700">{{ $contact->defaultGroup?->name ?? 'Padrão ativo' }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded border border-neutral-200 bg-[#F5F6F8] px-2 py-0.5 text-[11px] font-medium text-neutral-700">
                                        {{ strtoupper($contact->record_source) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button
                                        type="button"
                                        wire:click="inviteNext({{ $contact->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="inviteNext({{ $contact->id }})"
                                        class="inline-flex items-center rounded-lg bg-[#F9C80E] px-3 py-1.5 text-xs font-bold text-[#171717] hover:bg-amber-400 disabled:opacity-50"
                                    >
                                        <span wire:loading.remove wire:target="inviteNext({{ $contact->id }})">{{ $primaryButtonLabel }}</span>
                                        <span wire:loading wire:target="inviteNext({{ $contact->id }})">Processando...</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

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
