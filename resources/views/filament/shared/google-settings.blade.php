<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Google Drive Integration
            </x-slot>

            <x-slot name="description">
                Manage your Google Account connection. To upload documents, your account must have Google Drive permissions.
            </x-slot>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-4">
                <div class="mb-4 sm:mb-0">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Google Drive Access
                    </h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        @if ($this->hasDriveAccess)
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-green-400" fill="currentColor" viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3" />
                                </svg>
                                Active
                            </span>
                            <span class="ml-2">Your account has the necessary Google Drive permissions.</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-200">
                                 <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-red-400" fill="currentColor" viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3" />
                                </svg>
                                Permission Required
                            </span>
                            <span class="ml-2">Your account is missing Google Drive permissions. Please re-authenticate.</span>
                        @endif
                    </p>
                </div>
                <div>
                    <x-filament::button
                        wire:click="redirectToGoogle"
                        icon="heroicon-m-lock-closed"
                        :color="$this->hasDriveAccess ? 'gray' : 'primary'"
                    >
                        @if ($this->hasDriveAccess)
                            Re-Authenticate
                        @else
                            Connect Google Drive
                        @endif
                    </x-filament::button>
                </div>
            </div>

        </x-filament::section>
    </div>
</x-filament-panels::page>