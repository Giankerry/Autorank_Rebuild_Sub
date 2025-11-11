<x-filament-widgets::widget>
    <x-filament::card>

        @include('filament.instructor.widgets.partials.kra-widget-header')

        <x-filament::tabs wire:model.live="activeTable" class="mb-4">
            <x-filament::tabs.item
                wire:click="$set('activeTable', 'sole_authorship')"
                :active="$activeTable === 'sole_authorship'"
                icon="heroicon-o-user">
                Sole Authorship
            </x-filament::tabs.item>

            <x-filament::tabs.item
                wire:click="$set('activeTable', 'co_authorship')"
                :active="$activeTable === 'co_authorship'"
                icon="heroicon-o-users">
                Co-Authorship
            </x-filament::tabs.item>
        </x-filament::tabs>

        {{ $this->table }}
    </x-filament::card>
</x-filament-widgets::widget>