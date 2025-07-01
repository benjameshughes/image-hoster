<?php

namespace App\Livewire\Image;

use App\Models\Image;
use Livewire\Component;
use Mary\Traits\Toast;

class Edit extends Component
{
    use Toast;

    public Image $image;
    
    public string $alt_text = '';
    public string $description = '';
    public array $tags = [];
    public bool $is_shareable = true;
    public string $new_tag = '';

    public function mount(Image $image)
    {
        $this->image = $image;
        $this->alt_text = $image->alt_text ?? '';
        $this->description = $image->description ?? '';
        $this->tags = $image->tags ?? [];
        $this->is_shareable = $image->is_shareable;
    }

    public function addTag()
    {
        if (empty(trim($this->new_tag))) {
            return;
        }

        $tag = trim($this->new_tag);
        
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }
        
        $this->new_tag = '';
    }

    public function removeTag($index)
    {
        unset($this->tags[$index]);
        $this->tags = array_values($this->tags);
    }

    public function save()
    {
        $this->validate([
            'alt_text' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'tags' => 'array|max:10',
            'tags.*' => 'string|max:50',
            'is_shareable' => 'boolean',
        ]);

        $this->image->update([
            'alt_text' => $this->alt_text ?: null,
            'description' => $this->description ?: null,
            'tags' => $this->tags,
            'is_shareable' => $this->is_shareable,
        ]);

        $this->success('Image details updated successfully!');
        
        $this->dispatch('close-edit-modal');
        $this->dispatch('refresh-page');
    }

    public function render()
    {
        return view('livewire.image.edit');
    }
}