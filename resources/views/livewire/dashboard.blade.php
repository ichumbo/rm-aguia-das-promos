@php
    $activeMediaUrl = $activeTemplate?->mediaUrl();
    $progressPercent = $totalContacts > 0 ? (int) round(($sentCount / $totalContacts) * 100) : 0;
    $lineCount = trim($quickText) !== '' ? count(array_filter(array_map('trim', explode("\n", $quickText)))) : 0;
@endphp

<div class="mx-auto max-w-[1440px] space-y-6">
    <!-- Hero Header Card -->
    <header class="rounded-2xl border border-neutral-200/90 bg-white p-5 shadow-xs">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3.5 sm:items-center">
                <div class="hidden h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#F9C80E] to-[#DC2626] p-0.5 shadow-xs sm:flex">
                    <x-app-logo-icon class="size-full bg-[#171717]" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-extrabold tracking-tight text-[#171717] sm:text-2xl">Central de Contatos &amp; Envios</h1>
                    <p class="mt-0.5 text-sm text-neutral-600">Suba contatos por print ou texto, salve na fila e dispare sua mensagem com agilidade.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <a
                    href="{{ route('templates.index') }}"
                    wire:navigate
                    class="inline-flex items-center gap-1.5 rounded-xl border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold text-[#171717] shadow-2xs transition hover:border-amber-400 hover:bg-amber-50/40"
                >
                    <svg class="size-4 text-[#DC2626]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <span>Configurar Mensagem Automática</span>
                </a>
                <a
                    href="{{ route('contacts.index') }}"
                    wire:navigate
                    class="inline-flex items-center gap-1.5 rounded-xl bg-[#171717] px-4 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-neutral-800"
                >
                    <span>Ver Lista Completa</span>
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    </header>

    @if ($flashMessage)
        <div class="flex items-center gap-2.5 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-950 shadow-2xs">
            <svg class="size-4 shrink-0 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
            <span>{{ $flashMessage }}</span>
        </div>
    @endif

    @if ($flashError)
        <div class="flex items-center gap-2.5 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900 shadow-2xs">
            <svg class="size-4 shrink-0 text-[#DC2626]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            <span>{{ $flashError }}</span>
        </div>
    @endif

    <!-- 3 KPI Cards Modernos -->
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <!-- Total na Lista -->
        <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-2xs">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-neutral-500">Total na lista</p>
                    <p class="mt-2 text-3xl font-extrabold tracking-tight tabular-nums text-[#171717]">{{ $totalContacts }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F9C80E]/25 text-[#171717]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
            </div>
            <div class="mt-4 space-y-1.5">
                <div class="flex items-center justify-between text-[11px] font-medium text-neutral-500">
                    <span>Progresso de envios</span>
                    <span class="font-bold text-[#171717]">{{ $progressPercent }}%</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-neutral-100">
                    <div class="h-full rounded-full bg-gradient-to-r from-[#DC2626] to-[#F9C80E] transition-all" style="width: {{ $progressPercent }}%"></div>
                </div>
            </div>
        </div>

        <!-- Faltam Enviar -->
        <button
            type="button"
            wire:click="$set('queueTab', 'pending')"
            class="group rounded-2xl border p-5 text-left shadow-2xs transition {{ $queueTab === 'pending' ? 'border-[#DC2626] bg-gradient-to-br from-amber-50/70 via-white to-red-50/40 ring-1 ring-[#DC2626]/20' : 'border-neutral-200 bg-white hover:border-amber-300' }}"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-[#DC2626]">Faltam enviar</p>
                    <p class="mt-2 text-3xl font-extrabold tracking-tight tabular-nums text-[#171717]">{{ $notSentCount }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-100 text-[#DC2626] transition group-hover:scale-105">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </span>
            </div>
            <p class="mt-4 text-xs font-medium text-neutral-600">
                {{ $notSentCount > 0 ? 'Contatos aguardando envio ou confirmação.' : 'Tudo enviado por aqui!' }}
            </p>
        </button>

        <!-- Já Enviados -->
        <button
            type="button"
            wire:click="$set('queueTab', 'sent')"
            class="group rounded-2xl border p-5 text-left shadow-2xs transition {{ $queueTab === 'sent' ? 'border-emerald-600 bg-gradient-to-br from-emerald-50/70 via-white to-white ring-1 ring-emerald-600/20' : 'border-neutral-200 bg-white hover:border-emerald-300' }}"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Já enviados</p>
                    <p class="mt-2 text-3xl font-extrabold tracking-tight tabular-nums text-[#171717]">{{ $sentCount }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 transition group-hover:scale-105">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                </span>
            </div>
            <p class="mt-4 text-xs font-medium text-neutral-600">
                Clique para acompanhar confirmações e entradas nos grupos.
            </p>
        </button>
    </section>

    <!-- Fluxo Principal: Subir (5 cols) + Fila de Envio (7 cols) -->
    <div class="grid gap-6 lg:grid-cols-12">
        <!-- Coluna Esquerda: Subir Contatos por Imagem ou Texto -->
        <section
            x-data="{
                ocrLoading: false,
                ocrProgress: 0,
                ocrStatus: '',
                async readImageFile(file) {
                    if (!file || typeof window.extractTextFromImage !== 'function') return;
                    this.ocrLoading = true;
                    this.ocrProgress = 0;
                    this.ocrStatus = 'Preparando leitura da imagem...';
                    try {
                        const text = await window.extractTextFromImage(file, (pct) => {
                            this.ocrProgress = pct;
                            this.ocrStatus = 'Lendo números da imagem... ' + pct + '%';
                        });
                        await $wire.applyOcrText(text);
                        this.ocrStatus = '';
                    } catch (err) {
                        this.ocrStatus = 'Erro ao ler a imagem. Tente outro print ou cole o texto.';
                    } finally {
                        this.ocrLoading = false;
                    }
                }
            }"
            @paste="
                const items = $event.clipboardData?.items || [];
                for (const item of items) {
                    if (item.type && item.type.startsWith('image/')) {
                        $event.preventDefault();
                        readImageFile(item.getAsFile());
                        break;
                    }
                }
            "
            class="flex flex-col space-y-4 rounded-2xl border border-neutral-200/90 bg-white p-5 shadow-xs lg:col-span-5"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-md bg-red-100 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-[#DC2626]">Passo 1</span>
                        <h2 class="text-base font-bold text-[#171717]">Subir Contatos (Imagem ou Texto)</h2>
                    </div>
                    <p class="mt-1 text-xs text-neutral-600">Extraia números de um print ou cole a lista abaixo para salvar direto.</p>
                </div>
            </div>

            <!-- Abas de Imagem: 1) Ler números (Print)  2) Imagem + texto (Envio) -->
            <div class="rounded-xl border border-neutral-200 bg-[#F5F6F8] p-3 space-y-3">
                <div class="grid grid-cols-2 gap-1 rounded-xl bg-neutral-200/70 p-1">
                    <button
                        type="button"
                        wire:click="$set('imageTab', 'ocr')"
                        class="rounded-lg px-2.5 py-2 text-xs font-bold transition {{ $imageTab === 'ocr' ? 'bg-[#171717] text-white shadow-2xs' : 'text-neutral-700 hover:text-[#171717]' }}"
                    >
                        Ler números (Print)
                    </button>
                    <button
                        type="button"
                        wire:click="$set('imageTab', 'media')"
                        class="flex items-center justify-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-bold transition {{ $imageTab === 'media' ? 'bg-[#171717] text-white shadow-2xs' : 'text-neutral-700 hover:text-[#171717]' }}"
                    >
                        <span>Imagem + texto</span>
                        @if ($activeMediaUrl)
                            <span class="rounded-full bg-[#F9C80E] px-1.5 py-0.5 text-[10px] font-extrabold text-[#171717]">Ativa</span>
                        @endif
                    </button>
                </div>

                @if ($imageTab === 'ocr')
                    <div class="rounded-xl border-2 border-dashed border-neutral-300 bg-white p-5 text-center transition hover:border-[#DC2626]/50 hover:bg-amber-50/20">
                        <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-[#171717]">
                            <svg class="size-5 text-[#DC2626]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                        </div>
                        <p class="text-xs font-bold text-[#171717]">Subir por Imagem (Print / Lista)</p>
                        <p class="mt-0.5 text-[11px] text-neutral-500">Escolha um arquivo PNG/JPG ou cole um print aqui (Ctrl+V / Cmd+V).</p>

                        <label class="mt-3.5 inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#171717] px-4 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-neutral-800">
                            <svg class="size-3.5 text-[#F9C80E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
                            <span>Selecionar imagem para ler números</span>
                            <input
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="
                                    const f = $event.target.files?.[0];
                                    if (f) await readImageFile(f);
                                    $event.target.value = '';
                                "
                            />
                        </label>

                        <div x-show="ocrLoading" class="mt-3 space-y-1.5">
                            <div class="h-2 w-full overflow-hidden rounded-full bg-neutral-200">
                                <div class="h-full bg-gradient-to-r from-[#DC2626] to-[#F9C80E] transition-all" :style="'width:' + ocrProgress + '%'"></div>
                            </div>
                            <p x-text="ocrStatus" class="text-xs font-semibold text-[#171717]"></p>
                        </div>

                        <p x-show="!ocrLoading && ocrStatus" x-text="ocrStatus" class="mt-2 text-xs font-semibold text-red-700"></p>
                    </div>
                @else
                    <form wire:submit="saveQuickTemplateImage" class="space-y-3 rounded-xl border-2 border-dashed border-neutral-300 bg-white p-4 text-left">
                        <div>
                            <p class="text-xs font-bold text-[#171717]">Enviar imagem junto com a mensagem</p>
                            <p class="mt-0.5 text-[11px] text-neutral-600">Anexe um banner/imagem para acompanhar o texto padrão nos envios.</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#171717] px-3.5 py-2 text-xs font-bold text-white transition hover:bg-neutral-800">
                                <span>{{ $activeMediaUrl ? 'Trocar imagem do envio' : 'Selecionar imagem do envio' }}</span>
                                <input type="file" wire:model="quickTemplateImage" accept="image/*" class="hidden" />
                            </label>

                            @if ($quickTemplateImage && method_exists($quickTemplateImage, 'temporaryUrl'))
                                <button type="submit" class="rounded-xl bg-[#F9C80E] px-3.5 py-2 text-xs font-bold text-[#171717] hover:bg-amber-400">
                                    Salvar imagem no envio
                                </button>
                            @endif

                            @if ($activeMediaUrl)
                                <button
                                    type="button"
                                    wire:click="removeQuickTemplateImage"
                                    class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100"
                                >
                                    Remover
                                </button>
                            @endif
                        </div>

                        <div wire:loading wire:target="quickTemplateImage" class="text-xs font-semibold text-neutral-700">
                            Carregando imagem...
                        </div>

                        @error('quickTemplateImage')
                            <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror

                        @if ($quickTemplateImage && method_exists($quickTemplateImage, 'temporaryUrl'))
                            <div class="flex items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 p-2.5">
                                <img src="{{ $quickTemplateImage->temporaryUrl() }}" alt="Prévia" class="h-14 w-14 rounded-lg object-cover" />
                                <div class="text-xs text-[#171717]">
                                    <p class="font-bold">Pronta para salvar</p>
                                    <p class="text-[11px] text-neutral-600">Clique em "Salvar imagem no envio" acima.</p>
                                </div>
                            </div>
                        @elseif ($activeMediaUrl)
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-2.5">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $activeMediaUrl }}?v={{ $activeTemplate?->updated_at?->timestamp }}" alt="Imagem anexa" class="h-14 w-14 rounded-lg object-cover" />
                                    <div class="text-xs">
                                        <p class="font-bold text-emerald-900">Imagem ativa com o texto</p>
                                        <p class="text-[11px] text-emerald-800">Ao abrir o WhatsApp, ela é copiada automaticamente (use Cmd+V / Ctrl+V).</p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    data-img-url="{{ $activeMediaUrl }}"
                                    onclick="window.copyImageUrlToClipboard?.(this.dataset.imgUrl).then((ok) => { this.textContent = ok ? '✓ Copiada' : 'Erro'; setTimeout(() => this.textContent = 'Copiar', 1500); })"
                                    class="shrink-0 rounded-lg border border-emerald-300 bg-white px-2.5 py-1.5 text-[11px] font-bold text-emerald-900 hover:bg-emerald-100"
                                >
                                    Copiar
                                </button>
                            </div>
                        @endif
                    </form>
                @endif
            </div>

            <!-- Campo de Texto (preenchido pelo OCR ou colado) -->
            <form wire:submit="saveQuickContacts" class="flex flex-1 flex-col justify-between space-y-3.5">
                <div class="space-y-3">
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <label class="text-xs font-bold text-neutral-700">Números encontrados ou texto colado (1 por linha)</label>
                            @if ($lineCount > 0)
                                <span class="rounded-full bg-[#F9C80E]/30 px-2 py-0.5 text-[10px] font-bold text-[#171717]">{{ $lineCount }} linha(s)</span>
                            @endif
                        </div>
                        <textarea
                            wire:model.live.debounce.300ms="quickText"
                            rows="5"
                            placeholder="+55 11 99123-4567&#10;+55 21 98123-4567&#10;Maria Silva;+55 31 99876-5432"
                            class="w-full rounded-xl border border-neutral-300 bg-[#F5F6F8]/60 p-3 font-mono text-xs text-[#171717] transition focus:border-[#DC2626] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#F9C80E]/40"
                        ></textarea>
                        @error('quickText') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold text-neutral-700">Grupo de destino</label>
                            <select wire:model="quickGroupId" class="mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-xs text-[#171717] focus:border-[#DC2626] focus:outline-none">
                                <option value="">Padrão / Selecionar depois</option>
                                @foreach ($activeGroups as $g)
                                    <option value="{{ $g->id }}">{{ $g->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-neutral-700">DDI padrão</label>
                            <select wire:model="quickDefaultCountry" class="mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-xs text-[#171717] focus:border-[#DC2626] focus:outline-none">
                                <option value="55">+55 Brasil</option>
                                <option value="">Usar DDI do número</option>
                            </select>
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-neutral-200 bg-[#F5F6F8] px-3.5 py-2.5 text-xs font-medium text-[#171717] transition hover:border-amber-300 hover:bg-amber-50/40">
                        <input type="checkbox" wire:model.live="quickAuthorizeContacts" class="h-4 w-4 rounded border-neutral-400 accent-[#DC2626]" />
                        <span>Contatos já autorizaram receber convite (liberar envio imediato)</span>
                    </label>

                    @if ($quickAuthorizeContacts)
                        <div>
                            <label class="block text-xs font-semibold text-neutral-700">Origem / comprovante da autorização</label>
                            <input type="text" wire:model="quickConsentRef" class="mt-1 w-full rounded-xl border border-neutral-300 px-3 py-2 text-xs focus:border-[#DC2626] focus:outline-none" />
                        </div>
                    @endif
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="saveQuickContacts"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#F9C80E] px-4 py-3 text-xs font-extrabold uppercase tracking-wide text-[#171717] shadow-2xs transition hover:bg-amber-400 disabled:opacity-60"
                >
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <span wire:loading.remove wire:target="saveQuickContacts">Salvar na lista</span>
                    <span wire:loading wire:target="saveQuickContacts">Salvando contatos...</span>
                </button>
            </form>
        </section>

        <!-- Coluna Direita: Fila de Envio (Não enviados vs Já enviados) -->
        <section class="flex flex-col overflow-hidden rounded-2xl border border-neutral-200/90 bg-white shadow-xs lg:col-span-7">
            <div class="flex flex-col gap-3 border-b border-neutral-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-[#171717]">Passo 2</span>
                    <div class="inline-flex rounded-xl bg-neutral-100 p-1">
                        <button
                            type="button"
                            wire:click="$set('queueTab', 'pending')"
                            class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $queueTab === 'pending' ? 'bg-[#DC2626] text-white shadow-2xs' : 'text-neutral-700 hover:text-[#171717]' }}"
                        >
                            Faltam enviar ({{ $notSentCount }})
                        </button>
                        <button
                            type="button"
                            wire:click="$set('queueTab', 'sent')"
                            class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $queueTab === 'sent' ? 'bg-emerald-700 text-white shadow-2xs' : 'text-neutral-700 hover:text-[#171717]' }}"
                        >
                            Já enviados ({{ $sentCount }})
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select wire:model.live="selectedTemplateId" class="rounded-xl border border-neutral-300 bg-[#F5F6F8] px-3 py-1.5 text-xs font-semibold text-[#171717] focus:border-[#DC2626] focus:outline-none">
                        <option value="">Mensagem padrão ativa</option>
                        @foreach ($templates as $tpl)
                            <option value="{{ $tpl->id }}">{{ $tpl->display_name }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('templates.index') }}" wire:navigate class="rounded-lg border border-neutral-200 px-2.5 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-neutral-100 hover:text-[#171717]">
                        Editar
                    </a>
                </div>
            </div>

            @if ($activeMediaUrl)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-emerald-200 bg-emerald-50/80 px-4 py-2.5 text-xs">
                    <div class="flex items-center gap-2.5">
                        <img src="{{ $activeMediaUrl }}?v={{ $activeTemplate?->updated_at?->timestamp }}" alt="Imagem anexa" class="h-8 w-8 rounded-lg border border-emerald-200 object-cover" />
                        <span class="font-medium text-emerald-950">
                            <strong>Imagem anexa ao envio:</strong> ao abrir o WhatsApp, ela é copiada automaticamente — cole (Cmd+V / Ctrl+V) junto do texto.
                        </span>
                    </div>
                    <button
                        type="button"
                        data-img-url="{{ $activeMediaUrl }}"
                        onclick="window.copyImageUrlToClipboard?.(this.dataset.imgUrl).then((ok) => { this.textContent = ok ? '✓ Imagem copiada' : 'Erro'; setTimeout(() => this.textContent = 'Copiar imagem', 1500); })"
                        class="whitespace-nowrap rounded-lg border border-emerald-300 bg-white px-2.5 py-1 text-[11px] font-bold text-emerald-900 hover:bg-emerald-100"
                    >
                        Copiar imagem
                    </button>
                </div>
            @endif

            @if ($queueTab === 'pending' && $notSentCount > 0)
                @php
                    $selectedCount = count($selectedContactIds);
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-200 bg-[#F5F6F8]/80 px-4 py-2.5">
                    <div class="flex items-center gap-2 text-xs text-neutral-600">
                        @if ($selectedCount > 0)
                            <span class="inline-flex items-center rounded-full bg-[#DC2626] px-2 py-0.5 text-[11px] font-bold text-white">{{ $selectedCount }}/3</span>
                            <span>selecionado(s) para envio em massa</span>
                            <button type="button" wire:click="$set('selectedContactIds', [])" class="font-semibold text-[#DC2626] underline hover:text-red-800">Limpar</button>
                        @else
                            <span>Marque até <strong>3 contatos</strong> ou envie os próximos 3 automaticamente</span>
                        @endif
                    </div>

                    <button
                        type="button"
                        wire:click="bulkInviteSelected"
                        wire:loading.attr="disabled"
                        wire:target="bulkInviteSelected"
                        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-xl bg-[#171717] px-3.5 py-1.5 text-xs font-bold text-white shadow-2xs transition hover:bg-[#DC2626] disabled:opacity-50"
                    >
                        <svg class="size-3.5 text-[#F9C80E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                        <span wire:loading.remove wire:target="bulkInviteSelected">
                            {{ $selectedCount > 0 ? "Enviar selecionados ({$selectedCount}/3)" : 'Enviar em massa (máx. 3)' }}
                        </span>
                        <span wire:loading wire:target="bulkInviteSelected">Abrindo lote...</span>
                    </button>
                </div>
            @endif

            @if ($batchWaLinks !== [])
                <div class="space-y-3 border-b border-amber-300 bg-amber-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-xs font-bold text-[#171717]">Lote aberto ({{ count($batchWaLinks) }}/3) — Links WhatsApp</p>
                            <p class="text-[11px] text-neutral-700">Envie no WhatsApp usando os links abaixo e clique em confirmar tudo quando terminar.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="confirmBatchManualSends"
                                class="whitespace-nowrap rounded-xl bg-[#171717] px-3 py-1.5 text-xs font-bold text-white hover:bg-neutral-800"
                            >
                                Confirmar envios ({{ count($batchWaLinks) }})
                            </button>
                            <button type="button" wire:click="dismissBatchPanel" class="text-xs font-semibold text-neutral-600 underline">Fechar</button>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach ($batchWaLinks as $item)
                            <div class="flex items-center justify-between gap-1.5 rounded-xl border border-amber-300 bg-white px-2.5 py-2 text-xs shadow-2xs">
                                <span class="truncate font-mono font-semibold text-[#171717]">{{ $item['phone'] }}</span>
                                <div class="flex shrink-0 items-center gap-1">
                                    @if (! empty($item['message']))
                                        <button
                                            type="button"
                                            data-msg="{{ $item['message'] }}"
                                            onclick="navigator.clipboard?.writeText(this.dataset.msg); this.textContent='✓'; setTimeout(() => this.textContent='Copiar', 1500);"
                                            class="rounded-md border border-neutral-300 bg-[#F5F6F8] px-1.5 py-0.5 text-[10px] font-semibold text-[#171717] hover:bg-neutral-200"
                                            title="Copiar texto com emojis"
                                        >
                                            Copiar
                                        </button>
                                    @endif
                                    <a
                                        href="{{ $item['url'] }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-md bg-[#F9C80E] px-1.5 py-0.5 text-[10px] font-bold text-[#171717] hover:bg-amber-400"
                                    >
                                        Abrir
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($queueContacts->isEmpty())
                <div class="flex flex-1 flex-col items-center justify-center p-10 text-center">
                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-[#DC2626]">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    </div>
                    <p class="text-sm font-semibold text-[#171717]">
                        @if ($queueTab === 'sent')
                            Nenhum envio confirmado nesta lista ainda.
                        @else
                            Nenhum contato aguardando envio.
                        @endif
                    </p>
                    <p class="mt-1 max-w-sm text-xs text-neutral-500">
                        @if ($queueTab === 'sent')
                            Assim que confirmar seus envios no WhatsApp, eles aparecem aqui para você registrar quem entrou no grupo.
                        @else
                            Suba uma imagem ou cole os números na coluna ao lado para começar sua fila de envios.
                        @endif
                    </p>
                </div>
            @else
                <div class="divide-y divide-neutral-100">
                    @foreach ($queueContacts as $contact)
                        @php
                            $lastInvite = $contact->invitations->first();
                            $isAuthorized = $contact->consent_status === 'autorizado' && $contact->suppressionEntry === null;
                            $hasActiveInvite = $lastInvite !== null && ! in_array($lastInvite->status, ['failed', 'cancelled'], true);
                            $canBulkSelect = $queueTab === 'pending' && $isAuthorized && ! $hasActiveInvite;
                            $isSelected = in_array((string) $contact->id, array_map('strval', $selectedContactIds), true);
                            $disableCheckbox = ! $isSelected && count($selectedContactIds) >= 3;
                            $isPhoneOnlyName = str_starts_with($contact->name, 'Contato ') && str_contains($contact->name, $contact->phone_e164);
                            $displayTitle = $isPhoneOnlyName ? $contact->phone_e164 : $contact->name;
                            $avatarLabel = $isPhoneOnlyName ? substr(preg_replace('/\D/', '', $contact->phone_e164) ?: '00', -2) : mb_strtoupper(mb_substr($contact->name, 0, 1));
                        @endphp
                        <div
                            wire:key="dash-contact-{{ $contact->id }}"
                            class="flex flex-col gap-3 px-4 py-3.5 transition hover:bg-amber-50/25 sm:flex-row sm:items-center sm:justify-between {{ $isSelected ? 'bg-amber-50/60' : '' }}"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                @if ($canBulkSelect)
                                    <input
                                        type="checkbox"
                                        value="{{ $contact->id }}"
                                        wire:model.live="selectedContactIds"
                                        @disabled($disableCheckbox)
                                        title="{{ $disableCheckbox ? 'Máximo de 3 contatos por lote' : 'Selecionar para envio em massa' }}"
                                        class="h-4 w-4 shrink-0 rounded border-neutral-400 accent-[#DC2626] disabled:opacity-40"
                                    />
                                @endif

                                <span class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-extrabold sm:inline-flex {{ $queueTab === 'sent' ? 'bg-emerald-100 text-emerald-800' : ($lastInvite?->status === 'assisted_opened' ? 'bg-amber-100 text-amber-900' : 'bg-red-100 text-[#DC2626]') }}">
                                    {{ $avatarLabel }}
                                </span>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="truncate text-sm font-bold text-[#171717] {{ $isPhoneOnlyName ? 'font-mono' : '' }}">{{ $displayTitle }}</span>
                                        @if (! $isPhoneOnlyName)
                                            <span class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-neutral-600">{{ $contact->phone_e164 }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-neutral-500">
                                        <span class="inline-flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-neutral-400"></span>
                                            Grupo: <strong class="text-neutral-700">{{ $contact->defaultGroup?->name ?? 'Padrão ativo' }}</strong>
                                        </span>
                                        @if (! $isAuthorized)
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-neutral-200 px-2 py-0.5 font-semibold text-neutral-700">Aguardando autorização</span>
                                        @elseif ($lastInvite?->status === 'assisted_opened')
                                            <span class="inline-flex whitespace-nowrap rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 font-bold text-amber-900">WhatsApp aberto — confirme o envio</span>
                                        @elseif ($queueTab === 'sent')
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800">{{ $contact->stageLabel() }}</span>
                                        @else
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800">Pronto para enviar</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                @if ($queueTab === 'pending')
                                    @if (! $isAuthorized)
                                        <button
                                            type="button"
                                            wire:click="authorizeContact({{ $contact->id }})"
                                            class="whitespace-nowrap rounded-xl border border-emerald-400 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-900 transition hover:bg-emerald-100"
                                        >
                                            Autorizar envio
                                        </button>
                                    @elseif ($lastInvite?->status === 'assisted_opened')
                                        @if ($lastInvite->wa_me_url)
                                            <a
                                                href="{{ $lastInvite->wa_me_url }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="whitespace-nowrap rounded-xl border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-[#171717] transition hover:bg-neutral-50"
                                            >
                                                Reabrir
                                            </a>
                                        @endif
                                        <button
                                            type="button"
                                            wire:click="confirmManualSend({{ $lastInvite->id }})"
                                            class="whitespace-nowrap rounded-xl bg-[#DC2626] px-3.5 py-1.5 text-xs font-bold text-white shadow-2xs transition hover:bg-red-700"
                                        >
                                            Confirmar envio
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="inviteNext({{ $contact->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="inviteNext({{ $contact->id }})"
                                            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-xl bg-[#F9C80E] px-3.5 py-1.5 text-xs font-extrabold text-[#171717] shadow-2xs transition hover:bg-amber-400 disabled:opacity-50"
                                        >
                                            <span wire:loading.remove wire:target="inviteNext({{ $contact->id }})">{{ $primaryButtonLabel }}</span>
                                            <span wire:loading wire:target="inviteNext({{ $contact->id }})">Abrindo...</span>
                                        </button>
                                    @endif
                                @else
                                    @if ($contact->pipeline_stage !== 'entrada_confirmada')
                                        <button
                                            type="button"
                                            wire:click="confirmEntry({{ $contact->id }})"
                                            class="whitespace-nowrap rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-900 transition hover:bg-emerald-100"
                                        >
                                            Entrou no grupo
                                        </button>
                                    @else
                                        <span class="whitespace-nowrap rounded-xl bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-900">
                                            Concluído
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-auto flex items-center justify-between border-t border-neutral-100 bg-[#F5F6F8]/60 px-4 py-2.5 text-xs text-neutral-600">
                    <span>Exibindo os <strong>{{ $queueContacts->count() }}</strong> contatos mais recentes desta aba</span>
                    <a href="{{ route('contacts.index') }}" wire:navigate class="font-bold text-[#DC2626] hover:underline">
                        Abrir lista completa →
                    </a>
                </div>
            @endif
        </section>
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('open-wa-me', async (event) => {
                let copiedImage = false;
                if (event.imageUrl && typeof window.copyImageUrlToClipboard === 'function') {
                    copiedImage = await window.copyImageUrlToClipboard(event.imageUrl);
                }
                if (!copiedImage && event.message && navigator.clipboard?.writeText) {
                    navigator.clipboard.writeText(event.message).catch(() => {});
                }
                if (event.url) {
                    window.open(event.url, '_blank', 'noopener,noreferrer');
                }
            });

            Livewire.on('open-wa-me-batch', async (event) => {
                if (event.imageUrl && typeof window.copyImageUrlToClipboard === 'function') {
                    await window.copyImageUrlToClipboard(event.imageUrl);
                }
                const urls = Array.isArray(event.urls) ? event.urls.slice(0, 3) : [];
                urls.forEach((url, index) => {
                    setTimeout(() => {
                        window.open(url, '_blank', 'noopener,noreferrer');
                    }, index * 250);
                });
            });
        });
    </script>
</div>
