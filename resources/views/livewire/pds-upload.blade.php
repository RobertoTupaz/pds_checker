<section class="w-full max-w-3xl mx-auto space-y-6">
    <div>
        <flux:heading size="xl">{{ __('PDS Checker') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Upload an applicant\'s Personal Data Sheet (CS Form No. 212) to check it for missing or inconsistent entries.') }}</flux:text>
    </div>

    <flux:card class="space-y-4">
        <div class="space-y-4">
            <flux:input type="file" wire:model="pdsFile" accept=".xlsx" :label="__('PDS Excel file (.xlsx)')" />
            <flux:error name="pdsFile" />

            <div class="flex items-center gap-4">
                <flux:button type="button" wire:click="parse" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="parse">
                    {{ __('Upload and parse') }}
                </flux:button>
                <flux:text wire:loading wire:target="parse">{{ __('Processing...') }}</flux:text>
            </div>
        </div>
    </flux:card>

    @if ($uploadError)
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('Could not process this file')" :text="$uploadError" />
    @endif

    @if ($hasParsed)
        <flux:callout color="blue" icon="information-circle" :heading="__('File parsed successfully')" :text="__('See the results below.')" />

        @if (count($issues))
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Issues found (:count)', ['count' => count($issues)]) }}</flux:heading>
                <flux:text>{{ __('The following entries need to be corrected before this PDS can be certified as complete.') }}</flux:text>

                <ul class="space-y-3">
                    @foreach ($issues as $issue)
                        <li class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950">
                            <flux:icon.exclamation-circle variant="mini" class="mt-0.5 shrink-0 text-red-500" />
                            <div>
                                <flux:text class="font-medium text-red-800 dark:text-red-300">{{ $issue['section'] }} &mdash; {{ $issue['field'] }}</flux:text>
                                <flux:text class="text-red-700 dark:text-red-400">{{ $issue['message'] }}</flux:text>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </flux:card>
        @else
            <flux:card class="space-y-4 text-center">
                <flux:icon.check-circle variant="outline" class="mx-auto size-10 text-green-600" />
                <flux:heading size="lg">{{ __('No issues found') }}</flux:heading>
                <flux:text>{{ __('This PDS is complete and internally consistent. You can now generate a certification.') }}</flux:text>

                <div>
                    <flux:button wire:click="downloadCertification" variant="filled" color="green" icon="check-badge">
                        {{ __('Complete') }}
                    </flux:button>
                </div>
            </flux:card>
        @endif
    @endif
</section>
