<x-filament-widgets::widget>
    <div class="flex flex-col md:flex-row md:justify-end md:items-center gap-4 mb-4">
        <div class="w-full md:w-80">
            <x-filament::input.wrapper
                wire:target="selectedApplicationId"
            >
                <x-slot name="prefix">
                    {{ __('Filter by Application') }}
                </x-slot>
                <x-filament::input.select wire:model.live="selectedApplicationId">
                    @foreach($applicationOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </div>

    @if (($stats = $this->getStats()) && count($stats))
        <div class="mt-8">
            @if (count($stats) === 7)
                <div class="flex flex-col gap-6">
                    <div class="fi-wi-stats-overview-grid grid md:grid-cols-3 gap-6">
                        {{ $stats[0] }} {{-- Current Base Rank --}}
                        {{ $stats[2] }} {{-- Attainable Rank --}}
                        {{ $stats[1] }} {{-- Final Total Score --}}
                    </div>
                    
                    <div class="fi-wi-stats-overview-grid grid md:grid-cols-2 gap-6">
                        {{ $stats[3] }} {{-- KRA I: Instruction --}}
                        {{ $stats[4] }} {{-- KRA II: Research & Creative Works --}}
                    </div>

                    <div class="fi-wi-stats-overview-grid grid md:grid-cols-2 gap-6">
                        {{ $stats[5] }} {{-- KRA III: Extension Services --}}
                        {{ $stats[6] }} {{-- KRA IV: Professional Development --}}
                    </div>
                </div>
            @else
                <div class="fi-wi-stats-overview-grid grid md:grid-cols-1 gap-6">
                    @foreach ($stats as $stat)
                        {{ $stat }}
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</x-filament-widgets::widget>