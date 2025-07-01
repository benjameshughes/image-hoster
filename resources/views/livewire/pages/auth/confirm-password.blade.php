<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

layout('layouts.guest');

state(['password' => '']);

rules(['password' => ['required', 'string']]);

$confirmPassword = function () {
    $this->validate();

    if (! Auth::guard('web')->validate([
        'email' => Auth::user()->email,
        'password' => $this->password,
    ])) {
        throw ValidationException::withMessages([
            'password' => __('auth.password'),
        ]);
    }

    session(['auth.password_confirmed_at' => time()]);

    $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
};

?>

<div>
    <x-mary-form wire:submit="confirmPassword">
        <x-mary-header 
            title="{{ __('Confirm Password') }}" 
            subtitle="{{ __('This is a secure area of the application. Please confirm your password before continuing.') }}" 
            size="text-2xl" />
        
        <!-- Password -->
        <x-mary-input 
            label="{{ __('Password') }}" 
            wire:model="password" 
            type="password" 
            icon="o-lock-closed" 
            placeholder="{{ __('Enter your current password') }}"
            required 
            autofocus
            autocomplete="current-password"
            hint="{{ __('Enter your password to confirm your identity') }}" />

        <!-- Actions -->
        <x-slot:actions>
            <x-mary-button 
                label="{{ __('Confirm') }}" 
                type="submit" 
                icon="o-shield-check" 
                class="btn-primary" 
                spinner="confirmPassword" />
        </x-slot:actions>
    </x-mary-form>
</div>
