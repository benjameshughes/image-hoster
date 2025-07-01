<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet"/>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="drawer lg:drawer-open">
        {{-- Mobile drawer toggle --}}
        <input id="main-drawer" type="checkbox" class="drawer-toggle" />
        
        {{-- Page content --}}
        <div class="drawer-content flex flex-col">
            {{-- Mobile nav bar --}}
            <div class="navbar bg-base-300 lg:hidden">
                <div class="flex-none">
                    <label for="main-drawer" class="btn btn-square btn-ghost">
                        <x-mary-icon name="o-bars-3" class="w-6 h-6" />
                    </label>
                </div>
                <div class="flex-1">
                    <a href="{{ route('dashboard') }}" class="btn btn-ghost text-xl" wire:navigate>
                        {{ config('app.name') }}
                    </a>
                </div>
                <div class="flex-none">
                    <x-mary-theme-toggle class="btn btn-sm btn-circle" />
                </div>
            </div>
            
            {{-- Main content --}}
            <main class="flex-1 p-6">
                <!-- Page Heading -->
                @if (isset($header))
                    <header class="bg-white dark:bg-zinc-900 shadow mb-6 -m-6 mb-6 p-6">
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-white/80 leading-tight">
                            {{ $header }}
                        </h2>
                    </header>
                @endif

                <!-- Page Content -->
                {{ $slot }}
            </main>
        </div>
        
        {{-- Sidebar --}}
        <div class="drawer-side">
            <label for="main-drawer" class="drawer-overlay"></label>
            <aside class="min-h-full w-64 bg-base-200 flex flex-col">
                <div class="p-4 flex-1">
                    <livewire:layout.navigation/>
                </div>
            </aside>
        </div>
    </div>
</body>
</html>
