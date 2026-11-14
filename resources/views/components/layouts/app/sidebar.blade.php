@php
    $configStatus = app(\App\Services\WhatsApp\WhatsAppConfigStatus::class);
    $activeMode = $configStatus->currentMode();
    $apiConfigured = $configStatus->isConfigured();
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[var(--aguia-bg,#F5F6F8)] text-[var(--aguia-dark,#171717)] antialiased">
        <flux:sidebar sticky stashable class="border-r border-neutral-200 bg-[var(--aguia-surface,#FFFFFF)]">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mr-3 flex items-center py-1" wire:navigate>
                <x-app-logo />
            </a>

            <div class="my-2 rounded-md border border-neutral-200 bg-[var(--aguia-bg,#F5F6F8)] px-3 py-2 text-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-semibold text-neutral-700">Modo ativo</span>
                    @if ($activeMode === 'official_api')
                        <span class="rounded bg-[var(--aguia-dark,#171717)] px-1.5 py-0.5 text-[11px] font-semibold text-white">API Oficial</span>
                    @elseif ($activeMode === 'simulated')
                        <span class="rounded bg-amber-200 px-1.5 py-0.5 text-[11px] font-semibold text-[#171717]">Simulado</span>
                    @else
                        <span class="rounded bg-[var(--aguia-primary,#F9C80E)] px-1.5 py-0.5 text-[11px] font-semibold text-[#171717]">Assistido</span>
                    @endif
                </div>
                @if ($activeMode === 'official_api' && ! $apiConfigured)
                    <p class="mt-1 font-medium text-red-700">API não configurada</p>
                @endif
            </div>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="Operação" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        Dashboard
                    </flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('contacts.index')" :current="request()->routeIs('contacts.*')" wire:navigate>
                        Contatos
                    </flux:navlist.item>
                    <flux:navlist.item icon="user-group" :href="route('groups.index')" :current="request()->routeIs('groups.*')" wire:navigate>
                        Grupos
                    </flux:navlist.item>
                    <flux:navlist.item icon="paper-airplane" :href="route('invitations.index')" :current="request()->routeIs('invitations.*')" wire:navigate>
                        Convites
                    </flux:navlist.item>
                    <flux:navlist.item icon="document-text" :href="route('templates.index')" :current="request()->routeIs('templates.*')" wire:navigate>
                        Modelos de mensagem
                    </flux:navlist.item>
                    <flux:navlist.item icon="qr-code" :href="route('capture.index')" :current="request()->routeIs('capture.*')" wire:navigate>
                        Captação
                    </flux:navlist.item>
                </flux:navlist.group>

                @if (auth()->user()?->isAdmin())
                    <flux:navlist.group heading="Administração" class="grid mt-3">
                        <flux:navlist.item icon="cog-6-tooth" :href="route('crm.settings')" :current="request()->routeIs('crm.settings')" wire:navigate>
                            Configurações
                        </flux:navlist.item>
                    </flux:navlist.group>
                @endif
            </flux:navlist>

            <flux:spacer />

            <div class="border-t border-neutral-200 pt-3">
                <flux:dropdown position="bottom" align="start">
                    <flux:profile
                        :name="auth()->user()->name"
                        :initials="auth()->user()->initials()"
                        icon-trailing="chevrons-up-down"
                    />

                    <flux:menu class="w-[230px]">
                        <flux:menu.radio.group>
                            <div class="p-0 text-sm font-normal">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                    <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                        <span class="flex h-full w-full items-center justify-center rounded-lg bg-[var(--aguia-primary,#F9C80E)] font-bold text-[#171717]">
                                            {{ auth()->user()->initials() }}
                                        </span>
                                    </span>

                                    <div class="grid flex-1 text-left text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                        <span class="truncate text-xs text-neutral-500">
                                            {{ auth()->user()->isAdmin() ? 'Administrador' : 'Operador' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <flux:menu.radio.group>
                            <flux:menu.item href="/settings/profile" icon="user" wire:navigate>Meu Perfil</flux:menu.item>
                            @if (auth()->user()?->isAdmin())
                                <flux:menu.item :href="route('crm.settings')" icon="cog" wire:navigate>Configurações do CRM</flux:menu.item>
                            @endif
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                                Sair
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </flux:sidebar>

        <!-- Mobile Top Bar -->
        <flux:header class="border-b border-neutral-200 bg-white lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <span class="ml-2 text-sm font-bold text-[#171717]">Águia das Promos</span>
            <flux:spacer />
            <a href="{{ route('contacts.index') }}" class="rounded-md bg-[var(--aguia-primary,#F9C80E)] px-2.5 py-1 text-xs font-semibold text-[#171717]" wire:navigate>
                Contatos
            </a>
        </flux:header>

        <!-- Desktop Global Top Search Bar -->
        <div class="hidden border-b border-neutral-200 bg-white lg:block lg:pl-64">
            <div class="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-6 py-3">
                <form action="{{ route('contacts.index') }}" method="GET" class="flex w-full max-w-md items-center gap-2">
                    <div class="relative flex-1">
                        <input
                            type="search"
                            name="q"
                            value="{{ request('q') }}"
                            placeholder="Buscar contato por nome ou telefone (+55...)"
                            class="w-full rounded-lg border border-neutral-300 bg-[#F5F6F8] px-3.5 py-1.5 text-sm text-[#171717] placeholder-neutral-500 focus:border-[#171717] focus:bg-white focus:outline-none"
                        />
                    </div>
                    <button type="submit" class="rounded-lg bg-[#171717] px-3 py-1.5 text-xs font-semibold text-white hover:bg-neutral-800">
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
