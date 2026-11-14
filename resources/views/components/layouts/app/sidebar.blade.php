@php
    $configStatus = app(\App\Services\WhatsApp\WhatsAppConfigStatus::class);
    $activeMode = $configStatus->currentMode();
    $apiConfigured = $configStatus->isConfigured();

    try {
        $readyToInviteCount = \App\Models\Contact::query()->readyToInvite()->count();

        $pendingManualConfirmCount = \App\Models\Invitation::query()
            ->where('status', \App\Models\Invitation::STATUS_ASSISTED_OPENED)
            ->count();
    } catch (\Throwable) {
        $readyToInviteCount = 0;
        $pendingManualConfirmCount = 0;
    }
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[var(--aguia-bg,#F5F6F8)] text-[var(--aguia-dark,#171717)] antialiased">
        <div class="fixed inset-x-0 top-0 z-50 h-1 bg-gradient-to-r from-[#DC2626] via-[#F9C80E] to-[#DC2626]"></div>
        <flux:sidebar sticky stashable class="border-r border-neutral-200 bg-[var(--aguia-surface,#FFFFFF)]">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mr-3 flex items-center py-1" wire:navigate>
                <x-app-logo />
            </a>

            <div class="my-2 rounded-md border border-amber-200 bg-[#F9C80E]/15 px-3 py-2 text-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-semibold text-neutral-700">Modo ativo</span>
                    @if ($activeMode === 'official_api')
                        <span class="rounded bg-[#DC2626] px-1.5 py-0.5 text-[11px] font-semibold text-white">API Oficial</span>
                    @elseif ($activeMode === 'simulated')
                        <span class="rounded bg-amber-200 px-1.5 py-0.5 text-[11px] font-semibold text-[#171717]">Simulado</span>
                    @else
                        <span class="rounded bg-[var(--aguia-primary,#F9C80E)] px-1.5 py-0.5 text-[11px] font-bold text-[#171717]">Assistido</span>
                    @endif
                </div>
                @if ($activeMode === 'official_api' && ! $apiConfigured)
                    <p class="mt-1 font-medium text-red-700">API não configurada</p>
                @endif
            </div>

            <nav class="flex flex-col gap-5 text-sm" aria-label="Navegação principal">
                <div>
                    <p class="px-2.5 pb-1.5 text-[11px] font-bold uppercase tracking-wider text-neutral-500">Dia a dia</p>
                    <div class="space-y-1">
                        <a
                            href="{{ route('dashboard') }}"
                            wire:navigate
                            class="flex items-center justify-between rounded-lg px-2.5 py-2 font-medium transition {{ request()->routeIs('dashboard') ? 'border-l-4 border-[#DC2626] bg-[#F9C80E]/25 font-bold text-[#171717]' : 'text-neutral-700 hover:bg-[#F5F6F8] hover:text-[#171717]' }}"
                        >
                            <span>Dashboard</span>
                        </a>

                        <a
                            href="{{ route('contacts.index') }}"
                            wire:navigate
                            class="flex items-center justify-between rounded-lg px-2.5 py-2 font-medium transition {{ request()->routeIs('contacts.*') ? 'border-l-4 border-[#DC2626] bg-[#F9C80E]/25 font-bold text-[#171717]' : 'text-neutral-700 hover:bg-[#F5F6F8] hover:text-[#171717]' }}"
                        >
                            <span>Contatos &amp; Envios</span>
                            @if ($readyToInviteCount > 0)
                                <span class="rounded-full bg-[#DC2626] px-2 py-0.5 text-[11px] font-bold text-white">{{ $readyToInviteCount }}</span>
                            @endif
                        </a>

                        <a
                            href="{{ route('invitations.index') }}"
                            wire:navigate
                            class="flex items-center justify-between rounded-lg px-2.5 py-2 font-medium transition {{ request()->routeIs('invitations.*') ? 'border-l-4 border-[#DC2626] bg-[#F9C80E]/25 font-bold text-[#171717]' : 'text-neutral-700 hover:bg-[#F5F6F8] hover:text-[#171717]' }}"
                        >
                            <span>Convites</span>
                            @if ($pendingManualConfirmCount > 0)
                                <span class="rounded-full bg-[#F9C80E] px-2 py-0.5 text-[11px] font-bold text-[#171717]">{{ $pendingManualConfirmCount }}</span>
                            @endif
                        </a>
                    </div>
                </div>

                <div>
                    <p class="px-2.5 pb-1.5 text-[11px] font-bold uppercase tracking-wider text-neutral-500">Captação &amp; Estrutura</p>
                    <div class="space-y-1">
                        <a
                            href="{{ route('capture.index') }}"
                            wire:navigate
                            class="flex items-center justify-between rounded-lg px-2.5 py-2 font-medium transition {{ request()->routeIs('capture.*') ? 'border-l-4 border-[#DC2626] bg-[#F9C80E]/25 font-bold text-[#171717]' : 'text-neutral-700 hover:bg-[#F5F6F8] hover:text-[#171717]' }}"
                        >
                            <span>Captação</span>
                        </a>

                        <a
                            href="{{ route('groups.index') }}"
                            wire:navigate
                            class="flex items-center justify-between rounded-lg px-2.5 py-2 font-medium transition {{ request()->routeIs('groups.*') ? 'border-l-4 border-[#DC2626] bg-[#F9C80E]/25 font-bold text-[#171717]' : 'text-neutral-700 hover:bg-[#F5F6F8] hover:text-[#171717]' }}"
                        >
                            <span>Grupos</span>
                        </a>

                        <a
                            href="{{ route('templates.index') }}"
                            wire:navigate
                            class="flex items-center justify-between rounded-lg px-2.5 py-2 font-medium transition {{ request()->routeIs('templates.*') ? 'border-l-4 border-[#DC2626] bg-[#F9C80E]/25 font-bold text-[#171717]' : 'text-neutral-700 hover:bg-[#F5F6F8] hover:text-[#171717]' }}"
                        >
                            <span>Mensagem Automática</span>
                        </a>
                    </div>
                </div>

                @if (auth()->user()?->isAdmin())
                    <div>
                        <p class="px-2.5 pb-1.5 text-[11px] font-bold uppercase tracking-wider text-neutral-500">Administração</p>
                        <div class="space-y-1">
                            <a
                                href="{{ route('crm.settings') }}"
                                wire:navigate
                                class="flex items-center justify-between rounded-lg px-2.5 py-2 font-medium transition {{ request()->routeIs('crm.settings') ? 'border-l-4 border-[#DC2626] bg-[#F9C80E]/25 font-bold text-[#171717]' : 'text-neutral-700 hover:bg-[#F5F6F8] hover:text-[#171717]' }}"
                            >
                                <span>Configurações &amp; Equipe</span>
                            </a>
                        </div>
                    </div>
                @endif
            </nav>

            <flux:spacer />

            <div class="border-t border-neutral-200 pt-3">
                <div class="flex items-center gap-2.5 rounded-lg bg-[#F5F6F8] p-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#DC2626] text-xs font-bold text-[#F9C80E]">
                        {{ auth()->user()->initials() }}
                    </span>
                    <div class="min-w-0 flex-1 leading-tight">
                        <p class="truncate text-xs font-bold text-[#171717]">{{ auth()->user()->name }}</p>
                        <p class="truncate text-[11px] text-neutral-600">{{ auth()->user()->isAdmin() ? 'Administrador' : 'Operador' }}</p>
                    </div>
                </div>

                <div class="mt-2 flex items-center justify-between gap-2 px-1 text-xs">
                    <a href="/settings/profile" wire:navigate class="font-medium text-neutral-700 hover:text-[#171717] hover:underline">
                        Meu Perfil
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="font-semibold text-red-700 hover:underline">
                            Sair
                        </button>
                    </form>
                </div>
            </div>
        </flux:sidebar>

        <!-- Mobile Top Bar -->
        <flux:header class="border-b border-neutral-200 bg-white pt-1 lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <div class="ml-2 flex items-center gap-2">
                <x-app-logo-icon class="size-7 ring-1 ring-[#DC2626]" />
                <span class="text-sm font-bold text-[#171717]">Águia das Promos</span>
            </div>
            <flux:spacer />
            <a href="{{ route('contacts.index') }}" class="rounded-md bg-[var(--aguia-primary,#F9C80E)] px-2.5 py-1 text-xs font-semibold text-[#171717]" wire:navigate>
                Contatos
            </a>
        </flux:header>

        <!-- Desktop Global Top Search Bar -->
        <div class="hidden border-b border-neutral-200 bg-white pt-1 lg:block lg:pl-64">
            <div class="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-6 py-3">
                <form action="{{ route('contacts.index') }}" method="GET" class="flex w-full max-w-md items-center gap-2">
                    <div class="relative flex-1">
                        <input
                            type="search"
                            name="q"
                            value="{{ request('q') }}"
                            placeholder="Buscar contato por nome ou telefone (+55...)"
                            class="w-full rounded-lg border border-neutral-300 bg-[#F5F6F8] px-3.5 py-1.5 text-sm text-[#171717] placeholder-neutral-500 focus:border-[#DC2626] focus:bg-white focus:outline-none"
                        />
                    </div>
                    <button type="submit" class="rounded-lg bg-[#DC2626] px-3 py-1.5 text-xs font-bold text-white hover:bg-red-700">
                        Buscar
                    </button>
                </form>

                <div class="flex items-center gap-3 text-xs">
                    <a href="{{ route('public.capture') }}" target="_blank" rel="noopener" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 font-medium text-[#171717] hover:bg-[#F5F6F8]">
                        Abrir Captação Pública
                    </a>
                    <a href="{{ route('contacts.index', ['action' => 'new']) }}" wire:navigate class="rounded-md bg-[var(--aguia-primary,#F9C80E)] px-3 py-1.5 font-bold text-[#171717] hover:bg-amber-400">
                        + Novo Contato
                    </a>
                </div>
            </div>
        </div>

        <flux:main class="!bg-[var(--aguia-bg,#F5F6F8)] !p-4 sm:!p-6">
            {{ $slot }}
        </flux:main>

        @fluxScripts
    </body>
</html>
