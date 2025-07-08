<div class="bg-white rounded-lg shadow-lg p-6">
    <h3 class="text-lg font-semibold mb-4">Edit Media Details</h3>
    
    <form wire:submit="save" class="space-y-4">
        {{-- Alt Text --}}
        <div>
            <x-mary-input 
                label="Alt Text" 
                wire:model="alt_text"
                placeholder="Descriptive text for accessibility"
                hint="Used for screen readers and SEO"
            />
        </div>

        {{-- Description --}}
        <div>
            <x-mary-textarea 
                label="Description" 
                wire:model="description"
                placeholder="Optional description of the media"
                rows="3"
            />
        </div>

        {{-- Tags --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
            
            {{-- Tag Input --}}
            <div class="flex gap-2 mb-3">
                <x-mary-input 
                    wire:model="new_tag"
                    placeholder="Add a tag"
                    class="flex-1"
                />
                <x-mary-button 
                    icon="o-plus" 
                    wire:click="addTag"
                    class="btn-primary"
                    type="button"
                />
            </div>
            
            {{-- Existing Tags --}}
            @if(!empty($tags))
                <div class="flex flex-wrap gap-2">
                    @foreach($tags as $index => $tag)
                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 text-sm rounded">
                            {{ $tag }}
                            <button 
                                type="button" 
                                wire:click="removeTag({{ $index }})"
                                class="text-blue-600 hover:text-blue-800"
                            >
                                <x-mary-icon name="o-x-mark" class="w-3 h-3" />
                            </button>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Sharing --}}
        <div>
            <x-mary-checkbox 
                label="Allow public sharing" 
                wire:model="is_shareable"
                hint="When enabled, this media can be shared publicly"
            />
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-3 pt-4">
            <x-mary-button 
                label="Cancel" 
                class="btn-outline"
                x-on:click="$wire.dispatch('close-edit-modal')"
                type="button"
            />
            <x-mary-button 
                label="Save Changes" 
                icon="o-check" 
                class="btn-primary"
                type="submit"
                wire:loading.attr="disabled"
                wire:loading.class="loading"
            />
        </div>
    </form>
</div>