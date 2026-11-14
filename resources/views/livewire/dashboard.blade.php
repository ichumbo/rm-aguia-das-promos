<div class="mx-auto max-w-[1440px] space-y-5">
    <!-- Header limpo e direto -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-[#171717]">Central de Contatos &amp; Envios</h1>
            <p class="text-sm text-neutral-600">Suba contatos por imagem ou texto, salve na lista e envie a mensagem programada.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('templates.index') }}" wire:navigate class="rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-xs font-semibold text-[#171717] hover:bg-neutral-50">
                Configurar Mensagem Automática
            </a>
            <a href="{{ route('contacts.index') }}" wire:navigate class="rounded-lg bg-[#171717] px-3.5 py-2 text-xs font-semibold text-white hover:bg-neutral-800">
                Ver Lista Completa
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

    <!-- 3 Resumos simples -->
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Total na lista</p>
            <p class="mt-1 text-2xl font-bold text-[#171717]">{{ $totalContacts }}</p>
        </div>

        <button
            type="button"
            wire:click="$set('queueTab', 'pending')"
            class="rounded-xl border p-4 text-left transition {{ $queueTab === 'pending' ? 'border-[#171717] bg-[#F9C80E]/15' : 'border-neutral-200 bg-white hover:border-neutral-300' }}"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-600">Faltam enviar</p>
            <p class="mt-1 text-2xl font-bold text-[#171717]">{{ $notSentCount }}</p>
        </button>

        <button
            type="button"
            wire:click="$set('queueTab', 'sent')"
            class="rounded-xl border p-4 text-left transition {{ $queueTab === 'sent' ? 'border-[#171717] bg-emerald-50' : 'border-neutral-200 bg-white hover:border-neutral-300' }}"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-600">Já enviados</p>
            <p class="mt-1 text-2xl font-bold text-[#171717]">{{ $sentCount }}</p>
        </button>
    </section>

    <!-- Fluxo Principal: Subir (Imagem ou Texto) + Enviar -->
    <div class="grid gap-5 lg:grid-cols-12">
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
            class="space-y-4 rounded-xl border border-neutral-200 bg-white p-5 lg:col-span-5"
        >
            <div>
                <h2 class="text-base font-bold text-[#171717]">Subir Contatos (Imagem ou Texto)</h2>
                <p class="text-xs text-neutral-600">Envie um print/foto com números ou cole a lista abaixo para salvar direto.</p>
            </div>

            <!-- Upload de Imagem (OCR) -->
            <div class="rounded-xl border-2 border-dashed border-neutral-300 bg-[#F5F6F8] p-4 text-center">
                <p class="text-xs font-bold text-[#171717]">Subir por Imagem (Print / Lista)</p>
                <p class="mt-0.5 text-[11px] text-neutral-600">Escolha um arquivo PNG/JPG ou cole um print aqui (Ctrl+V / Cmd+V).</p>

                <label class="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#171717] px-4 py-2 text-xs font-bold text-white hover:bg-neutral-800">
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
                        <div class="h-full bg-[#F9C80E] transition-all" :style="'width:' + ocrProgress + '%'"></div>
                    </div>
                    <p x-text="ocrStatus" class="text-xs font-semibold text-[#171717]"></p>
                </div>

                <p x-show="!ocrLoading && ocrStatus" x-text="ocrStatus" class="mt-2 text-xs font-semibold text-red-700"></p>
            </div>

            <!-- Campo de Texto (preenchido pelo OCR ou colado) -->
            <form wire:submit="saveQuickContacts" class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700">Números encontrados ou texto colado (1 por linha)</label>
                    <textarea
                        wire:model="quickText"
                        rows="6"
                        placeholder="+55 11 99123-4567&#10;+55 21 98123-4567&#10;Maria Silva;+55 31 99876-5432"
                        class="mt-1 w-full rounded-lg border border-neutral-300 p-3 font-mono text-xs focus:border-[#F9C80E] focus:outline-none"
                    ></textarea>
                    @error('quickText') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-700">Grupo de destino</label>
                        <select wire:model="quickGroupId" class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-2.5 py-2 text-xs">
                            <option value="">Padrão / Selecionar depois</option>
                            @foreach ($activeGroups as $g)
                                <option value="{{ $g->id }}">{{ $g->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-neutral-700">DDI padrão</label>
                        <select wire:model="quickDefaultCountry" class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-2.5 py-2 text-xs">
                            <option value="55">+55 Brasil</option>
                            <option value="">Usar DDI do número</option>
                        </select>
                    </div>
                </div>

                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-neutral-200 bg-[#F5F6F8] px-3 py-2 text-xs font-medium text-[#171717]">
                    <input type="checkbox" wire:model.live="quickAuthorizeContacts" class="h-4 w-4 rounded border-neutral-400" />
                    <span>Contatos já autorizaram receber convite (liberar envio imediato)</span>
                </label>

                @if ($quickAuthorizeContacts)
                    <div>
                        <label class="block text-xs font-semibold text-neutral-700">Origem / comprovante da autorização</label>
                        <input type="text" wire:model="quickConsentRef" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-1.5 text-xs" />
                    </div>
                @endif

                <button type="submit" class="w-full rounded-lg bg-[#F9C80E] px-4 py-2.5 text-xs font-bold text-[#171717] hover:bg-amber-400">
                    Salvar na lista
                </button>
            </form>
        </section>

        <!-- Coluna Direita: Fila de Envio (Não enviados vs Já enviados) -->
        <section class="flex flex-col rounded-xl border border-neutral-200 bg-white lg:col-span-7">
            <div class="flex flex-col gap-3 border-b border-neutral-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="$set('queueTab', 'pending')"
                        class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $queueTab === 'pending' ? 'bg-[#171717] text-white' : 'border border-neutral-300 bg-white text-neutral-700 hover:bg-[#F5F6F8]' }}"
                    >
                        Faltam enviar ({{ $notSentCount }})
                    </button>
                    <button
                        type="button"
                        wire:click="$set('queueTab', 'sent')"
                        class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $queueTab === 'sent' ? 'bg-emerald-700 text-white' : 'border border-neutral-300 bg-white text-neutral-700 hover:bg-[#F5F6F8]' }}"
                    >
                        Já enviados ({{ $sentCount }})
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <select wire:model.live="selectedTemplateId" class="rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-medium text-[#171717]">
                        <option value="">Mensagem padrão ativa</option>
                        @foreach ($templates as $tpl)
                            <option value="{{ $tpl->id }}">{{ $tpl->display_name }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('templates.index') }}" wire:navigate class="text-xs font-semibold text-neutral-600 underline hover:text-[#171717]">
                        Editar
                    </a>
                </div>
            </div>

            @if ($queueContacts->isEmpty())
                <div class="flex flex-1 flex-col items-center justify-center p-8 text-center text-sm text-neutral-500">
                    @if ($queueTab === 'sent')
                        Nenhum envio confirmado nesta lista ainda.
                    @else
                        Nenhum contato aguardando envio. Suba uma imagem ou cole números ao lado para começar.
                    @endif
                </div>
            @else
                <div class="divide-y divide-neutral-200">
                    @foreach ($queueContacts as $contact)
                        @php
                            $lastInvite = $contact->invitations->first();
                            $isAuthorized = $contact->consent_status === 'autorizado' && $contact->suppressionEntry === null;
                        @endphp
                        <div wire:key="dash-contact-{{ $contact->id }}" class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-sm font-bold text-[#171717]">{{ $contact->name }}</span>
                                    <span class="font-mono text-xs text-neutral-600">{{ $contact->phone_e164 }}</span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-neutral-500">
                                    <span>Grupo: <strong>{{ $contact->defaultGroup?->name ?? 'Padrão ativo' }}</strong></span>
                                    <span>·</span>
                                    @if (! $isAuthorized)
                                        <span class="rounded bg-neutral-200 px-1.5 py-0.5 font-semibold text-neutral-700">Aguardando autorização</span>
                                    @elseif ($lastInvite?->status === 'assisted_opened')
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-900">WhatsApp aberto — confirme o envio</span>
                                    @elseif ($queueTab === 'sent')
                                        <span class="rounded bg-emerald-100 px-1.5 py-0.5 font-semibold text-emerald-800">{{ $contact->stageLabel() }}</span>
                                    @else
                                        <span class="rounded bg-emerald-100 px-1.5 py-0.5 font-semibold text-emerald-800">Pronto para enviar</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                @if ($queueTab === 'pending')
                                    @if (! $isAuthorized)
                                        <button
                                            type="button"
                                            wire:click="authorizeContact({{ $contact->id }})"
                                            class="whitespace-nowrap rounded-lg border border-emerald-400 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-900 hover:bg-emerald-100"
                                        >
                                            Autorizar envio
                                        </button>
                                    @elseif ($lastInvite?->status === 'assisted_opened')
                                        @if ($lastInvite->wa_me_url)
                                            <a
                                                href="{{ $lastInvite->wa_me_url }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="whitespace-nowrap rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-[#171717] hover:bg-neutral-50"
                                            >
                                                Reabrir
                                            </a>
                                        @endif
                                        <button
                                            type="button"
                                            wire:click="confirmManualSend({{ $lastInvite->id }})"
                                            class="whitespace-nowrap rounded-lg bg-[#171717] px-3 py-1.5 text-xs font-bold text-white hover:bg-neutral-800"
                                        >
                                            Confirmar envio
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="inviteNext({{ $contact->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="inviteNext({{ $contact->id }})"
                                            class="whitespace-nowrap rounded-lg bg-[#F9C80E] px-3 py-1.5 text-xs font-bold text-[#171717] hover:bg-amber-400 disabled:opacity-50"
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
                                            class="whitespace-nowrap rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-900 hover:bg-emerald-100"
                                        >
                                            Entrou no grupo
                                        </button>
                                    @else
                                        <span class="whitespace-nowrap rounded-lg bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-900">
                                            Concluído
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

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
