<x-filament-widgets::widget>
    <x-filament::section>
        @if ($isActivated)
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div class="flex-1">
                    <h2 class="text-lg font-semibold tracking-tight text-gray-950 dark:text-white">
                        Let's Get Started
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Your profile is fully configured. You can start a new application whenever you're ready.
                    </p>
                </div>
                
                <div class="flex-shrink-0">
                    {{ $this->startApplicationAction }}
                </div>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div class="flex-1">
                    <h2 class="text-lg font-semibold tracking-tight text-gray-950 dark:text-white">
                        Welcome to AutoRank!
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Your account is awaiting activation. An Admin must set your official faculty rank before you can submit an application.
                    </p>
                </div>
                
                <div class="flex-shrink-0">
                    {{ $this->notifyAdminAction }}
                </div>
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>