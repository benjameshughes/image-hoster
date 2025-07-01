<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;

use function Livewire\Volt\form;
use function Livewire\Volt\layout;

layout('layouts.guest');

form(LoginForm::class);

$login = function () {
    $this->validate();

    $this->form->authenticate();

    Session::regenerate();

    $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
};

?>

<div>
    <!-- Session Status -->
    @if(session('status'))
        <x-mary-alert class="mb-4" icon="o-check-circle" success dismissible>
            {{ session('status') }}
        </x-mary-alert>
    @endif

    <x-mary-form wire:submit="login">
        <x-mary-header title="{{ __('Sign in to your account') }}" subtitle="{{ __('Welcome back! Please enter your details.') }}" size="text-2xl" />
        
        <!-- Email Address -->
        <x-mary-input 
            label="{{ __('Email') }}" 
            wire:model="form.email" 
            type="email" 
            icon="o-envelope" 
            placeholder="{{ __('Enter your email') }}"
            required 
            autofocus 
            autocomplete="username"
            hint="{{ __('We\'ll never share your email with anyone else.') }}" />

        <!-- Password -->
        <x-mary-input 
            label="{{ __('Password') }}" 
            wire:model="form.password" 
            type="password" 
            icon="o-lock-closed" 
            placeholder="{{ __('Enter your password') }}"
            required 
            autocomplete="current-password" />

        <!-- Remember Me -->
        <x-mary-checkbox 
            label="{{ __('Remember me') }}" 
            wire:model="form.remember" 
            hint="{{ __('Keep me signed in on this device') }}" />

        <!-- Actions -->
        <x-slot:actions>
            @if (Route::has('password.request'))
                <x-mary-button 
                    label="{{ __('Forgot password?') }}" 
                    link="{{ route('password.request') }}"
                    class="btn-ghost" 
                    wire:navigate />
            @endif

            <x-mary-button 
                label="{{ __('Sign in') }}" 
                type="submit" 
                icon="o-paper-airplane" 
                class="btn-primary" 
                spinner="login" />
        </x-slot:actions>
    </x-mary-form>
</div>
