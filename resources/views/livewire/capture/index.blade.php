<div class="mx-auto max-w-[1440px] space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-[#171717]">Captação de Interessados</h1>
            <p class="text-sm text-neutral-600">Divulgue o link público e QR Code em canais próprios para coletar autorizações explícitas com registro de versão.</p>
        </div>
        <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="rounded-lg bg-[#F9C80E] px-4 py-2 text-xs font-bold text-[#171717] hover:bg-amber-400">
            Abrir página pública
        </a>
    </div>

    @if ($flashMessage)
        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
            {{ $flashMessage }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Link & QR Code -->
        <section class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-bold uppercase tracking-wide text-[#171717]">Link compartilhável & QR Code</h2>
            <p class="mt-1 text-xs text-neutral-600">Total captado pela página pública: <strong>{{ $totalCaptured }}</strong></p>

            <div class="mt-4 flex flex-col items-center rounded-lg border border-neutral-200 bg-[#F5F6F8] p-4">
                <img src="{{ route('capture.qr') }}" alt="QR Code Captação Águia das Promos" class="h-48 w-48 rounded bg-white p-2" />
                <a href="{{ route('capture.qr', ['download' => 1]) }}" class="mt-3 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-[#171717] hover:bg-neutral-50">
                    Baixar QR Code (.svg)
                </a>
            </div>

            <div class="mt-4">
                <label class="block text-xs font-semibold text-[#171717]">URL pública</label>
                <div class="mt-1 flex gap-2">
                    <input id="capture-public-url" type="text" readonly value="{{ $publicUrl }}" class="w-full rounded-lg border border-neutral-300 bg-[#F5F6F8] px-3 py-2 font-mono text-xs text-[#171717]" />
                    <button
                        type="button"
                        onclick="navigator.clipboard.writeText(document.getElementById('capture-public-url').value); this.textContent='Copiado!'; setTimeout(() => this.textContent='Copiar', 1500);"
                        class="shrink-0 rounded-lg bg-[#171717] px-3 py-2 text-xs font-semibold text-white hover:bg-neutral-800"
                    >
                        Copiar
                    </button>
                </div>
            </div>
        </section>

        <!-- Config (Admin) / Preview -->
        <section class="rounded-xl border border-neutral-200 bg-white p-5 lg:col-span-2">
            <h2 class="text-sm font-bold uppercase tracking-wide text-[#171717]">Textos, Categorias & Versão do Termo</h2>
            <p class="mt-1 text-xs text-neutral-600">Cada inscrição grava a versão exata do termo aceito pelo interessado.</p>

            @if (auth()->user()?->isAdmin())
                <form wire:submit="saveCaptureConfig" class="mt-4 space-y-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-[#171717]">Título principal da página</label>
                            <input type="text" wire:model="headline" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#171717]">Versão do termo</label>
                            <input type="text" wire:model="policyVersion" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 font-mono text-xs" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#171717]">Descrição</label>
                        <textarea wire:model="description" rows="2" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#171717]">Categorias exibidas (uma por linha)</label>
                        <textarea wire:model="categoriesRaw" rows="4" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#171717]">WhatsApp de atendimento pós-inscrição (opcional)</label>
                        <div class="mt-1 grid grid-cols-4 gap-2">
                            <select wire:model="supportWhatsappCountryCode" class="col-span-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-2 text-xs text-[#171717]">
                                <option value="55">+55 BR</option>
                                <option value="351">+351 PT</option>
                                <option value="1">+1 US</option>
                            </select>
                            <input
                                type="text"
                                wire:model="supportWhatsapp"
                                placeholder="(11) 99999-8888"
                                class="col-span-3 rounded-lg border border-neutral-300 px-3 py-2 text-sm"
                            />
                        </div>
                        @error('supportWhatsapp')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-[11px] text-neutral-500">Se preenchido, a tela de confirmação em <code>/inscrever</code> mostra também um botão <code>wa.me</code> com mensagem pré-pronta.</p>
                    </div>

                    <button type="submit" class="rounded-lg bg-[#171717] px-4 py-2 text-xs font-bold text-white hover:bg-neutral-800">
                        Salvar alterações da captação
                    </button>
                </form>

                <div class="mt-6 border-t border-neutral-200 pt-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wide text-[#171717]">Webhook JSON para formulários externos</h3>
                            <p class="mt-0.5 text-xs text-neutral-600">Conecte Google Forms, Typeform, Make, n8n, Zapier ou landing pages diretamente ao CRM.</p>
                        </div>
                        <span class="inline-flex w-fit items-center rounded px-2 py-0.5 text-[11px] font-semibold {{ $hasWebhookToken ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $hasWebhookToken ? 'Token configurado' : 'Sem token configurado' }}
                        </span>
                    </div>

                    <div class="mt-3">
                        <label class="block text-xs font-semibold text-[#171717]">Endpoint (POST JSON)</label>
                        <div class="mt-1 flex gap-2">
                            <input id="capture-webhook-url" type="text" readonly value="{{ $webhookUrl }}" class="w-full rounded-lg border border-neutral-300 bg-[#F5F6F8] px-3 py-2 font-mono text-xs text-[#171717]" />
                            <button
                                type="button"
                                onclick="navigator.clipboard.writeText(document.getElementById('capture-webhook-url').value); this.textContent='Copiado!'; setTimeout(() => this.textContent='Copiar', 1500);"
                                class="shrink-0 rounded-lg bg-[#171717] px-3 py-2 text-xs font-semibold text-white hover:bg-neutral-800"
                            >
                                Copiar
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            wire:click="generateWebhookToken"
                            class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-[#171717] hover:bg-neutral-50"
                        >
                            {{ $hasWebhookToken ? 'Rotacionar token Bearer' : 'Gerar token Bearer' }}
                        </button>
                    </div>

                    @if ($generatedWebhookToken)
                        <div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3">
                            <p class="text-xs font-semibold text-amber-900">Copie seu token agora (não será exibido novamente):</p>
                            <div class="mt-1.5 flex gap-2">
                                <input id="capture-webhook-token" type="text" readonly value="{{ $generatedWebhookToken }}" class="w-full rounded-lg border border-amber-300 bg-white px-3 py-1.5 font-mono text-xs text-[#171717]" />
                                <button
                                    type="button"
                                    onclick="navigator.clipboard.writeText(document.getElementById('capture-webhook-token').value); this.textContent='Copiado!'; setTimeout(() => this.textContent='Copiar', 1500);"
                                    class="shrink-0 rounded-lg bg-[#171717] px-3 py-1.5 text-xs font-semibold text-white hover:bg-neutral-800"
                                >
                                    Copiar
                                </button>
                            </div>
                        </div>
                    @endif

                    <details class="mt-3 rounded-lg border border-neutral-200 bg-[#F5F6F8] p-3 text-xs text-neutral-700">
                        <summary class="cursor-pointer font-semibold text-[#171717]">Exemplo de requisição JSON (n8n / Make / Google Forms)</summary>
                        <pre class="mt-2 overflow-x-auto rounded bg-neutral-900 p-3 font-mono text-[11px] text-neutral-100">curl -X POST "{{ $webhookUrl }}" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Maria Silva",
    "phone": "+5511999998888",
    "interests": ["Eletrônicos &amp; Tech"],
    "origin": "google_forms",
    "evidence_reference": "form_resp_123",
    "consent_accepted": true
  }'</pre>
                    </details>
                </div>
            @else
                <div class="mt-4 rounded-lg border border-neutral-200 bg-[#F5F6F8] p-4 text-xs text-neutral-700">
                    Apenas administradores podem editar os textos e a versão do termo de consentimento. Versão ativa: <strong>{{ $policyVersion }}</strong>.
                </div>
            @endif

            <div class="mt-6 border-t border-neutral-200 pt-4">
                <h3 class="text-xs font-bold uppercase text-neutral-600">Últimos interessados captados</h3>
                @if ($recentCaptured->isEmpty())
                    <p class="mt-2 text-xs text-neutral-500">Nenhuma inscrição pública registrada ainda.</p>
                @else
                    <div class="mt-2 divide-y divide-neutral-200 text-xs">
                        @foreach ($recentCaptured as $item)
                            <div class="flex items-center justify-between py-2">
                                <div>
                                    <span class="font-semibold text-[#171717]">{{ $item->name }}</span>
                                    <span class="ml-2 font-mono text-neutral-500">{{ $item->phone_e164 }}</span>
                                </div>
                                <span class="text-neutral-500">{{ $item->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>

