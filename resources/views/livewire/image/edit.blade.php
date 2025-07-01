<div>
    <x-mary-form wire:submit="save">
        <div class="space-y-4">
            <!-- Alt Text -->
            <x-mary-input 
                label="{{ __('Alt Text') }}" 
                wire:model="alt_text" 
                placeholder="{{ __('Descriptive text for accessibility') }}"
                hint="{{ __('Describe what the image shows for screen readers') }}" />

            <!-- Description -->
            <x-mary-textarea 
                label="{{ __('Description') }}" 
                wire:model="description" 
                placeholder="{{ __('Detailed description of the image') }}"
                rows="3"
                hint="{{ __('Optional description for internal use') }}" />

            <!-- Tags -->
            <div>
                <label class="label">
                    <span class="label-text">{{ __('Tags') }}</span>
                </label>
                
                <!-- Existing Tags -->
                @if(count($tags) > 0)
                    <div class="flex flex-wrap gap-2 mb-2">
                        @foreach($tags as $index => $tag)
                            <div class="badge badge-outline gap-2">
                                {{ $tag }}
                                <button type="button" 
                                        wire:click="removeTag({{ $index }})"
                                        class="btn btn-xs btn-circle btn-ghost">
                                    <x-mary-icon name="o-x-mark" class="w-3 h-3" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
                
                <!-- Add New Tag -->
                <div class="flex gap-2">
                    <x-mary-input 
                        wire:model="new_tag" 
                        placeholder="{{ __('Add a tag') }}"
                        class="flex-1"
                        wire:keydown.enter="addTag" />
                    <x-mary-button 
                        type="button"
                        icon="o-plus" 
                        class="btn-outline"
                        wire:click="addTag" />
                </div>
                
                <div class="label">
                    <span class="label-text-alt">{{ __('Press Enter or click + to add. Max 10 tags.') }}</span>
                </div>
            </div>

            <!-- Sharing Toggle -->
            <x-mary-checkbox 
                label="{{ __('Allow public sharing') }}" 
                wire:model="is_shareable" 
                hint="{{ __('When enabled, this image can be viewed publicly via share links') }}" />

            @if($is_shareable && $image->unique_id)
                <x-mary-alert icon="o-information-circle" class="alert-info">
                    <span>{{ __('Public share URL:') }}</span>
                    <code class="text-xs">{{ $image->shareable_url }}</code>
                </x-mary-alert>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button 
                label="{{ __('Cancel') }}" 
                class="btn-ghost" 
                onclick="document.getElementById('edit-image-modal').close()" />
            
            <x-mary-button 
                label="{{ __('Save Changes') }}" 
                class="btn-primary" 
                type="submit" 
                spinner="save" />
        </x-slot:actions>
    </x-mary-form>
</div>