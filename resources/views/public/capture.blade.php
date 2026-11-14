<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Captação de Interessados — Águia das Promos</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F5F6F8] text-[#171717] antialiased">
    <header class="border-b border-neutral-200 bg-white">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4 sm:px-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#F9C80E] text-[#171717]">
                    <x-app-logo-icon class="size-6" />
                </div>
                <div>
                    <p class="text-base font-bold tracking-tight text-[#171717]">Águia das Promos</p>
                    <p class="text-xs text-neutral-600">Grupos oficiais de ofertas e achados</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-md border border-neutral-200 bg-[#F5F6F8] px-2.5 py-1 text-xs font-medium text-neutral-700">
                    Entrada voluntária
                </span>
                <a href="{{ route('login') }}" class="text-xs font-semibold text-neutral-700 underline hover:text-[#171717]">
                    Área da equipe
                </a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
        <div class="grid gap-6 lg:grid-cols-5">
            <section class="rounded-xl border border-neutral-200 bg-white p-6 lg:col-span-2">
                <span class="inline-block rounded bg-[#F9C80E] px-2.5 py-1 text-xs font-semibold text-[#171717]">
                    Comunidade gratuita
                </span>
                <h1 class="mt-4 text-2xl font-bold tracking-tight text-[#171717]">
                    {{ $headline }}
                </h1>
                <p class="mt-3 text-sm leading-relaxed text-neutral-700">
                    {{ $description }}
                </p>

                <div class="mt-6 space-y-3 border-t border-neutral-200 pt-5 text-sm text-neutral-700">
                    <div class="flex items-start gap-2.5">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#171717]"></span>
                        <span>Você escolhe suas categorias de interesse antes de receber o convite.</span>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#171717]"></span>
                        <span>Ninguém é adicionado automaticamente: você entra apenas se clicar no link recebido.</span>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#171717]"></span>
                        <span>Para parar de receber mensagens, basta responder <strong>SAIR</strong> a qualquer momento.</span>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-neutral-200 bg-white p-6 lg:col-span-3">
                @if (session('capture_success'))
                    <div class="mb-6 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">
                        <p class="font-semibold">Autorização registrada com sucesso</p>
                        <p class="mt-1">{{ session('capture_success') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-900">
                        <p class="font-semibold">Verifique os campos abaixo:</p>
                        <ul class="mt-1 list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('public.capture.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="name" class="block text-sm font-semibold text-[#171717]">Seu nome</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            required
                            value="{{ old('name') }}"
                            placeholder="Ex: Maria Silva"
                            class="mt-1.5 w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-[#171717] focus:border-[#171717] focus:outline-none"
                        />
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-semibold text-[#171717]">WhatsApp (com DDD)</label>
                        <div class="mt-1.5 grid grid-cols-4 gap-2">
                            <div class="col-span-1">
                                <select
                                    name="country_code"
                                    aria-label="Código do país"
                                    class="w-full rounded-lg border border-neutral-300 bg-white px-2.5 py-2.5 text-sm text-[#171717] focus:border-[#171717] focus:outline-none"
                                >
                                    <option value="55" @selected(old('country_code', '55') === '55')>+55 BR</option>
                                    <option value="351" @selected(old('country_code') === '351')>+351 PT</option>
                                    <option value="1" @selected(old('country_code') === '1')>+1 US</option>
                                </select>
                            </div>
                            <div class="col-span-3">
                                <input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    required
                                    value="{{ old('phone') }}"
                                    placeholder="(11) 99999-8888"
                                    class="w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-[#171717] focus:border-[#171717] focus:outline-none"
                                />
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-neutral-600">Informe DDD + número completo. Não adicionamos DDD ou dígitos automaticamente.</p>
                    </div>

                    <div>
                        <span class="block text-sm font-semibold text-[#171717]">Categorias de interesse</span>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($categories as $category)
                                <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-neutral-200 bg-[#F5F6F8] px-3 py-2 text-sm text-[#171717] hover:border-[#171717]">
                                    <input
                                        type="checkbox"
                                        name="interests[]"
                                        value="{{ $category }}"
                                        @checked(in_array($category, old('interests', []), true))
                                        class="h-4 w-4 rounded border-neutral-400 text-[#171717] focus:ring-[#171717]"
                                    />
                                    <span>{{ $category }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-lg border border-neutral-300 bg-[#F5F6F8] p-4">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                type="checkbox"
                                name="consent_accepted"
                                value="1"
                                @checked(old('consent_accepted') === '1')
                                class="mt-0.5 h-4 w-4 shrink-0 rounded border-neutral-500 text-[#171717] focus:ring-[#171717]"
                            />
                            <span class="text-sm leading-snug text-[#171717]">
                                Autorizo expressamente o <strong>Águia das Promos</strong> a enviar pelo WhatsApp o link de convite dos grupos selecionados acima. Sei que a participação é gratuita e posso cancelar a qualquer momento respondendo <strong>SAIR</strong>.
                            </span>
                        </label>
                    </div>

                    <div class="rounded-lg border border-neutral-200 bg-white p-3.5 text-xs leading-relaxed text-neutral-600">
                        <strong>Aviso de privacidade (versão {{ $policyVersion }}):</strong> Coletamos apenas seu nome, telefone e interesses para enviar o convite solicitado. Não vendemos listas nem compartilhamos seus dados com terceiros. Se você solicitar descadastro, mantemos somente o bloqueio mínimo necessário do número para evitar novos contatos.
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-[#F9C80E] px-4 py-3 text-sm font-bold text-[#171717] transition hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-[#171717]"
                    >
                        Solicitar convite pelo WhatsApp
                    </button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
