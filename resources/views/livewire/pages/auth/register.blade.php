<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

layout('layouts.guest');

state([
    'name' => '',
    'email' => '',
    'password' => '',
    'password_confirmation' => ''
]);

rules([
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
    'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
]);

$register = function () {
    $validated = $this->validate();

    $validated['password'] = Hash::make($validated['password']);

    event(new Registered($user = User::create($validated)));

    Auth::login($user);

    $this->redirect(route('dashboard', absolute: false), navigate: true);
};

?>

<div>
    <x-mary-form wire:submit="register">
        <x-mary-header title="{{ __('Create your account') }}" subtitle="{{ __('Join us today! Please fill in your details.') }}" size="text-2xl" />
        
        <!-- Name -->
        <x-mary-input 
            label="{{ __('Full Name') }}" 
            wire:model="name" 
            type="text" 
            icon="o-user" 
            placeholder="{{ __('Enter your full name') }}"
            required 
            autofocus 
            autocomplete="name"
            hint="{{ __('Your display name on the platform') }}" />

        <!-- Email Address -->
        <x-mary-input 
            label="{{ __('Email') }}" 
            wire:model="email" 
            type="email" 
            icon="o-envelope" 
            placeholder="{{ __('Enter your email address') }}"
            required 
            autocomplete="username"
            hint="{{ __('We\'ll use this to send you important notifications') }}" />

        <!-- Password -->
        <x-mary-input 
            label="{{ __('Password') }}" 
            wire:model="password" 
            type="password" 
            icon="o-lock-closed" 
            placeholder="{{ __('Create a strong password') }}"
            required 
            autocomplete="new-password"
            hint="{{ __('Must be at least 8 characters') }}" />

        <!-- Confirm Password -->
        <x-mary-input 
            label="{{ __('Confirm Password') }}" 
            wire:model="password_confirmation" 
            type="password" 
            icon="o-lock-closed" 
            placeholder="{{ __('Confirm your password') }}"
            required 
            autocomplete="new-password"
            hint="{{ __('Must match the password above') }}" />

        <!-- Actions -->
        <x-slot:actions>
            <x-mary-button 
                label="{{ __('Already registered?') }}" 
                link="{{ route('login') }}"
                class="btn-ghost" 
                wire:navigate />

            <x-mary-button 
                label="{{ __('Create Account') }}" 
                type="submit" 
                icon="o-user-plus" 
                class="btn-primary" 
                spinner="register" />
        </x-slot:actions>
    </x-mary-form>
</div>
