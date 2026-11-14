<div class="mx-auto max-w-[1440px] space-y-6">
    <div>
        <h1 class="text-xl font-bold text-[#171717]">Configurações & Integração (Administrador)</h1>
        <p class="text-sm text-neutral-600">Segredos da Meta Cloud API residem exclusivamente no <code>.env</code> do servidor e nunca são exibidos no navegador.</p>
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

    <div class="grid gap-6 lg:grid-cols-2">
        <!-- Modo de Operação & Retenção -->
        <section class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-bold uppercase tracking-wide text-[#171717]">Modo de Envio & Política de Retenção</h2>

            <form wire:submit="saveOperationSettings" class="mt-4 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-[#171717]">Modo padrão da operação</label>
                    <select wire:model="whatsappMode" class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm">
                        <option value="assisted">Modo Assistido (wa.me — sem credenciais)</option>
                        <option value="official_api">API Oficial (WhatsApp Business Cloud API)</option>
                        <option value="simulated">Modo Simulado (somente homologação local)</option>
                    </select>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold">Retenção contatos sem aceite (dias)</label>
                        <input type="number" min="30" max="3650" wire:model="retentionDaysContacts" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold">Retenção mínima de descadastro (dias)</label>
                        <input type="number" min="30" max="3650" wire:model="retentionDaysSuppression" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="submit" class="rounded-lg bg-[#F9C80E] px-4 py-2 text-xs font-bold text-[#171717]">
                        Salvar modo e retenção
                    </button>
                    <button
                        type="button"
                        wire:click="runRetentionCleanup"
                        wire:confirm="Executar limpeza dos registros vencidos mantendo os bloqueios de descadastro vigentes?"
                        class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold text-[#171717]"
                    >
                        Executar limpeza de retenção
                    </button>
                </div>
            </form>
        </section>

        <!-- Checklist da Meta Cloud API -->
        <section class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-[#171717]">Status das Credenciais Meta (Servidor)</h2>
                @if ($isApiConfigured)
                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">Credenciais presentes</span>
                @else
                    <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-bold text-red-800">API não configurada</span>
                @endif
            </div>

            <div class="mt-3 rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3 text-xs">
                <p class="font-semibold text-[#171717]">URL do Webhook HTTPS:</p>
                <p class="mt-1 font-mono text-neutral-700">{{ $webhookUrl }}</p>
            </div>

            <ul class="mt-4 divide-y divide-neutral-200 text-xs">
                @foreach ($checklist as $item)
                    <li class="flex items-center justify-between py-2">
                        <div>
                            <p class="font-semibold text-[#171717]">{{ $item['label'] }}</p>
                            <p class="font-mono text-[11px] text-neutral-500">{{ $item['env'] }}</p>
                        </div>
                        @if ($item['configured'])
                            <span class="rounded bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800">Configurada</span>
                        @else
                            <span class="rounded bg-amber-100 px-2 py-0.5 font-semibold text-amber-900">Pendente</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-[11px] text-neutral-500">
                Custos, limites de envio e aprovação de modelos dependem da Meta e devem ser consultados no WhatsApp Manager antes da ativação.
            </p>
        </section>
    </div>

    <!-- Paleta editável + Equipe -->
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-bold uppercase tracking-wide text-[#171717]">Paleta Visual Editável</h2>
            <form wire:submit="savePalette" class="mt-4 space-y-4">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <label class="block text-xs font-semibold">Destaque</label>
                        <input type="color" wire:model="palettePrimary" class="mt-1 h-9 w-full rounded border border-neutral-300" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold">Escuro</label>
                        <input type="color" wire:model="paletteDark" class="mt-1 h-9 w-full rounded border border-neutral-300" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold">Superfície</label>
                        <input type="color" wire:model="paletteSurface" class="mt-1 h-9 w-full rounded border border-neutral-300" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold">Fundo</label>
                        <input type="color" wire:model="paletteBg" class="mt-1 h-9 w-full rounded border border-neutral-300" />
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-[#171717] px-4 py-2 text-xs font-bold text-white">Aplicar paleta</button>
                    <button type="button" wire:click="resetPalette" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold">Restaurar padrão Águia</button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-bold uppercase tracking-wide text-[#171717]">Usuários & Perfis</h2>

            <form wire:submit="createUser" class="mt-3 grid gap-2 sm:grid-cols-2">
                <input type="text" wire:model="newUserName" placeholder="Nome" required class="rounded border border-neutral-300 px-2.5 py-1.5 text-xs" />
                <input type="email" wire:model="newUserEmail" placeholder="E-mail" required class="rounded border border-neutral-300 px-2.5 py-1.5 text-xs" />
                <input type="password" wire:model="newUserPassword" placeholder="Senha (mín. 8)" required class="rounded border border-neutral-300 px-2.5 py-1.5 text-xs" />
                <div class="flex gap-2">
                    <select wire:model="newUserRole" class="flex-1 rounded border border-neutral-300 bg-white px-2 py-1.5 text-xs">
                        <option value="operator">Operador</option>
                        <option value="admin">Administrador</option>
                    </select>
                    <button type="submit" class="rounded bg-[#F9C80E] px-3 py-1.5 text-xs font-bold text-[#171717]">Adicionar</button>
                </div>
            </form>
            @error('newUserEmail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

            <div class="mt-4 divide-y divide-neutral-200 text-xs">
                @foreach ($users as $user)
                    <div class="flex items-center justify-between py-2">
                        <div>
                            <span class="font-semibold text-[#171717]">{{ $user->name }}</span>
                            <span class="ml-1 text-neutral-500">({{ $user->email }})</span>
                            <span class="ml-1 rounded bg-[#F5F6F8] px-1.5 py-0.5 text-[10px] font-bold uppercase">{{ $user->role }}</span>
                            @if (! $user->is_active)
                                <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-800">Inativo</span>
                            @endif
                        </div>
                        @if ($user->id !== auth()->id())
                            <div class="flex gap-1.5">
                                <button type="button" wire:click="toggleUserRole({{ $user->id }})" class="rounded border border-neutral-300 px-2 py-1 font-semibold">
                                    Alternar perfil
                                </button>
                                <button type="button" wire:click="toggleUserActive({{ $user->id }})" class="rounded border border-neutral-300 px-2 py-1 font-semibold">
                                    {{ $user->is_active ? 'Desativar' : 'Ativar' }}
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <!-- Audit Logs -->
    <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
        <div class="border-b border-neutral-200 px-5 py-3.5">
            <h2 class="text-sm font-bold text-[#171717]">Auditoria recente (audit_logs)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-left text-xs">
                <thead class="bg-[#F5F6F8] font-semibold uppercase text-neutral-600">
                    <tr>
                        <th class="px-4 py-2.5">Data</th>
                        <th class="px-4 py-2.5">Ação</th>
                        <th class="px-4 py-2.5">Usuário</th>
                        <th class="px-4 py-2.5">Metadados</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white">
                    @foreach ($auditLogs as $log)
                        <tr>
                            <td class="px-4 py-2 text-neutral-500">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="px-4 py-2 font-mono font-semibold text-[#171717]">{{ $log->action }}</td>
                            <td class="px-4 py-2">{{ $log->user?->name ?? 'Sistema/Webhook' }}</td>
                            <td class="px-4 py-2 font-mono text-[11px] text-neutral-600">{{ json_encode($log->metadata, JSON_UNESCAPED_UNICODE) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
