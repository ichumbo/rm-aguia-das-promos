<div class="mx-auto max-w-[1440px] space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-[#171717]">Modelos de Mensagem</h1>
            <p class="text-sm text-neutral-600">Templates para iniciar conversas na Meta (categoria Marketing) e mensagens padrão do modo assistido.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="syncMeta"
                wire:loading.attr="disabled"
                class="rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-xs font-semibold text-[#171717] hover:bg-neutral-50 disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="syncMeta">Sincronizar status na Meta</span>
                <span wire:loading wire:target="syncMeta">Sincronizando...</span>
            </button>
            <button type="button" wire:click="openCreate" class="rounded-lg bg-[#F9C80E] px-4 py-2 text-xs font-bold text-[#171717] hover:bg-amber-400">
                + Novo modelo
            </button>
        </div>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4 text-xs leading-relaxed text-neutral-700">
        <strong>Regras de janela e aprovação da Meta:</strong> Para iniciar conversa ou fora da janela de 24 horas desde a última resposta do contato, o envio oficial exige template <strong>APPROVED</strong>. Modelos com status <strong>PAUSED</strong>, <strong>REJECTED</strong> ou <strong>DISABLED</strong> são automaticamente bloqueados. Convites promocionais devem ser classificados como <strong>MARKETING</strong>.
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

    @php
        $quickPreview = str_replace(
            ['{{nome}}', '{{categoria}}', '{{grupo}}', '{{link_grupo}}'],
            ['Maria Silva', 'Eletrônicos & Tech', 'Águia Tech Ofertas', 'https://chat.whatsapp.com/ExemploLink'],
            $quickBodyText
        );
    @endphp
    <section class="rounded-xl border border-neutral-200 bg-white p-5">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-bold text-[#171717]">Configurar Mensagem Automática Padrão</h2>
                <p class="text-xs text-neutral-600">Este é o texto pré-preenchido automaticamente quando você clica em enviar convite na lista de contatos.</p>
            </div>
            <span class="inline-flex w-fit items-center rounded-md bg-[#F9C80E] px-2.5 py-1 text-[11px] font-bold text-[#171717]">Texto padrão dos envios</span>
        </div>

        <form wire:submit="saveQuickDefaultMessage" class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="space-y-3">
                <div>
                    <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                        <label class="text-xs font-semibold text-neutral-700">Texto da mensagem automática</label>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (['{{nome}}', '{{categoria}}', '{{grupo}}', '{{link_grupo}}'] as $varTag)
                                <button
                                    type="button"
                                    wire:click="appendQuickPlaceholder('{{ $varTag }}')"
                                    class="rounded border border-neutral-300 bg-[#F5F6F8] px-2 py-0.5 font-mono text-[11px] font-semibold text-[#171717] hover:bg-neutral-200"
                                >
                                    + {{ $varTag }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <textarea
                        wire:model.live.debounce.200ms="quickBodyText"
                        rows="5"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-[#F9C80E] focus:outline-none"
                        placeholder="Ex.: Olá, @{{nome}}! Seu convite para @{{grupo}}: @{{link_grupo}}"
                    ></textarea>
                    @error('quickBodyText') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[11px] text-neutral-500">Use as variáveis acima para preencher nome e link do grupo automaticamente.</span>
                    <button type="submit" class="rounded-lg bg-[#171717] px-4 py-2 text-xs font-bold text-white hover:bg-neutral-800">
                        Salvar mensagem automática
                    </button>
                </div>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-[#F5F6F8] p-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">Prévia no WhatsApp (exemplo)</p>
                <div class="mt-2 rounded-lg border border-neutral-200 bg-white p-3 text-sm leading-relaxed text-[#171717]">
                    {{ $quickPreview !== '' ? $quickPreview : 'Digite seu texto ao lado para visualizar como o contato vai receber.' }}
                </div>
            </div>
        </form>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($templates as $template)
            @php
                $previewText = $template->render([
                    'nome' => 'Maria Silva',
                    'categoria' => 'Eletrônicos & Tech',
                    'grupo' => 'Águia Tech Ofertas',
                    'link_grupo' => 'https://chat.whatsapp.com/ExemploLink',
                ]);
            @endphp
            <article class="rounded-xl border border-neutral-200 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-[#171717]">{{ $template->display_name }}</h2>
                            @if ($template->is_default)
                                <span class="rounded bg-[#F9C80E] px-2 py-0.5 text-[11px] font-bold text-[#171717]">Padrão</span>
                            @endif
                        </div>
                        <p class="mt-0.5 font-mono text-xs text-neutral-500">{{ $template->name }} · {{ $template->language }} · {{ $template->category }}</p>
                    </div>
                    <div>
                        @if ($template->status === 'APPROVED')
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">APPROVED</span>
                        @elseif (in_array($template->status, ['REJECTED', 'PAUSED', 'DISABLED'], true))
                            <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">{{ $template->status }} (Bloqueado)</span>
                        @else
                            <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $template->status }}</span>
                        @endif
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3.5 text-xs text-[#171717]">
                    <p class="font-semibold text-neutral-500">Prévia com variáveis preenchidas:</p>
                    <p class="mt-1 whitespace-pre-line leading-relaxed">{{ $previewText }}</p>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-neutral-500">
                    <span>Variáveis: {{ implode(', ', $template->extractPlaceholders()) ?: 'Nenhuma' }}</span>
                    <div class="flex gap-2">
                        @if (! $template->is_default && $template->status === 'APPROVED')
                            <button type="button" wire:click="setDefault({{ $template->id }})" class="rounded border border-neutral-300 bg-white px-2.5 py-1 font-semibold text-[#171717] hover:bg-neutral-50">
                                Definir padrão
                            </button>
                        @endif
                        <button type="button" wire:click="openEdit({{ $template->id }})" class="rounded border border-neutral-300 bg-white px-2.5 py-1 font-semibold text-[#171717] hover:bg-neutral-50">
                            Editar
                        </button>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500">
                Nenhum modelo cadastrado.
            </div>
        @endforelse
    </div>

    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-xl rounded-xl border border-neutral-200 bg-white p-6">
                <h2 class="text-lg font-bold text-[#171717]">{{ $editingId ? 'Editar modelo' : 'Novo modelo de mensagem' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold text-[#171717]">Nome técnico (Meta)</label>
                            <input type="text" wire:model="name" required placeholder="convite_grupo_aguia" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 font-mono text-xs" />
                            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#171717]">Nome amigável</label>
                            <input type="text" wire:model="display_name" required placeholder="Convite Padrão Águia" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                            @error('display_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="block text-xs font-semibold text-[#171717]">Categoria Meta</label>
                            <select wire:model="category" class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm">
                                <option value="MARKETING">MARKETING</option>
                                <option value="UTILITY">UTILITY</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#171717]">Idioma</label>
                            <input type="text" wire:model="language" required class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#171717]">Status na Meta</label>
                            <select wire:model="status" class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm">
                                <option value="APPROVED">APPROVED</option>
                                <option value="PENDING">PENDING</option>
                                <option value="PAUSED">PAUSED</option>
                                <option value="REJECTED">REJECTED</option>
                                <option value="DISABLED">DISABLED</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#171717]">Texto do modelo</label>
                        <textarea wire:model="body_text" rows="4" required class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"></textarea>
                        <p class="mt-1 text-[11px] text-neutral-500">Variáveis suportadas: <code>@{{nome}}</code>, <code>@{{categoria}}</code>, <code>@{{grupo}}</code>, <code>@{{link_grupo}}</code>.</p>
                        @error('body_text') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="is_default" class="h-4 w-4 rounded border-neutral-400" />
                        <span>Definir como modelo padrão para convites</span>
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showFormModal', false)" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold text-[#171717]">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-[#F9C80E] px-4 py-2 text-xs font-bold text-[#171717]">Salvar modelo</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

