<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

use function Livewire\Volt\rules;
use function Livewire\Volt\state;

state([
    'current_password' => '',
    'password' => '',
    'password_confirmation' => ''
]);

rules([
    'current_password' => ['required', 'string', 'current_password'],
    'password' => ['required', 'string', Password::defaults(), 'confirmed'],
]);

$updatePassword = function () {
    try {
        $validated = $this->validate();
    } catch (ValidationException $e) {
        $this->reset('current_password', 'password', 'password_confirmation');

        throw $e;
    }

    Auth::user()->update([
        'password' => Hash::make($validated['password']),
    ]);

    $this->reset('current_password', 'password', 'password_confirmation');

    $this->dispatch('password-updated');
};

?>

<x-mary-card>
    <x-mary-header 
        title="{{ __('Update Password') }}" 
        subtitle="{{ __('Ensure your account is using a long, random password to stay secure.') }}" />

    <x-mary-form wire:submit="updatePassword">
        <x-mary-input 
            label="{{ __('Current Password') }}" 
            wire:model="current_password" 
            type="password" 
            icon="o-lock-closed" 
            placeholder="{{ __('Enter your current password') }}"
            autocomplete="current-password" 
            hint="{{ __('Enter your current password to confirm changes') }}" />

        <x-mary-input 
            label="{{ __('New Password') }}" 
            wire:model="password" 
            type="password" 
            icon="o-key" 
            placeholder="{{ __('Enter a new secure password') }}"
            autocomplete="new-password" 
            hint="{{ __('Choose a strong password with at least 8 characters') }}" />

        <x-mary-input 
            label="{{ __('Confirm Password') }}" 
            wire:model="password_confirmation" 
            type="password" 
            icon="o-shield-check" 
            placeholder="{{ __('Confirm your new password') }}"
            autocomplete="new-password" 
            hint="{{ __('Re-enter your new password to confirm') }}" />

        <x-slot:actions>
            <div class="flex items-center gap-4">
                <x-mary-button 
                    label="{{ __('Save') }}" 
                    type="submit" 
                    icon="o-check" 
                    class="btn-primary" 
                    spinner="updatePassword" />

                <div x-data="{ show: false }" 
                     x-on:password-updated.window="show = true; setTimeout(() => show = false, 3000)" 
                     x-show="show" 
                     x-transition 
                     class="text-sm text-green-600 font-medium">
                    {{ __('Saved.') }}
                </div>
            </div>
        </x-slot:actions>
    </x-mary-form>
</x-mary-card>
