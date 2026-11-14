<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[var(--aguia-bg,#F5F6F8)] text-[var(--aguia-dark,#171717)] antialiased">
        <div class="h-1.5 w-full bg-gradient-to-r from-[#DC2626] via-[#F9C80E] to-[#DC2626]"></div>
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="w-full max-w-md space-y-5">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2.5 text-center" wire:navigate>
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-[#F9C80E] to-[#DC2626] p-1 shadow-sm">
                        <x-app-logo-icon class="size-full bg-[#171717]" />
                    </span>
                    <div>
                        <p class="text-lg font-bold tracking-tight text-[#171717]">Águia das Promos</p>
                        <p class="text-xs font-semibold text-[#DC2626]">CRM de Captação e Convites da Equipe</p>
                    </div>
                </a>

                <div class="rounded-xl border border-neutral-200 border-t-4 border-t-[#DC2626] bg-white p-6 shadow-xs">
                    {{ $slot }}
                </div>

                <div class="text-center text-xs text-neutral-600">
                    <a href="{{ route('public.capture') }}" class="font-medium underline hover:text-[#171717]">
                        Ir para a página pública de captação (/inscrever)
                    </a>
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
