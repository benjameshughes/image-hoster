<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto">
            <div class="overflow-hidden">
                <div class="p-6 text-gray-900">
                    <livewire:images.uploader/>
                </div>
            </div>
        </div>
    </div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto">
            <div class="overflow-hidden">
                <div class="p-6 text-gray-900">

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
