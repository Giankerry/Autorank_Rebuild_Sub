<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        <div class="col-span-1 space-y-6 lg:col-span-2">
            
            <x-filament::tabs>
                <x-filament::tabs.item
                    :active="$activeTab === 'kra1'"
                    wire:click="$set('activeTab', 'kra1')"
                    icon="heroicon-o-academic-cap"
                >
                    KRA 1
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'kra2'"
                    wire:click="$set('activeTab', 'kra2')"
                    icon="heroicon-o-beaker"
                >
                    KRA 2
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'kra3'"
                    wire:click="$set('activeTab', 'kra3')"
                    icon="heroicon-o-globe-alt"
                >
                    KRA 3
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'kra4'"
                    wire:click="$set('activeTab', 'kra4')"
                    icon="heroicon-o-briefcase"
                >
                    KRA 4
                </x-filament::tabs.item>
            </x-filament::tabs>

            {{-- Nested Tab Content --}}
            <div class="space-y-6">
                
                {{-- KRA 1 Content --}}
                <div @class(['space-y-6', 'hidden' => $activeTab !== 'kra1'])>
                    <x-filament::tabs>
                        @foreach($this->getKra1Widgets() as $criterionKey => $widgets)
                            <x-filament::tabs.item
                                :active="$activeKra1Tab === $criterionKey"
                                wire:click="$set('activeKra1Tab', '{{ $criterionKey }}')"
                            >
                                {{ (string) Str::of($criterionKey)->after('Criterion ') }}
                            </x-filament::tabs.item>
                        @endforeach
                    </x-filament::tabs>
                    
                    @foreach($this->getKra1Widgets() as $criterionKey => $widgets)
                        <div @class(['space-y-6', 'hidden' => $activeKra1Tab !== $criterionKey])>
                            <x-filament-widgets::widgets
                                :widgets="$widgets"
                                :data="$this->getWidgetData()"
                            />
                        </div>
                    @endforeach
                </div>

                {{-- KRA 2 Content --}}
                <div @class(['space-y-6', 'hidden' => $activeTab !== 'kra2'])>
                    <x-filament::tabs>
                        @foreach($this->getKra2Widgets() as $criterionKey => $widgets)
                            <x-filament::tabs.item
                                :active="$activeKra2Tab === $criterionKey"
                                wire:click="$set('activeKra2Tab', '{{ $criterionKey }}')"
                            >
                                {{ (string) Str::of($criterionKey)->after('Criterion ') }}
                            </x-filament::tabs.item>
                        @endforeach
                    </x-filament::tabs>

                    @foreach($this->getKra2Widgets() as $criterionKey => $widgets)
                        <div @class(['space-y-6', 'hidden' => $activeKra2Tab !== $criterionKey])>
                            <x-filament-widgets::widgets
                                :widgets="$widgets"
                                :data="$this->getWidgetData()"
                            />
                        </div>
                    @endforeach
                </div>

                {{-- KRA 3 Content --}}
                <div @class(['space-y-6', 'hidden' => $activeTab !== 'kra3'])>
                    <x-filament::tabs>
                        @foreach($this->getKra3Widgets() as $criterionKey => $widgets)
                            <x-filament::tabs.item
                                :active="$activeKra3Tab === $criterionKey"
                                wire:click="$set('activeKra3Tab', '{{ $criterionKey }}')"
                            >
                                {{ (string) Str::of($criterionKey)->after('Criterion ') }}
                            </x-filament::tabs.item>
                        @endforeach
                    </x-filament::tabs>

                    @foreach($this->getKra3Widgets() as $criterionKey => $widgets)
                        <div @class(['space-y-6', 'hidden' => $activeKra3Tab !== $criterionKey])>
                            <x-filament-widgets::widgets
                                :widgets="$widgets"
                                :data="$this->getWidgetData()"
                            />
                        </div>
                    @endforeach
                </div>

                {{-- KRA 4 Content --}}
                <div @class(['space-y-6', 'hidden' => $activeTab !== 'kra4'])>
                    <x-filament::tabs>
                        @foreach($this->getKra4Widgets() as $criterionKey => $widgets)
                            <x-filament::tabs.item
                                :active="$activeKra4Tab === $criterionKey"
                                wire:click="$set('activeKra4Tab', '{{ $criterionKey }}')"
                            >
                                {{ (string) Str::of($criterionKey)->after('Criterion ') }}
                            </x-filament::tabs.item>
                        @endforeach
                    </x-filament::tabs>
                    
                    @foreach($this->getKra4Widgets() as $criterionKey => $widgets)
                        <div @class(['space-y-6', 'hidden' => $activeKra4Tab !== $criterionKey])>
                            <x-filament-widgets::widgets
                                :widgets="$widgets"
                                :data="$this->getWidgetData()"
                            />
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        <div class="col-span-1">
            <div class="sticky top-0">
                <x-filament::section>
                    <x-slot name="heading">
                        Validation Actions
                    </x-slot>

                    <form wire:submit="save">
                        {{ $this->form }}
                    </form>
                </x-filament::section>
            </div>
        </div>

    </div>
</x-filament-panels::page>