<x-filament::page>
    {{ $this->form }}

    <div>
        <x-filament::button wire:click="save" >
            Сохранить
        </x-filament::button>
    </div>
</x-filament::page>
