<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered(($user = User::create($validated))));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-5">
    <div class="text-center">
        <h1 class="text-lg font-bold text-[#171717]">Cadastro da Equipe</h1>
        <p class="mt-1 text-xs text-neutral-600">Crie seu acesso de operador para gerenciar contatos e convites.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-4">
        <!-- Name -->
        <flux:input wire:model="name" id="name" label="Nome completo" type="text" name="name" required autofocus autocomplete="name" placeholder="Seu nome" />

        <!-- Email Address -->
        <flux:input wire:model="email" id="email" label="E-mail" type="email" name="email" required autocomplete="email" placeholder="voce@aguiadaspromos.com.br" />

        <!-- Password -->
        <flux:input
            wire:model="password"
            id="password"
            label="Senha"
            type="password"
            name="password"
            required
            autocomplete="new-password"
            placeholder="Mínimo 8 caracteres"
        />

        <!-- Confirm Password -->
        <flux:input
            wire:model="password_confirmation"
            id="password_confirmation"
            label="Confirmar senha"
            type="password"
            name="password_confirmation"
            required
            autocomplete="new-password"
            placeholder="Repita a senha"
        />

        <div class="pt-1">
            <button type="submit" class="w-full rounded-lg bg-[#171717] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-neutral-800">
                Criar acesso da equipe
            </button>
        </div>
    </form>

    <div class="border-t border-neutral-200 pt-3 text-center text-xs text-neutral-600">
        Já possui acesso?
        <x-text-link href="{{ route('login') }}" class="font-semibold text-[#171717]">Entrar</x-text-link>
    </div>
</div>
