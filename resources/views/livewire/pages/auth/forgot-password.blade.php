<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

layout('layouts.guest');

state(['email' => '']);

rules(['email' => ['required', 'string', 'email']]);

$sendPasswordResetLink = function () {
    $this->validate();

    // We will send the password reset link to this user. Once we have attempted
    // to send the link, we will examine the response then see the message we
    // need to show to the user. Finally, we'll send out a proper response.
    $status = Password::sendResetLink(
        $this->only('email')
    );

    if ($status != Password::RESET_LINK_SENT) {
        $this->addError('email', __($status));

        return;
    }

    $this->reset('email');

    Session::flash('status', __($status));
};

?>

<div>
    <!-- Session Status -->
    @if(session('status'))
        <x-mary-alert class="mb-4" icon="o-check-circle" success dismissible>
            {{ session('status') }}
        </x-mary-alert>
    @endif

    <x-mary-form wire:submit="sendPasswordResetLink">
        <x-mary-header 
            title="{{ __('Forgot Password?') }}" 
            subtitle="{{ __('No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}" 
            size="text-2xl" />
        
        <!-- Email Address -->
        <x-mary-input 
            label="{{ __('Email') }}" 
            wire:model="email" 
            type="email" 
            icon="o-envelope" 
            placeholder="{{ __('Enter your email address') }}"
            required 
            autofocus
            hint="{{ __('Enter the email address associated with your account') }}" />

        <!-- Actions -->
        <x-slot:actions>
            <x-mary-button 
                label="{{ __('Back to login') }}" 
                link="{{ route('login') }}"
                class="btn-ghost" 
                wire:navigate />

            <x-mary-button 
                label="{{ __('Send Reset Link') }}" 
                type="submit" 
                icon="o-paper-airplane" 
                class="btn-primary" 
                spinner="sendPasswordResetLink" />
        </x-slot:actions>
    </x-mary-form>
</div>
