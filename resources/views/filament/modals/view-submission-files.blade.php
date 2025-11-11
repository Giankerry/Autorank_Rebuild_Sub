@props([
    'submissionData' => [],
    'fileViewRoutes' => [],
    'formattingMap' => [],
])

@php
    $details = [];
    foreach ($submissionData as $key => $value) {
        $details[(string) Str::of($key)->replace('_', ' ')->title()] = $value;
    }
@endphp

<style>
    .loader {
        border: 4px solid #94949446;
        border-top: 4px solid #ffffffc0;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    @media (max-width: 768px) {
        .modal-container {
            flex-direction: column !important;
            height: auto !important;
        }
        .details-column, .viewer-column {
            width: 100% !important;
            height: auto !important;
        }
        .iframe-container {
            margin-top: 20px; 
            height: 70vh !important;
        }
    }
</style>

<div
    x-data="{
        currentFileUrl: '{{ array_values($fileViewRoutes)[0] ?? '' }}',
        isLoading: true,
        
        loadFile(url) {
            if (this.currentFileUrl !== url) {
                this.isLoading = true;
                this.currentFileUrl = url;
            }
        },
    }"
    x-init="$watch('currentFileUrl', () => { if(currentFileUrl) isLoading = true; })"
    class="flex flex-row gap-x-4 modal-container"
>

    <section 
        class="fi-section rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 flex flex-col details-column"
        style="width: 20%; height: 80vh;"
    >
        <div class="fi-section-header flex w-full items-center justify-between gap-x-3 px-4 py-3">
            <div class="fi-section-header-heading">
                <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    Submission Details
                </h3>
            </div>
        </div>

        <div class="fi-section-content border-t border-gray-200 p-4 dark:border-gray-700 flex-grow overflow-y-auto">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-3">
                @foreach($details as $key => $value)
                    <div class="col-span-1">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ $key }}
                        </dt>
                        <dd class="text-sm text-gray-900 dark:text-white">
                            @php
                                $displayValue = $value;
                                
                                if (isset($formattingMap[$key])) {
                                    $rule = $formattingMap[$key];
                                    
                                    if (is_array($rule)) {
                                        $displayValue = $rule[$value] ?? $value;
                                    } elseif (is_string($rule)) {
                                        try {
                                            $displayValue = \Carbon\Carbon::parse($value)->format($rule);
                                        } catch (Exception $e) {
                                            $displayValue = $value;
                                        }
                                    }
                                }
                            @endphp
                            {{ $displayValue }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <div 
        class="flex flex-col space-y-4 viewer-column"
        style="width: 80%;"
    >

        @if (count($fileViewRoutes) > 1)
            <div class="fi-tabs border-b border-gray-300 dark:border-gray-600">
                <nav class="fi-tabs-nav -mb-px flex space-x-4" aria-label="Tabs">
                    @foreach ($fileViewRoutes as $label => $url)
                        <button
                            type="button"
                            @click="loadFile('{{ $url }}')"
                            :class="currentFileUrl === '{{ $url }}'
                                ? 'fi-tabs-item-active border-primary-500 text-primary-600'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-300'"
                            class="fi-tabs-item whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>
            </div>
        @endif

        <div
            class="relative w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 flex-grow iframe-container"
            style="max-height: 80vh;"
        >
            <div 
                x-show="isLoading" 
                class="absolute inset-0 z-10 flex items-center justify-center bg-white/70 dark:bg-gray-900/70"
            >
                <div class="loader"></div>
            </div>
            
            <iframe
                :src="currentFileUrl"
                class="h-full w-full rounded-lg"
                frameborder="0"
                allow="autoplay"
                x-show="!isLoading" 
                @load="isLoading = false"
            ></iframe>

            <div
                x-show="!currentFileUrl"
                class="absolute inset-0 flex items-center justify-center"
            >
                <span class="text-gray-500">No file selected or available.</span>
            </div>
        </div>

    </div>
</div>