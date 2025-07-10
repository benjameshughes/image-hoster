<div class="space-y-4">
    {{-- WordPress Connection Details --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-mary-input 
            label="WordPress Site URL" 
            wire:model="wordpressUrl"
            placeholder="https://your-wordpress-site.com"
            hint="Enter your WordPress site URL"
            icon="o-globe-alt"
        />
        
        <x-mary-input 
            label="Credential Name" 
            wire:model="credentialName"
            placeholder="e.g. My WordPress Site"
            hint="Give this credential set a memorable name"
            icon="o-tag"
        />
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-mary-input 
            label="Username" 
            wire:model="username"
            placeholder="your-username"
            icon="o-user"
        />
        
        <x-mary-input 
            label="Application Password" 
            wire:model="password"
            type="password"
            placeholder="xxxx xxxx xxxx xxxx"
            hint="Generate in WordPress: Users → Profile → Application Passwords"
            icon="o-key"
        />
    </div>
    
    {{-- Save Options --}}
    <div class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
        <x-mary-checkbox 
            label="Set as default credentials" 
            wire:model="setAsDefault"
        />
    </div>
    
    {{-- Save Button --}}
    <div class="flex justify-end">
        <x-mary-button 
            label="Save Credentials" 
            icon="o-bookmark"
            wire:click="saveCredential"
            class="btn-primary"
        />
    </div>
</div>