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
                <div class="mb-6 sm:mb-0">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Google Drive Access
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        @if ($this->hasDriveAccess)
                            <span style="display: inline-flex; align-items: center; border-radius: 9999px; background-color: #DCFCE7; padding-left: 0.625rem; padding-right: 0.625rem; padding-top: 0.125rem; padding-bottom: 0.125rem; font-size: 0.75rem; font-weight: 500; color: #166534;">
                                <svg style="height: 0.5rem; width: 0.5rem; margin-right: 0.375rem; margin-left: -0.125rem; flex-shrink: 0; fill: #22C55E;" viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3" />
                                </svg>
                                Active
                            </span>
                            <span style="margin-left: 0.5rem;">Your account has the necessary Google Drive permissions.</span>
                        @else
                           <span style="display: inline-flex; align-items: center; border-radius: 9999px; background-color: #FEE2E2; padding-left: 0.625rem; padding-right: 0.625rem; padding-top: 0.125rem; padding-bottom: 0.125rem; font-size: 0.75rem; font-weight: 500; color: #991B1B;">
                                <svg style="height: 0.5rem; width: 0.5rem; margin-right: 0.375rem; margin-left: -0.125rem; flex-shrink: 0; fill: #EF4444;" viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3" />
                                </svg>
                                Permission Required
                            </span>
                            <span style="margin-left: 0.5rem;">Your account is missing Google Drive permissions. Please re-authenticate.</span>
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