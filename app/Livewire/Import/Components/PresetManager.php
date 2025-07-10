<?php

declare(strict_types=1);

namespace App\Livewire\Import\Components;

use App\Models\ImportPreset;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

class PresetManager extends Component
{
    use Toast;

    public ?int $selectedPresetId = null;
    public bool $showPresetModal = false;
    
    #[Validate('required|string|min:3|max:255')]
    public string $newPresetName = '';
    
    #[Validate('nullable|string|max:500')]
    public string $newPresetDescription = '';
    
    // Current configuration to save as preset
    public array $currentConfiguration = [];

    public function mount(): void
    {
        // Load default preset if available
        $defaultPreset = ImportPreset::getDefaultForUser(Auth::id());
        if ($defaultPreset) {
            $this->selectedPresetId = $defaultPreset->id;
            $this->loadPreset($defaultPreset->id);
        }
    }

    public function loadPreset(?int $presetId): void
    {
        if (!$presetId) {
            $this->selectedPresetId = null;
            return;
        }
        
        $preset = ImportPreset::availableForUser(Auth::id())
            ->where('id', $presetId)
            ->first();
            
        if (!$preset) {
            $this->error(
                title: 'Preset Not Found',
                description: 'The selected preset could not be found.',
                position: 'toast-top toast-end'
            );
            return;
        }
        
        $config = $preset->getConfigWithDefaults();
        
        $this->selectedPresetId = $presetId;
        
        $this->info(
            title: 'Preset Loaded',
            description: "Applied settings from '{$preset->name}' preset.",
            position: 'toast-top toast-end'
        );

        // Emit configuration to parent components
        $this->dispatch('preset-loaded', [
            'presetId' => $presetId,
            'presetName' => $preset->name,
            'configuration' => $config,
        ]);
    }
    
    public function savePreset(): void
    {
        $this->validate([
            'newPresetName' => 'required|string|min:3|max:255',
            'newPresetDescription' => 'nullable|string|max:500',
        ]);

        if (empty($this->currentConfiguration)) {
            $this->error(
                title: 'No Configuration',
                description: 'Please configure your import settings before saving as a preset.',
                position: 'toast-top toast-end'
            );
            return;
        }
        
        try {
            $preset = Auth::user()->importPresets()->create([
                'name' => $this->newPresetName,
                'description' => $this->newPresetDescription ?: null,
                'config' => $this->currentConfiguration,
            ]);
            
            $this->selectedPresetId = $preset->id;
            
            $this->success(
                title: 'Preset Saved',
                description: "Import preset '{$this->newPresetName}' has been saved.",
                position: 'toast-top toast-end'
            );
            
            $this->reset(['showPresetModal', 'newPresetName', 'newPresetDescription']);
            
        } catch (\Exception $e) {
            $this->error(
                title: 'Save Failed',
                description: 'Failed to save preset: ' . $e->getMessage(),
                position: 'toast-top toast-end'
            );
        }
    }
    
    public function deletePreset(int $presetId): void
    {
        $preset = Auth::user()->importPresets()->find($presetId);
        
        if (!$preset) {
            $this->error(
                title: 'Preset Not Found',
                description: 'The preset could not be found.',
                position: 'toast-top toast-end'
            );
            return;
        }

        try {
            $presetName = $preset->name;
            $preset->delete();
            
            if ($this->selectedPresetId === $presetId) {
                $this->selectedPresetId = null;
            }
            
            $this->warning(
                title: 'Preset Deleted',
                description: "Import preset '{$presetName}' has been deleted.",
                position: 'toast-top toast-end'
            );
            
        } catch (\Exception $e) {
            $this->error(
                title: 'Delete Failed',
                description: 'Failed to delete preset: ' . $e->getMessage(),
                position: 'toast-top toast-end'
            );
        }
    }

    public function setAsDefault(int $presetId): void
    {
        $preset = Auth::user()->importPresets()->find($presetId);
        
        if (!$preset) {
            $this->error(
                title: 'Preset Not Found',
                description: 'The preset could not be found.',
                position: 'toast-top toast-end'
            );
            return;
        }

        try {
            $preset->setAsDefault();
            
            $this->success(
                title: 'Default Set',
                description: "'{$preset->name}' is now your default preset.",
                position: 'toast-top toast-end'
            );
            
        } catch (\Exception $e) {
            $this->error(
                title: 'Failed to Set Default',
                description: 'Failed to set default preset: ' . $e->getMessage(),
                position: 'toast-top toast-end'
            );
        }
    }

    public function updateCurrentConfiguration(array $configuration): void
    {
        $this->currentConfiguration = $configuration;
    }

    public function getAvailablePresetsProperty()
    {
        return ImportPreset::availableForUser(Auth::id())->get();
    }

    public function render()
    {
        return view('livewire.import.components.preset-manager');
    }
}