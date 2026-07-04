<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}

        <div class="fi-form-actions mt-6 flex items-center gap-3">
            <x-filament::button type="submit" size="md">
                {{ __('panel.tmpl_save') }}
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
