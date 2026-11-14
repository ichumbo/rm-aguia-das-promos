<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-5">
    <div class="text-center">
        <h1 class="text-lg font-bold text-[#171717]">Acesso da Equipe</h1>
        <p class="mt-1 text-xs text-neutral-600">Entre com seu e-mail e senha para atender contatos e enviar convites.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-4">
        <!-- Email Address -->
        <flux:input wire:model="email" label="E-mail da equipe" type="email" name="email" required autofocus autocomplete="email" placeholder="voce@aguiadaspromos.com.br" />

        <!-- Password -->
        <div class="relative">
            <flux:input
                wire:model="password"
                label="Senha"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Sua senha"
            />

            @if (Route::has('password.request'))
                <x-text-link class="absolute right-0 top-0 text-xs" href="{{ route('password.request') }}">
                    Esqueci minha senha
                </x-text-link>
            @endif
        </div>

        <!-- Remember Me -->
        <flux:checkbox wire:model="remember" label="Manter sessão conectada" />

        <div class="pt-1">
            <button type="submit" class="w-full rounded-lg bg-[#F9C80E] px-4 py-2.5 text-sm font-bold text-[#171717] transition hover:bg-amber-400">
                Entrar no CRM
            </button>
        </div>
    </form>

    <div class="border-t border-neutral-200 pt-3 text-center text-xs text-neutral-600">
        Ainda não tem conta?
        <x-text-link href="{{ route('register') }}" class="font-semibold text-[#171717]">Criar acesso da equipe</x-text-link>
    </div>
</div>
