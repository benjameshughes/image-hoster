@props(['icon' => 'x-icon-clipboard'])
<div x-data="{
    copyToClipboard() {
        const text = $refs.text.innerText;
        navigator.clipboard.writeText(text)
            .then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
                $dispatch('textCopied');
            })
            .catch(err => {
                console.error('Failed to copy text: ', err);
            });
    },
    copied: false
}">
    <flux:tooltip content="Click to copy url to clipboard">
        <div class="flex gap-2 cursor-pointer" @click="copyToClipboard()">
            <flux:text x-ref="text" tooltip="Copy to clipboard">{{$slot}}</flux:text>
            <flux:icon.clipboard-copy variant="mini"/>
        </div>
    </flux:tooltip>
</div>