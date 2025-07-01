<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="corporate">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $image->alt_text ?? $image->original_name }} - Shared Image</title>

    <!-- Open Graph meta tags for social sharing -->
    <meta property="og:title" content="{{ $image->alt_text ?? $image->original_name }}">
    <meta property="og:description" content="{{ $image->description ?? 'Shared image' }}">
    <meta property="og:image" content="{{ route('images.public.serve', [$image->unique_id, 'compressed']) }}">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta property="og:type" content="website">
    
    <!-- Twitter Card meta tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $image->alt_text ?? $image->original_name }}">
    <meta name="twitter:description" content="{{ $image->description ?? 'Shared image' }}">
    <meta name="twitter:image" content="{{ route('images.public.serve', [$image->unique_id, 'compressed']) }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-base-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="navbar bg-base-200 shadow-sm">
            <div class="navbar-start">
                <h1 class="text-lg font-semibold">Shared Image</h1>
            </div>
            <div class="navbar-end">
                @if($image->user)
                    <span class="text-sm opacity-70">Shared by {{ $image->user->name }}</span>
                @endif
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 py-8">
            <div class="max-w-4xl mx-auto">
                <!-- Image Card -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <!-- Image Title and Meta -->
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h1 class="card-title text-2xl">{{ $image->alt_text ?? $image->original_name }}</h1>
                                @if($image->description)
                                    <p class="text-base-content/70 mt-2">{{ $image->description }}</p>
                                @endif
                            </div>
                            <div class="text-right text-sm text-base-content/60">
                                <div>{{ $image->formatted_size }}</div>
                                @if($image->width && $image->height)
                                    <div>{{ $image->width }} × {{ $image->height }}</div>
                                @endif
                                <div>{{ $image->view_count }} views</div>
                            </div>
                        </div>

                        <!-- Tags -->
                        @if($image->tags && count($image->tags) > 0)
                            <div class="flex flex-wrap gap-2 mb-4">
                                @foreach($image->tags as $tag)
                                    <span class="badge badge-outline">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif

                        <!-- Main Image -->
                        <div class="flex justify-center mb-6">
                            <img 
                                src="{{ route('images.public.serve', [$image->unique_id, 'compressed']) }}" 
                                alt="{{ $image->alt_text ?? $image->original_name }}"
                                class="max-w-full h-auto rounded-lg shadow-lg"
                                loading="lazy"
                            >
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-wrap gap-2 justify-center">
                            <a href="{{ route('images.public.serve', [$image->unique_id, 'original']) }}" 
                               class="btn btn-primary" target="_blank">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                View Original
                            </a>
                            
                            <a href="{{ route('images.public.serve', [$image->unique_id, 'original']) }}?download=1" 
                               class="btn btn-secondary">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Download
                            </a>

                            <button class="btn btn-ghost" onclick="copyShareLink()">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
                                </svg>
                                Copy Link
                            </button>

                            <button class="btn btn-ghost" onclick="showEmbedCodes()">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                </svg>
                                Embed
                            </button>
                        </div>

                        <!-- Image Metadata -->
                        <div class="mt-6 text-sm text-base-content/60 text-center">
                            <div>Uploaded {{ $image->created_at->diffForHumans() }}</div>
                            @if($image->compressionRatio())
                                <div>{{ $image->compressionRatio() }}% compression applied</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Embed Modal -->
    <dialog id="embedModal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg">Embed Codes</h3>
            <div class="py-4 space-y-4">
                <div>
                    <label class="label">
                        <span class="label-text font-semibold">HTML</span>
                    </label>
                    <textarea class="textarea textarea-bordered w-full" readonly id="htmlEmbed"></textarea>
                    <button class="btn btn-xs btn-ghost mt-1" onclick="copyToClipboard('htmlEmbed')">Copy</button>
                </div>
                
                <div>
                    <label class="label">
                        <span class="label-text font-semibold">Markdown</span>
                    </label>
                    <textarea class="textarea textarea-bordered w-full" readonly id="markdownEmbed"></textarea>
                    <button class="btn btn-xs btn-ghost mt-1" onclick="copyToClipboard('markdownEmbed')">Copy</button>
                </div>
                
                <div>
                    <label class="label">
                        <span class="label-text font-semibold">BBCode</span>
                    </label>
                    <textarea class="textarea textarea-bordered w-full" readonly id="bbcodeEmbed"></textarea>
                    <button class="btn btn-xs btn-ghost mt-1" onclick="copyToClipboard('bbcodeEmbed')">Copy</button>
                </div>
            </div>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn">Close</button>
                </form>
            </div>
        </div>
    </dialog>

    <script>
        function copyShareLink() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                showToast('Link copied to clipboard!');
            });
        }

        async function showEmbedCodes() {
            try {
                const response = await fetch('{{ route("images.public.embed", $image->unique_id) }}');
                const data = await response.json();
                
                document.getElementById('htmlEmbed').value = data.embed_codes.html;
                document.getElementById('markdownEmbed').value = data.embed_codes.markdown;
                document.getElementById('bbcodeEmbed').value = data.embed_codes.bbcode;
                
                document.getElementById('embedModal').showModal();
            } catch (error) {
                showToast('Failed to load embed codes', 'error');
            }
        }

        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            document.execCommand('copy');
            showToast('Copied to clipboard!');
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-top toast-center`;
            toast.innerHTML = `<div class="alert alert-${type}"><span>${message}</span></div>`;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 3000);
        }
    </script>
</body>
</html>