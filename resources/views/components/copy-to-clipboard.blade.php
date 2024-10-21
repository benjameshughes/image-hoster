<!-- copy-to-clipboard.blade.php -->
@props(['textToCopy', 'buttonText' => 'Copy to Clipboard', 'successMessage' => 'Copied to Clipboard', 'errorMessage' => 'Failed to copy'])


<div
        x-data="{
        showMsg: false,
        isSuccess: true,
        message: '',
        copyToClipboard() {
            navigator.clipboard.writeText('{{ $textToCopy }}')
                .then(() => {
                    this.isSuccess = true;
                    this.message = '{{ $successMessage }}';
                    this.showMsg = true;
                    setTimeout(() => this.showMsg = false, 2000);
                })
                .catch(err => {
                    console.error('Failed to copy: ', err);
                    this.isSuccess = false;
                    this.message = '{{ $errorMessage }}';
                    this.showMsg = true;
                    setTimeout(() => this.showMsg = false, 2000);
                });
        }
    }"
        class="relative"
>
    <button
            {{ $attributes->merge(["px-3 py-1 bg-red-600 text-white rounded-md hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"]) }}
            @click="copyToClipboard"
            aria-label="Copy to clipboard"
    >
        {{ $buttonText }}
    </button>

    <div
            x-show="showMsg"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-90"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-90"
            @click.away="showMsg = false"
            :class="{
            'bg-green-100 border-green-300': isSuccess,
            'bg-red-100 border-red-300': !isSuccess
        }"
            class="fixed bottom-3 right-3 z-20 max-w-sm overflow-hidden border rounded-lg shadow-lg"
            role="alert"
    >
        <p
                class="p-3 flex items-center justify-center"
                :class="{
                'text-green-600': isSuccess,
                'text-red-600': !isSuccess
            }"
        >
            <span x-text="message"></span>
        </p>
    </div>
</div>