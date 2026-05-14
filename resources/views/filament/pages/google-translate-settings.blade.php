<x-filament::page>
    {{ $this->form }}

    <div>
        <x-filament::button wire:click="save">
            {{ __('admin.common.save') }}
        </x-filament::button>
    </div>
</x-filament::page>
