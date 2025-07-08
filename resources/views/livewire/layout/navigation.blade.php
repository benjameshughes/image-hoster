<?php

use App\Livewire\Actions\Logout;

$logout = function (Logout $logout) {
    $logout();

    $this->redirect('/', navigate: true);
};

?>

<div class="flex flex-col h-full">
    {{-- Brand --}}
    <div class="mb-6">
        <x-mary-button label="{{ config('app.name') }}" link="{{ route('dashboard') }}" class="btn-ghost text-xl font-bold" wire:navigate />
    </div>

    {{-- Navigation Items --}}
    <div class="flex-1">
        <x-mary-menu activate-by-route>
            <x-mary-menu-item title="{{ __('Dashboard') }}" icon="o-home" link="{{ route('dashboard') }}" />
            <x-mary-menu-item title="{{ __('Media Library') }}" icon="o-photo" link="{{ route('media.index') }}" />
            <x-mary-menu-item title="{{ __('WordPress Import') }}" icon="o-arrow-down-tray" link="{{ route('import.dashboard') }}" />
            <x-mary-menu-separator />
            <x-mary-menu-sub title="{{ __('Account') }}" icon="o-user">
                <x-mary-menu-item title="{{ __('Profile') }}" icon="o-user" link="{{ route('profile') }}" />
                <x-mary-menu-item title="{{ __('Settings') }}" icon="o-cog-6-tooth" link="#" />
                <x-mary-menu-separator />
                <x-mary-menu-item title="{{ __('Logout') }}" icon="o-arrow-left-on-rectangle" wire:click="logout" />
            </x-mary-menu-sub>
        </x-mary-menu>
    </div>

    {{-- User Info Footer --}}
    <div class="mt-auto pt-4 border-t">
        <div class="flex items-center gap-3 p-2">
            <x-mary-avatar :image="gravatar(auth()->user()->email)" class="w-10 h-10" />
            <div class="flex-1 min-w-0">
                <div class="font-medium text-sm truncate" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</div>
            </div>
            <x-mary-theme-toggle class="btn-sm btn-circle" />
        </div>
    </div>
</div>