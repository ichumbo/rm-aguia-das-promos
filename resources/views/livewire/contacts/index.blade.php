<div class="mx-auto max-w-[1440px] space-y-5">
    <!-- Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-[#171717]">Contatos & Pipeline de Convites</h1>
            <p class="text-sm text-neutral-600">
                Modo atual:
                <strong>{{ $mode === 'assisted' ? 'Modo Assistido (wa.me)' : ($mode === 'simulated' ? 'Modo Simulado (Homologação)' : 'API Oficial WhatsApp') }}</strong>
                @if ($mode === 'official_api' && ! $isApiConfigured)
                    <span class="ml-1 rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">API não configurada</span>
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if (auth()->user()?->isAdmin())
                <a href="{{ route('contacts.export') }}" class="rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-xs font-semibold text-[#171717] hover:bg-neutral-50">
                    Exportar CSV
                </a>
            @endif
            <button type="button" wire:click="openImportModal" class="rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-xs font-semibold text-[#171717] hover:bg-neutral-50">
                Subir texto / CSV
            </button>
            <button type="button" wire:click="openCreateModal" class="rounded-lg bg-[#F9C80E] px-4 py-2 text-xs font-bold text-[#171717] hover:bg-amber-400">
                + Novo contato
            </button>
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

    <!-- Quick Queue Tabs & Message Template Selector -->
    <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 bg-white p-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="setQueueView('all')"
                class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $queueView === 'all' ? 'bg-[#171717] text-white' : 'bg-[#F5F6F8] text-neutral-700 hover:bg-neutral-200' }}"
            >
                Todos
            </button>
            <button
                type="button"
                wire:click="setQueueView('not_sent')"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $queueView === 'not_sent' ? 'bg-[#171717] text-white' : 'bg-[#F5F6F8] text-neutral-700 hover:bg-neutral-200' }}"
            >
                <span>Não enviados ainda</span>
                <span class="rounded-full bg-white text-[#171717] px-1.5 py-0.5 text-[10px] font-bold">{{ $queueCounts['not_sent'] }}</span>
            </button>
            <button
                type="button"
                wire:click="setQueueView('ready')"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $queueView === 'ready' ? 'bg-[#F9C80E] text-[#171717]' : 'bg-[#F5F6F8] text-neutral-700 hover:bg-neutral-200' }}"
            >
                <span>Prontos para convidar</span>
                <span class="rounded-full bg-white/90 px-1.5 py-0.5 text-[10px] font-bold text-[#171717]">{{ $queueCounts['ready'] }}</span>
            </button>
            <button
                type="button"
                wire:click="setQueueView('pending_confirm')"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $queueView === 'pending_confirm' ? 'bg-[#171717] text-white' : 'bg-[#F5F6F8] text-neutral-700 hover:bg-neutral-200' }}"
            >
                <span>Aguardando confirmação</span>
                <span class="rounded-full {{ $queueView === 'pending_confirm' ? 'bg-[#F9C80E] text-[#171717]' : 'bg-white text-[#171717]' }} px-1.5 py-0.5 text-[10px] font-bold">{{ $queueCounts['pending_confirm'] }}</span>
            </button>
            <button
                type="button"
                wire:click="setQueueView('sent')"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition {{ in_array($queueView, ['sent', 'invited'], true) ? 'bg-emerald-700 text-white' : 'bg-[#F5F6F8] text-neutral-700 hover:bg-neutral-200' }}"
            >
                <span>Já enviados</span>
                <span class="rounded-full bg-white text-[#171717] px-1.5 py-0.5 text-[10px] font-bold">{{ $queueCounts['sent'] }}</span>
            </button>
        </div>

        <div class="flex items-center gap-2">
            <label class="text-xs font-semibold text-neutral-700">Modelo do texto:</label>
            <select wire:model.live="selectedTemplateId" class="rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-medium text-[#171717]">
                <option value="">Padrão ativo</option>
                @foreach ($templates as $tpl)
                    <option value="{{ $tpl->id }}">{{ $tpl->display_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Compact Filters Bar -->
    @php
        $hasAdvancedFilters = $showAdvancedFilters || $stageFilter !== '' || $consentFilter !== '' || $groupFilter !== '' || $sourceFilter !== '';
    @endphp
    <div class="rounded-xl border border-neutral-200 bg-white p-3.5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-1 flex-wrap items-center gap-2.5">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar nome ou telefone..."
                    class="w-full min-w-[220px] flex-1 rounded-lg border border-neutral-300 bg-[#F5F6F8] px-3 py-2 text-sm text-[#171717] sm:max-w-sm"
                />

                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-neutral-200 bg-[#F5F6F8] px-3 py-2 text-xs font-medium text-[#171717]">
                    <input type="checkbox" wire:model.live="onlyEligible" class="h-4 w-4 rounded border-neutral-400" />
                    <span>Somente elegíveis</span>
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs">
                <button
                    type="button"
                    wire:click="$toggle('showAdvancedFilters')"
                    class="rounded-lg border border-neutral-300 bg-white px-3 py-2 font-semibold text-[#171717] hover:bg-neutral-50"
                >
                    {{ $hasAdvancedFilters ? 'Ocultar filtros' : 'Mais filtros' }}
                </button>

                @if ($search !== '' || $onlyEligible || $stageFilter !== '' || $consentFilter !== '' || $groupFilter !== '' || $sourceFilter !== '')
                    <button type="button" wire:click="clearFilters" class="px-2 py-1.5 font-semibold text-neutral-600 underline hover:text-[#171717]">
                        Limpar
                    </button>
                @endif
            </div>
        </div>

        @if ($hasAdvancedFilters)
            <div class="mt-3 grid gap-2.5 border-t border-neutral-200 pt-3 sm:grid-cols-2 lg:grid-cols-4">
                <select wire:model.live="stageFilter" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs">
                    <option value="">Todas as etapas</option>
                    @foreach ($stages as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select wire:model.live="consentFilter" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs">
                    <option value="">Toda autorização</option>
                    <option value="autorizado">Autorizado</option>
                    <option value="pendente">Pendente</option>
                    <option value="revogado">Revogado / Descadastro</option>
                </select>

                <select wire:model.live="groupFilter" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs">
                    <option value="">Todos os grupos</option>
                    @foreach ($groups as $g)
                        <option value="{{ $g->id }}">{{ $g->name }}</option>
                    @endforeach
                </select>

                <select wire:model.live="sourceFilter" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs">
                    <option value="">Todas as fontes</option>
                    <option value="real">Real (Captação/Import)</option>
                    <option value="manual">Manual</option>
                    <option value="demo">Demonstração (Dev)</option>
                </select>
            </div>
        @endif
    </div>

    <!-- Desktop Clean 5-Column Table -->
    <div class="hidden rounded-xl border border-neutral-200 bg-white md:block">
        <div class="overflow-x-auto lg:overflow-visible">
            <table class="min-w-full divide-y divide-neutral-200 text-left text-xs">
                <thead class="bg-[#F5F6F8] font-semibold uppercase text-neutral-600">
                    <tr>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('name')" class="inline-flex items-center gap-1 whitespace-nowrap hover:text-[#171717]">
                                Contato
                                @if ($sortField === 'name') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('consent_status')" class="inline-flex items-center gap-1 whitespace-nowrap hover:text-[#171717]">
                                Autorização
                                @if ($sortField === 'consent_status') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 whitespace-nowrap">Grupo de destino</th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('last_invited_at')" class="inline-flex items-center gap-1 whitespace-nowrap hover:text-[#171717]">
                                Status do envio
                                @if ($sortField === 'last_invited_at') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white">
                    @forelse ($contacts as $contact)
                        @php
                            $lastInvite = $contact->invitations->first();
                            $isSuppressed = $contact->suppressionEntry !== null || $contact->consent_status === 'revogado';
                            $isAuthorized = ! $isSuppressed && $contact->consent_status === 'autorizado';
                            $hasActiveInvite = $lastInvite !== null && ! in_array($lastInvite->status, ['failed', 'cancelled'], true);
                        @endphp
                        <tr wire:key="contact-row-{{ $contact->id }}" class="hover:bg-[#F5F6F8]/60">
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-1.5">
                                    <button type="button" wire:click="openDrawer({{ $contact->id }})" class="text-left text-sm font-bold text-[#171717] hover:underline">
                                        {{ $contact->name }}
                                    </button>
                                    @if ($contact->record_source !== 'real')
                                        <span class="whitespace-nowrap rounded border border-neutral-300 bg-[#F5F6F8] px-1.5 py-0.5 text-[10px] font-semibold uppercase text-neutral-600">
                                            {{ $contact->record_source }}
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-0.5 font-mono text-xs text-neutral-600">{{ $contact->phone_e164 }}</div>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                @if ($isSuppressed)
                                    <span class="inline-flex whitespace-nowrap rounded-md bg-red-100 px-2.5 py-1 text-[11px] font-semibold text-red-800">Descadastrado</span>
                                @elseif ($isAuthorized)
                                    <span class="inline-flex whitespace-nowrap rounded-md bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800">Autorizado</span>
                                @else
                                    <span class="inline-flex whitespace-nowrap rounded-md bg-neutral-200 px-2.5 py-1 text-[11px] font-semibold text-neutral-700">Sem autorização</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <select
                                    wire:change="updateContactGroup({{ $contact->id }}, $event.target.value)"
                                    class="w-full max-w-[190px] rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs text-[#171717]"
                                >
                                    <option value="">Selecionar grupo</option>
                                    @foreach ($activeGroups as $g)
                                        <option value="{{ $g->id }}" @selected($contact->default_group_id === $g->id)>{{ $g->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                @if ($lastInvite)
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex whitespace-nowrap rounded-md border border-neutral-200 bg-[#F5F6F8] px-2 py-0.5 text-[11px] font-semibold text-[#171717]">
                                            {{ $lastInvite->statusLabel() }}
                                        </span>
                                        <span class="text-[11px] text-neutral-500">{{ $lastInvite->created_at->format('d/m H:i') }}</span>
                                    </div>
                                    <div class="mt-1 text-[11px] text-neutral-500">Etapa: {{ $contact->stageLabel() }}</div>
                                @else
                                    <span class="inline-flex whitespace-nowrap rounded-md border border-neutral-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-neutral-600">
                                        {{ $contact->stageLabel() }}
                                    </span>
                                    <div class="mt-1 text-[11px] text-neutral-400">Nunca convidado</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    @if ($isAuthorized && ! $hasActiveInvite)
                                        <button
                                            type="button"
                                            wire:click="invite({{ $contact->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="invite({{ $contact->id }})"
                                            class="whitespace-nowrap rounded-lg bg-[#F9C80E] px-3 py-1.5 text-xs font-bold text-[#171717] hover:bg-amber-400 disabled:opacity-50"
                                        >
                                            <span wire:loading.remove wire:target="invite({{ $contact->id }})">{{ $primaryLabel }}</span>
                                            <span wire:loading wire:target="invite({{ $contact->id }})">Enviando...</span>
                                        </button>
                                    @elseif ($isAuthorized && $lastInvite?->status === 'assisted_opened')
                                        <button
                                            type="button"
                                            wire:click="confirmManualSend({{ $lastInvite->id }})"
                                            class="whitespace-nowrap rounded-lg bg-[#171717] px-3 py-1.5 text-xs font-bold text-white hover:bg-neutral-800"
                                        >
                                            Confirmar envio manual
                                        </button>
                                    @elseif ($isAuthorized && $hasActiveInvite)
                                        <button
                                            type="button"
                                            wire:click="openResendModal({{ $contact->id }})"
                                            class="whitespace-nowrap rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-[#171717] hover:bg-neutral-50"
                                        >
                                            Reenviar
                                        </button>
                                    @elseif (! $isSuppressed)
                                        <button
                                            type="button"
                                            wire:click="authorizeContact({{ $contact->id }})"
                                            class="whitespace-nowrap rounded-lg border border-emerald-400 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-900 hover:bg-emerald-100"
                                        >
                                            Autorizar
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            disabled
                                            title="Descadastrado: bloqueado permanentemente"
                                            class="cursor-not-allowed whitespace-nowrap rounded-lg bg-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-500"
                                        >
                                            Bloqueado
                                        </button>
                                    @endif

                                    <button type="button" wire:click="openDrawer({{ $contact->id }})" class="whitespace-nowrap rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-[#171717] hover:bg-neutral-50">
                                        Ficha
                                    </button>

                                    @if (! $isSuppressed || ($isAuthorized && $lastInvite?->status === 'assisted_opened' && $lastInvite->wa_me_url))
                                        <details class="relative inline-block text-left">
                                            <summary class="cursor-pointer list-none whitespace-nowrap rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                                                Mais ▾
                                            </summary>
                                            <div class="absolute right-0 z-20 mt-1.5 w-44 space-y-1 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-lg">
                                                @if ($isAuthorized && $lastInvite?->status === 'assisted_opened' && $lastInvite->wa_me_url)
                                                    <a
                                                        href="{{ $lastInvite->wa_me_url }}"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="block rounded-lg px-2.5 py-1.5 text-left text-xs font-semibold text-[#171717] hover:bg-[#F5F6F8]"
                                                    >
                                                        Reabrir WhatsApp
                                                    </a>
                                                @endif
                                                @if (! $isSuppressed)
                                                    <button type="button" wire:click="markResponded({{ $contact->id }})" class="block w-full rounded-lg px-2.5 py-1.5 text-left text-xs font-medium text-neutral-700 hover:bg-[#F5F6F8]">
                                                        Respondeu
                                                    </button>
                                                    <button type="button" wire:click="confirmEntry({{ $contact->id }})" class="block w-full rounded-lg px-2.5 py-1.5 text-left text-xs font-semibold text-emerald-800 hover:bg-emerald-50">
                                                        Entrou no grupo
                                                    </button>
                                                    <button type="button" wire:click="doNotContact({{ $contact->id }})" class="block w-full rounded-lg px-2.5 py-1.5 text-left text-xs font-semibold text-red-700 hover:bg-red-50">
                                                        Não contatar
                                                    </button>
                                                @endif
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-sm text-neutral-500">
                                Nenhum contato encontrado com os filtros atuais.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($contacts->hasPages())
            <div class="border-t border-neutral-200 px-5 py-3">
                {{ $contacts->links() }}
            </div>
        @endif
    </div>

    <!-- Mobile Responsive Cards -->
    <div class="space-y-3 md:hidden">
        @forelse ($contacts as $contact)
            @php
                $lastInvite = $contact->invitations->first();
                $isSuppressed = $contact->suppressionEntry !== null || $contact->consent_status === 'revogado';
                $isAuthorized = ! $isSuppressed && $contact->consent_status === 'autorizado';
                $hasActiveInvite = $lastInvite !== null && ! in_array($lastInvite->status, ['failed', 'cancelled'], true);
            @endphp
            <article wire:key="contact-card-{{ $contact->id }}" class="rounded-xl border border-neutral-200 bg-white p-4 space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <button type="button" wire:click="openDrawer({{ $contact->id }})" class="text-left text-base font-bold text-[#171717]">
                            {{ $contact->name }}
                        </button>
                        <p class="font-mono text-xs text-neutral-600">{{ $contact->phone_e164 }}</p>
                    </div>
                    <span class="rounded border border-neutral-300 bg-[#F5F6F8] px-2 py-0.5 text-[11px] font-semibold">
                        {{ $contact->stageLabel() }}
                    </span>
                </div>

                <div class="flex flex-wrap gap-1.5 text-[11px]">
                    @if ($isSuppressed)
                        <span class="rounded bg-red-100 px-2 py-0.5 font-semibold text-red-800">Descadastrado</span>
                    @elseif ($isAuthorized)
                        <span class="rounded bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800">Autorizado</span>
                    @else
                        <span class="rounded bg-neutral-200 px-2 py-0.5 font-semibold text-neutral-700">Sem autorização</span>
                    @endif
                    <span class="rounded bg-[#F5F6F8] px-2 py-0.5 text-neutral-700">Origem: {{ $contact->origin }}</span>
                    @if ($contact->interests_text)
                        <span class="rounded bg-[#F5F6F8] px-2 py-0.5 text-neutral-700">{{ $contact->interests_text }}</span>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    @if ($isAuthorized && ! $hasActiveInvite)
                        <button type="button" wire:click="invite({{ $contact->id }})" wire:loading.attr="disabled" class="flex-1 rounded-lg bg-[#F9C80E] px-3 py-2 text-xs font-bold text-[#171717]">
                            {{ $primaryLabel }}
                        </button>
                    @elseif ($isAuthorized && $lastInvite?->status === 'assisted_opened')
                        @if ($lastInvite->wa_me_url)
                            <a
                                href="{{ $lastInvite->wa_me_url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-[#171717]"
                            >
                                Reabrir WhatsApp
                            </a>
                        @endif
                        <button type="button" wire:click="confirmManualSend({{ $lastInvite->id }})" class="flex-1 rounded-lg bg-[#171717] px-3 py-2 text-xs font-bold text-white">
                            Confirmar envio manual
                        </button>
                    @endif
                    <button type="button" wire:click="openDrawer({{ $contact->id }})" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold">
                        Abrir ficha
                    </button>
                    @if (! $isSuppressed)
                        <button type="button" wire:click="confirmEntry({{ $contact->id }})" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900">
                            Confirmar entrada
                        </button>
                        <button type="button" wire:click="doNotContact({{ $contact->id }})" class="rounded-lg border border-red-300 bg-white px-3 py-2 text-xs font-semibold text-red-700">
                            Não contatar
                        </button>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-neutral-200 bg-white p-6 text-center text-sm text-neutral-500">
                Nenhum contato encontrado.
            </div>
        @endforelse
        <div>{{ $contacts->links() }}</div>
    </div>

    <!-- Modal: Cadastro Manual -->
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-neutral-200 bg-white p-6">
                <h2 class="text-lg font-bold text-[#171717]">Cadastrar contato manualmente</h2>
                <p class="mt-1 text-xs text-neutral-600">Cadastro manual não gera autorização automática. Informe evidência apenas se houver consentimento comprovado.</p>

                <form wire:submit="createManualContact" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold">Nome completo</label>
                        <input type="text" wire:model="newName" required class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                        @error('newName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-4 gap-2">
                        <div class="col-span-1">
                            <label class="block text-xs font-semibold">DDI</label>
                            <input type="text" wire:model="newCountryCode" placeholder="55" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                        </div>
                        <div class="col-span-3">
                            <label class="block text-xs font-semibold">Telefone com DDD</label>
                            <input type="text" wire:model="newPhone" required placeholder="(11) 99999-8888" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                        </div>
                    </div>
                    @error('newPhone') <p class="-mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold">Origem</label>
                            <input type="text" wire:model="newOrigin" required class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold">Grupo de destino</label>
                            <select wire:model="newGroupId" class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm">
                                <option value="">Nenhum definido</option>
                                @foreach ($activeGroups as $g)
                                    <option value="{{ $g->id }}">{{ $g->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold">Interesses (separados por vírgula)</label>
                        <input type="text" wire:model="newInterests" placeholder="Eletrônicos, Casa" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                    </div>

                    <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3.5 space-y-3">
                        <label class="flex items-center gap-2 text-xs font-semibold">
                            <input type="checkbox" wire:model.live="newHasConsentEvidence" class="h-4 w-4 rounded border-neutral-400" />
                            <span>Contato já forneceu autorização comprovada (registrar evidência)</span>
                        </label>
                        @if ($newHasConsentEvidence)
                            <div>
                                <label class="block text-xs font-semibold">Origem do aceite</label>
                                <input type="text" wire:model="newConsentOrigin" class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold">Referência da evidência (obrigatória)</label>
                                <input type="text" wire:model="newConsentEvidenceRef" placeholder="Ex: Atendimento WhatsApp #452 em 14/11" class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs" />
                                @error('newConsentEvidenceRef') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-[#F9C80E] px-4 py-2 text-xs font-bold text-[#171717]">Salvar contato</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal: Importação CSV / Colagem -->
    @if ($showImportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl border border-neutral-200 bg-white p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-[#171717]">Subir Contatos (Imagem, Texto ou CSV)</h2>
                    <button type="button" wire:click="$set('showImportModal', false)" class="text-xs font-semibold text-neutral-500">Fechar</button>
                </div>

                @if ($importStep === 'input')
                    <div class="mt-4 space-y-4">
                        <div
                            x-data="{ ocrLoading: false, ocrProgress: 0, ocrStatus: '' }"
                            class="rounded-xl border border-dashed border-neutral-300 bg-[#F5F6F8] p-4"
                        >
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs font-bold text-[#171717]">Ler números de uma imagem (Print / Foto)</p>
                                    <p class="text-[11px] text-neutral-600">Selecione uma imagem e os números encontrados serão preenchidos automaticamente abaixo.</p>
                                </div>
                                <label class="inline-flex cursor-pointer items-center justify-center rounded-lg bg-[#171717] px-3 py-1.5 text-xs font-bold text-white hover:bg-neutral-800">
                                    <span>Escolher imagem</span>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="
                                            const file = $event.target.files?.[0];
                                            if (!file || typeof window.extractTextFromImage !== 'function') return;
                                            ocrLoading = true;
                                            ocrProgress = 0;
                                            ocrStatus = 'Preparando leitura da imagem...';
                                            try {
                                                const text = await window.extractTextFromImage(file, (pct) => {
                                                    ocrProgress = pct;
                                                    ocrStatus = 'Lendo números da imagem... ' + pct + '%';
                                                });
                                                await $wire.applyOcrText(text);
                                                ocrStatus = 'Leitura concluída!';
                                            } catch (err) {
                                                ocrStatus = 'Não foi possível ler a imagem.';
                                            } finally {
                                                ocrLoading = false;
                                                $event.target.value = '';
                                            }
                                        "
                                    />
                                </label>
                            </div>
                            <p x-show="ocrStatus" x-text="ocrStatus" class="mt-2 text-xs font-semibold text-neutral-700"></p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold">Arquivo CSV ou TXT (opcional)</label>
                            <input type="file" wire:model="importCsvFile" accept=".csv,.txt" class="mt-1 block w-full text-xs" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold">Cole os números ou linhas abaixo</label>
                            <textarea
                                wire:model="importRawText"
                                rows="6"
                                placeholder="+55 11 99123-4567&#10;Maria Silva;+55 11 99876-5432"
                                class="mt-1 w-full rounded-lg border border-neutral-300 p-3 font-mono text-xs"
                            ></textarea>
                            @error('importRawText') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="flex items-center gap-2 text-xs font-semibold">
                                <input type="checkbox" wire:model="importHasHeader" class="h-4 w-4 rounded border-neutral-400" />
                                <span>Primeira linha é cabeçalho</span>
                            </label>
                            <div>
                                <label class="block text-xs font-semibold">DDI padrão (quando sem +)</label>
                                <select wire:model="importDefaultCountry" class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs">
                                    <option value="55">+55 Brasil (exige DDD completo)</option>
                                    <option value="">Exigir +DDI explícito no texto</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold">Grupo padrão do lote (opcional)</label>
                                <select wire:model="importDefaultGroupId" class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs">
                                    <option value="">Nenhum</option>
                                    @foreach ($activeGroups as $g)
                                        <option value="{{ $g->id }}">{{ $g->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2 pt-2">
                            <button type="button" wire:click="$set('showImportModal', false)" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold">Cancelar</button>
                            <button type="button" wire:click="previewImport" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold text-[#171717] hover:bg-neutral-50">Pré-visualizar & Mapear colunas</button>
                            <button type="button" wire:click="quickImportText" class="rounded-lg bg-[#F9C80E] px-4 py-2 text-xs font-bold text-[#171717] hover:bg-amber-400">Salvar contatos do texto agora</button>
                        </div>
                    </div>
                @else
                    <div class="mt-4 space-y-4">
                        @php
                            $firstRowCols = $importRows[0] ?? [];
                        @endphp
                        <div class="grid gap-3 rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3 sm:grid-cols-4">
                            @foreach (['name' => 'Coluna Nome', 'phone' => 'Coluna Telefone', 'origin' => 'Coluna Origem', 'interests' => 'Coluna Interesse'] as $field => $label)
                                <div>
                                    <label class="block text-[11px] font-bold">{{ $label }}</label>
                                    <select wire:model="importMapping.{{ $field }}" wire:change="refreshImportPreview" class="mt-1 w-full rounded border border-neutral-300 bg-white px-2 py-1 text-xs">
                                        <option value="">Ignorar</option>
                                        @foreach ($firstRowCols as $idx => $colLabel)
                                            <option value="{{ $idx }}">Col {{ $idx + 1 }} ({{ Str::limit($colLabel, 18) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>

                        @if (! empty($importPreview['summary']))
                            @php $s = $importPreview['summary']; @endphp
                            <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-5">
                                <div class="rounded border border-emerald-200 bg-emerald-50 p-2 font-semibold text-emerald-900">Novos válidos: {{ $s['valid_new'] }}</div>
                                <div class="rounded border border-neutral-200 bg-neutral-50 p-2 font-semibold">Existentes: {{ $s['existing'] }}</div>
                                <div class="rounded border border-amber-200 bg-amber-50 p-2 font-semibold text-amber-900">Descadastrados (mantidos): {{ $s['suppressed'] }}</div>
                                <div class="rounded border border-neutral-200 bg-neutral-50 p-2 font-semibold">Duplicados no lote: {{ $s['duplicate_in_batch'] }}</div>
                                <div class="rounded border border-red-200 bg-red-50 p-2 font-semibold text-red-900">Inválidos: {{ $s['invalid'] }}</div>
                            </div>
                        @endif

                        <div class="max-h-56 overflow-y-auto rounded-lg border border-neutral-200">
                            <table class="min-w-full divide-y divide-neutral-200 text-left text-xs">
                                <thead class="bg-[#F5F6F8] font-semibold">
                                    <tr>
                                        <th class="px-3 py-2">Linha</th>
                                        <th class="px-3 py-2">Nome</th>
                                        <th class="px-3 py-2">E.164</th>
                                        <th class="px-3 py-2">Situação</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200 bg-white">
                                    @foreach (array_slice($importPreview['items'] ?? [], 0, 50) as $item)
                                        <tr>
                                            <td class="px-3 py-1.5">{{ $item['line'] }}</td>
                                            <td class="px-3 py-1.5 font-medium">{{ $item['name'] }}</td>
                                            <td class="px-3 py-1.5 font-mono">{{ $item['phone_e164'] ?? $item['raw_phone'] }}</td>
                                            <td class="px-3 py-1.5">{{ $item['message'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3 space-y-2 text-xs">
                            <label class="flex items-center gap-2 font-semibold">
                                <input type="checkbox" wire:model.live="importHasConsentEvidence" class="h-4 w-4 rounded border-neutral-400" />
                                <span>Este lote possui prova documentada de autorização (nunca reativa descadastrados)</span>
                            </label>
                            @if ($importHasConsentEvidence)
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <input type="text" wire:model="importConsentScope" placeholder="Escopo (ex: convite_grupos_aguia)" class="rounded border border-neutral-300 bg-white px-2.5 py-1.5" />
                                    <input type="text" wire:model="importConsentEvidenceRef" placeholder="Referência da evidência (obrigatória)" class="rounded border border-neutral-300 bg-white px-2.5 py-1.5" />
                                </div>
                                @error('importConsentEvidenceRef') <p class="text-red-600">{{ $message }}</p> @enderror
                            @endif
                        </div>

                        <div class="flex justify-between pt-2">
                            <button type="button" wire:click="$set('importStep', 'input')" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold">Voltar</button>
                            <button type="button" wire:click="commitImport" class="rounded-lg bg-[#F9C80E] px-4 py-2 text-xs font-bold text-[#171717]">Confirmar importação</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Modal: Reenvio Auditado -->
    @if ($showResendModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl border border-neutral-200 bg-white p-6">
                <h2 class="text-lg font-bold text-[#171717]">Reenvio explícito e auditado</h2>
                <p class="mt-1 text-xs text-neutral-600">Por padrão, o sistema bloqueia convite duplicado para o mesmo contato e grupo. Informe a justificativa para registrar este reenvio.</p>
                <form wire:submit="confirmResend" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold">Justificativa do reenvio</label>
                        <input type="text" wire:model="resendReason" required placeholder="Ex: Contato pediu o link novamente pois expirou" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                        @error('resendReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showResendModal', false)" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-[#171717] px-4 py-2 text-xs font-bold text-white">Confirmar reenvio</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Slide-over Drawer: Ficha Lateral do Contato -->
    @if ($drawerContact)
        <div class="fixed inset-0 z-50 flex justify-end bg-black/40">
            <div class="h-full w-full max-w-xl overflow-y-auto border-l border-neutral-200 bg-white p-6">
                <div class="flex items-start justify-between border-b border-neutral-200 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-[#171717]">{{ $drawerContact->name }}</h2>
                        <p class="font-mono text-xs text-neutral-600">{{ $drawerContact->phone_e164 }} · Origem: {{ $drawerContact->origin }}</p>
                    </div>
                    <button type="button" wire:click="closeDrawer" class="rounded border border-neutral-300 px-2.5 py-1 text-xs font-semibold">Fechar</button>
                </div>

                <!-- Notas e atribuição (separadas da prova de autorização) -->
                <div class="mt-4 space-y-3 border-b border-neutral-200 pb-5">
                    <h3 class="text-xs font-bold uppercase text-neutral-600">Dados operacionais & Notas livres</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold">Grupo de destino</label>
                            <select wire:model="drawerGroupId" class="mt-1 w-full rounded border border-neutral-300 bg-white px-2.5 py-1.5 text-xs">
                                <option value="">Nenhum</option>
                                @foreach ($activeGroups as $g)
                                    <option value="{{ $g->id }}">{{ $g->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold">Responsável</label>
                            <select wire:model="drawerOwnerId" class="mt-1 w-full rounded border border-neutral-300 bg-white px-2.5 py-1.5 text-xs">
                                <option value="">Sem responsável</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold">Notas livres do atendimento (não valem como consentimento)</label>
                        <textarea wire:model="drawerNotes" rows="3" class="mt-1 w-full rounded-lg border border-neutral-300 p-2.5 text-xs"></textarea>
                    </div>
                    <button type="button" wire:click="saveDrawerDetails" class="rounded-lg bg-[#171717] px-3 py-1.5 text-xs font-bold text-white">
                        Salvar ficha
                    </button>
                </div>

                <!-- Prova de Autorização (separada das notas!) -->
                <div class="mt-4 space-y-3 border-b border-neutral-200 pb-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase text-neutral-600">Prova de Autorização & Descadastro</h3>
                        <span class="rounded bg-[#F5F6F8] px-2 py-0.5 text-[11px] font-bold">{{ strtoupper($drawerContact->consent_status) }}</span>
                    </div>

                    @if ($drawerContact->suppressionEntry)
                        <div class="rounded-lg border border-red-300 bg-red-50 p-3 text-xs text-red-900">
                            <strong>Descadastrado em {{ $drawerContact->suppressionEntry->created_at->format('d/m/Y H:i') }}</strong>
                            — Motivo: {{ $drawerContact->suppressionEntry->reason }} (origem: {{ $drawerContact->suppressionEntry->source }}).
                            Retenção do bloqueio até {{ $drawerContact->suppressionEntry->retain_until?->format('d/m/Y') ?? 'indeterminado' }}.
                        </div>
                    @endif

                    <div class="space-y-2">
                        @forelse ($drawerContact->consentEvents as $ce)
                            <div class="rounded border border-neutral-200 bg-[#F5F6F8] p-2.5 text-xs">
                                <div class="flex justify-between font-semibold">
                                    <span>{{ $ce->status === 'granted' ? 'CONCEDIDA' : 'REVOGADA' }} · {{ $ce->origin }}</span>
                                    <span>{{ $ce->occurred_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <p class="mt-0.5 text-neutral-600">
                                    Escopo: {{ $ce->scope }} · Versão: {{ $ce->policy_version }} · Evidência: {{ $ce->evidence_reference ?? '—' }}
                                </p>
                            </div>
                        @empty
                            <p class="text-xs text-neutral-500">Nenhum evento de autorização registrado.</p>
                        @endforelse
                    </div>

                    @if (! $drawerContact->suppressionEntry && $drawerContact->consent_status !== 'autorizado')
                        <div class="flex gap-2 pt-1">
                            <input type="text" wire:model="drawerManualConsentRef" placeholder="Referência da prova (ex: Protocolo #123)" class="flex-1 rounded border border-neutral-300 px-2.5 py-1.5 text-xs" />
                            <button type="button" wire:click="recordConsentFromDrawer" class="rounded bg-[#F9C80E] px-3 py-1.5 text-xs font-bold text-[#171717]">
                                Registrar autorização
                            </button>
                        </div>
                        @error('drawerManualConsentRef') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @endif
                </div>

                <!-- Linha do tempo de mensagens e alterações -->
                <div class="mt-4 space-y-3">
                    <h3 class="text-xs font-bold uppercase text-neutral-600">Linha do tempo (Convites, Eventos e Auditoria)</h3>
                    <div class="space-y-2 text-xs">
                        @foreach ($drawerContact->invitations as $inv)
                            <div class="rounded border border-neutral-200 p-2.5">
                                <div class="flex justify-between font-semibold">
                                    <span>Convite ({{ $inv->mode }}) — {{ $inv->statusLabel() }}</span>
                                    <span>{{ $inv->created_at->format('d/m H:i') }}</span>
                                </div>
                                <p class="text-neutral-600">Grupo: {{ $inv->group?->name }} · Origem: {{ $inv->status_origin }}</p>
                                @if ($inv->error_message)
                                    <p class="mt-1 text-red-700">{{ $inv->error_message }}</p>
                                @endif
                            </div>
                        @endforeach

                        @foreach ($drawerAuditLogs as $log)
                            <div class="rounded border border-neutral-200 bg-[#F5F6F8] p-2">
                                <div class="flex justify-between">
                                    <span class="font-semibold">{{ $log->action }}</span>
                                    <span class="text-neutral-500">{{ $log->created_at->format('d/m H:i') }}</span>
                                </div>
                                <p class="text-neutral-600">Por: {{ $log->user?->name ?? 'Sistema/Webhook' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if (auth()->user()?->isAdmin())
                    <div class="mt-6 border-t border-neutral-200 pt-4">
                        <button
                            type="button"
                            wire:click="deleteContactPermanently({{ $drawerContact->id }})"
                            wire:confirm="Confirmar exclusão permanente dos dados deste contato? Se houver descadastro, o bloqueio mínimo será mantido."
                            class="rounded border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-800 hover:bg-red-100"
                        >
                            Excluir contato (Admin / LGPD)
                        </button>
                    </div>
                @endif
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

