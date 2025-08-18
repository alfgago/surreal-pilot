<x-filament::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Bring Your Own API Keys</x-slot>
            <x-slot name="description">Studio plan feature: use your own model provider keys.</x-slot>

            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button wire:click="save">Save</x-filament::button>
            </div>
        </x-filament::section>
    </div>
</x-filament::page>

