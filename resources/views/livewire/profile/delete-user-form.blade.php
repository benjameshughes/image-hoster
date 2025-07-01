<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\rules;
use function Livewire\Volt\state;

state([
    'password' => '',
    'showDeleteModal' => false
]);

rules(['password' => ['required', 'string', 'current_password']]);

$deleteUser = function (Logout $logout) {
    $this->validate();

    tap(Auth::user(), $logout(...))->delete();

    $this->redirect('/', navigate: true);
};

?>

<x-mary-card>
    <x-mary-header 
        title="{{ __('Delete Account') }}" 
        subtitle="{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}" />

    <x-mary-button 
        label="{{ __('Delete Account') }}" 
        icon="o-trash" 
        class="btn-error"
        @click="$wire.showDeleteModal = true" />

    <x-mary-modal 
        wire:model="showDeleteModal" 
        title="{{ __('Are you sure you want to delete your account?') }}"
        subtitle="{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}"
        persistent>
        
        <x-mary-form wire:submit="deleteUser">
            <x-mary-input 
                label="{{ __('Password') }}" 
                wire:model="password" 
                type="password" 
                icon="o-lock-closed" 
                placeholder="{{ __('Enter your password to confirm') }}"
                required 
                hint="{{ __('This action cannot be undone') }}" />

            <x-slot:actions>
                <x-mary-button 
                    label="{{ __('Cancel') }}" 
                    class="btn-ghost" 
                    @click="$wire.showDeleteModal = false" />
                
                <x-mary-button 
                    label="{{ __('Delete Account') }}" 
                    type="submit" 
                    icon="o-trash" 
                    class="btn-error" 
                    spinner="deleteUser" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</x-mary-card>
