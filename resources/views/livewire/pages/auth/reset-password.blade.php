<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

layout('layouts.guest');

state('token')->locked();

state([
    'email' => fn () => request()->string('email')->value(),
    'password' => '',
    'password_confirmation' => ''
]);

rules([
    'token' => ['required'],
    'email' => ['required', 'string', 'email'],
    'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
]);

$resetPassword = function () {
    $this->validate();

    // Here we will attempt to reset the user's password. If it is successful we
    // will update the password on an actual user model and persist it to the
    // database. Otherwise we will parse the error and return the response.
    $status = Password::reset(
        $this->only('email', 'password', 'password_confirmation', 'token'),
        function ($user) {
            $user->forceFill([
                'password' => Hash::make($this->password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        }
    );

    // If the password was successfully reset, we will redirect the user back to
    // the application's home authenticated view. If there is an error we can
    // redirect them back to where they came from with their error message.
    if ($status != Password::PASSWORD_RESET) {
        $this->addError('email', __($status));

        return;
    }

    Session::flash('status', __($status));

    $this->redirectRoute('login', navigate: true);
};

?>

<div>
    <x-mary-form wire:submit="resetPassword">
        <x-mary-header 
            title="{{ __('Reset Password') }}" 
            subtitle="{{ __('Please enter your new password below') }}" 
            size="text-2xl" />
        
        <!-- Email Address -->
        <x-mary-input 
            label="{{ __('Email') }}" 
            wire:model="email" 
            type="email" 
            icon="o-envelope" 
            readonly
            hint="{{ __('This field is pre-filled from your reset link') }}" />

        <!-- Password -->
        <x-mary-input 
            label="{{ __('New Password') }}" 
            wire:model="password" 
            type="password" 
            icon="o-lock-closed" 
            placeholder="{{ __('Enter your new password') }}"
            required 
            autocomplete="new-password"
            hint="{{ __('Must be at least 8 characters') }}" />

        <!-- Confirm Password -->
        <x-mary-input 
            label="{{ __('Confirm New Password') }}" 
            wire:model="password_confirmation" 
            type="password" 
            icon="o-lock-closed" 
            placeholder="{{ __('Confirm your new password') }}"
            required 
            autocomplete="new-password"
            hint="{{ __('Must match the password above') }}" />

        <!-- Actions -->
        <x-slot:actions>
            <x-mary-button 
                label="{{ __('Back to login') }}" 
                link="{{ route('login') }}"
                class="btn-ghost" 
                wire:navigate />

            <x-mary-button 
                label="{{ __('Reset Password') }}" 
                type="submit" 
                icon="o-key" 
                class="btn-primary" 
                spinner="resetPassword" />
        </x-slot:actions>
    </x-mary-form>
</div>
