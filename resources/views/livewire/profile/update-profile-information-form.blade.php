<?php

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;

use function Livewire\Volt\state;

state([
    'name' => fn () => auth()->user()->name,
    'email' => fn () => auth()->user()->email
]);

$updateProfileInformation = function () {
    $user = Auth::user();

    $validated = $this->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
    ]);

    $user->fill($validated);

    if ($user->isDirty('email')) {
        $user->email_verified_at = null;
    }

    $user->save();

    $this->dispatch('profile-updated', name: $user->name);
};

$sendVerification = function () {
    $user = Auth::user();

    if ($user->hasVerifiedEmail()) {
        $this->redirectIntended(default: route('dashboard', absolute: false));

        return;
    }

    $user->sendEmailVerificationNotification();

    Session::flash('status', 'verification-link-sent');
};

?>

<x-mary-card>
    <x-mary-header 
        title="{{ __('Profile Information') }}" 
        subtitle="{{ __("Update your account's profile information and email address.") }}" />

    <x-mary-form wire:submit="updateProfileInformation">
        <x-mary-input 
            label="{{ __('Name') }}" 
            wire:model="name" 
            icon="o-user" 
            placeholder="{{ __('Enter your full name') }}"
            required 
            autofocus 
            autocomplete="name" />

        <x-mary-input 
            label="{{ __('Email') }}" 
            wire:model="email" 
            type="email" 
            icon="o-envelope" 
            placeholder="{{ __('Enter your email address') }}"
            required 
            autocomplete="username" />

        @if (auth()->user() instanceof MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
            <x-mary-alert class="mt-4" icon="o-exclamation-triangle" warning>
                <div class="flex flex-col gap-2">
                    <span>{{ __('Your email address is unverified.') }}</span>
                    
                    <x-mary-button 
                        wire:click.prevent="sendVerification" 
                        label="{{ __('Click here to re-send the verification email.') }}"
                        class="btn-link btn-sm p-0 h-auto justify-start"
                        spinner="sendVerification" />
                </div>
            </x-mary-alert>

            @if (session('status') === 'verification-link-sent')
                <x-mary-alert class="mt-2" icon="o-check-circle" success dismissible>
                    {{ __('A new verification link has been sent to your email address.') }}
                </x-mary-alert>
            @endif
        @endif

        <x-slot:actions>
            <div class="flex items-center gap-4">
                <x-mary-button 
                    label="{{ __('Save') }}" 
                    type="submit" 
                    icon="o-check" 
                    class="btn-primary" 
                    spinner="updateProfileInformation" />

                <div x-data="{ show: false }" 
                     x-on:profile-updated.window="show = true; setTimeout(() => show = false, 3000)" 
                     x-show="show" 
                     x-transition 
                     class="text-sm text-green-600 font-medium">
                    {{ __('Saved.') }}
                </div>
            </div>
        </x-slot:actions>
    </x-mary-form>
</x-mary-card>
