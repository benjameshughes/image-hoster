<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

use function Livewire\Volt\layout;

layout('layouts.guest');

$sendVerification = function () {
    if (Auth::user()->hasVerifiedEmail()) {
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

        return;
    }

    Auth::user()->sendEmailVerificationNotification();

    Session::flash('status', 'verification-link-sent');
};

$logout = function (Logout $logout) {
    $logout();

    $this->redirect('/', navigate: true);
};

?>

<div>
    <x-mary-header 
        title="{{ __('Verify Your Email') }}" 
        subtitle="{{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you?') }}" 
        size="text-2xl" />

    @if (session('status') == 'verification-link-sent')
        <x-mary-alert class="mb-4" icon="o-check-circle" success dismissible>
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </x-mary-alert>
    @endif

    <x-mary-card>
        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
            <x-mary-button 
                wire:click="sendVerification"
                label="{{ __('Resend Verification Email') }}"
                icon="o-envelope"
                class="btn-primary"
                spinner="sendVerification" />

            <x-mary-button 
                wire:click="logout"
                label="{{ __('Log Out') }}"
                icon="o-arrow-left-on-rectangle"
                class="btn-ghost" />
        </div>
    </x-mary-card>
</div>
