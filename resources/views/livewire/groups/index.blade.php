<div class="mx-auto max-w-[1440px] space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-[#171717]">Grupos de Destino</h1>
            <p class="text-sm text-neutral-600">Cadastre os grupos oficiais e gerencie os links de convite fornecidos pelo administrador. A entrada é voluntária pelo link.</p>
        </div>
        <button type="button" wire:click="openCreate" class="rounded-lg bg-[#F9C80E] px-4 py-2 text-xs font-bold text-[#171717] hover:bg-amber-400">
            + Novo grupo
        </button>
    </div>

    @if ($flashMessage)
        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
            {{ $flashMessage }}
        </div>
    @endif

    <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 bg-white p-4 sm:flex-row sm:items-center">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Filtrar por nome ou categoria..."
            class="w-full rounded-lg border border-neutral-300 bg-[#F5F6F8] px-3.5 py-2 text-sm text-[#171717] sm:max-w-xs"
        />
        <select wire:model.live="statusFilter" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-[#171717]">
            <option value="">Todos os status</option>
            <option value="active">Ativos</option>
            <option value="inactive">Inativos / Revogados</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                <thead class="bg-[#F5F6F8] text-xs font-semibold uppercase text-neutral-600">
                    <tr>
                        <th class="px-5 py-3">Grupo</th>
                        <th class="px-5 py-3 whitespace-nowrap">Categoria</th>
                        <th class="px-5 py-3">Link &amp; Capacidade</th>
                        <th class="px-5 py-3 whitespace-nowrap">Status</th>
                        <th class="px-5 py-3 text-right whitespace-nowrap">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white">
                    @forelse ($groups as $group)
                        <tr class="hover:bg-[#F5F6F8]/60">
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-[#171717]">{{ $group->name }}</p>
                                @if ($group->description)
                                    <p class="text-xs text-neutral-500">{{ Str::limit($group->description, 65) }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-xs font-medium text-neutral-700">{{ $group->category }}</td>
                            <td class="px-5 py-3.5 text-xs">
                                <a href="{{ $group->invite_url }}" target="_blank" rel="noopener" class="font-mono text-neutral-700 underline hover:text-[#171717]">
                                    {{ Str::limit($group->invite_url, 40) }}
                                </a>
                                <p class="mt-0.5 text-[11px] text-neutral-500">{{ $group->capacity_note ?: 'Sem observação registrada' }}</p>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if ($group->revoked_at)
                                    <span class="inline-flex whitespace-nowrap rounded-md bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Link revogado</span>
                                @elseif ($group->is_active)
                                    <span class="inline-flex whitespace-nowrap rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Ativo</span>
                                @else
                                    <span class="inline-flex whitespace-nowrap rounded-md bg-neutral-200 px-2.5 py-1 text-xs font-semibold text-neutral-700">Inativo</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5 whitespace-nowrap">
                                    <button type="button" wire:click="openEdit({{ $group->id }})" class="whitespace-nowrap rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-[#171717] hover:bg-neutral-50">
                                        Editar
                                    </button>
                                    <button type="button" wire:click="openReplaceLink({{ $group->id }})" class="whitespace-nowrap rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-[#171717] hover:bg-amber-100">
                                        Substituir link
                                    </button>
                                    @if ($group->is_active && ! $group->revoked_at)
                                        <button type="button" wire:click="markLinkRevoked({{ $group->id }})" class="whitespace-nowrap rounded-lg border border-red-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                            Revogar
                                        </button>
                                    @else
                                        <button type="button" wire:click="toggleActive({{ $group->id }})" class="whitespace-nowrap rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-[#171717] hover:bg-neutral-50">
                                            Ativar
                                        </button>
                                    @endif
                                    <button
                                        type="button"
                                        wire:click="deleteGroup({{ $group->id }})"
                                        wire:confirm="Tem certeza que deseja excluir este grupo? Os contatos ligados a ele permanecerão salvos sem grupo padrão."
                                        class="whitespace-nowrap rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100"
                                    >
                                        Excluir
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-sm text-neutral-500">
                                Nenhum grupo encontrado. Cadastre o primeiro grupo oficial de ofertas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($groups->hasPages())
            <div class="border-t border-neutral-200 px-5 py-3">
                {{ $groups->links() }}
            </div>
        @endif
    </div>

    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-lg rounded-xl border border-neutral-200 bg-white p-6">
                <h2 class="text-lg font-bold text-[#171717]">{{ $editingId ? 'Editar grupo' : 'Novo grupo de destino' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#171717]">Nome do grupo</label>
                        <input type="text" wire:model="name" required class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#171717]">Categoria</label>
                        <input type="text" wire:model="category" required placeholder="Ex: Eletrônicos & Tech" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                        @error('category') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#171717]">Link oficial de convite (https://...)</label>
                        <input type="url" wire:model="invite_url" required placeholder="https://chat.whatsapp.com/..." class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 font-mono text-xs" />
                        @error('invite_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#171717]">Descrição</label>
                        <textarea wire:model="description" rows="2" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#171717]">Observação de capacidade (manual)</label>
                        <input type="text" wire:model="capacity_note" placeholder="Ex: Conferido manualmente em 14/11 — com vagas" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded border-neutral-400" />
                        <span>Grupo ativo para receber novos convites</span>
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showFormModal', false)" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold text-[#171717]">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-[#F9C80E] px-4 py-2 text-xs font-bold text-[#171717]">Salvar grupo</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showReplaceLinkModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl border border-neutral-200 bg-white p-6">
                <h2 class="text-lg font-bold text-[#171717]">Substituir link revogado</h2>
                <p class="mt-1 text-xs text-neutral-600">Informe o novo link oficial gerado no WhatsApp e o motivo da troca.</p>

                <form wire:submit="replaceRevokedLink" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#171717]">Novo link oficial</label>
                        <input type="url" wire:model="newInviteUrl" required placeholder="https://chat.whatsapp.com/..." class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 font-mono text-xs" />
                        @error('newInviteUrl') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#171717]">Motivo da substituição</label>
                        <input type="text" wire:model="replaceReason" required placeholder="Ex: Link anterior revogado pelo admin" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                        @error('replaceReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showReplaceLinkModal', false)" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold text-[#171717]">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-[#171717] px-4 py-2 text-xs font-bold text-white">Confirmar substituição</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

