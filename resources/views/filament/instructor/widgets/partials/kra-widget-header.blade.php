@if(!$validation_mode)
<div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4 mb-4">
    <div class="flex-grow">
        <x-filament::input.wrapper
            wire:target="selectedApplicationId"
        >
            <x-slot name="prefix">
                {{ __('Filter by Application') }}
            </x-slot>
            <x-filament::input.select wire:model.live="selectedApplicationId">
                @foreach($this->applicationOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
    <div>
        {{ $this->createApplicationAction }}
    </div>
</div>
@endif