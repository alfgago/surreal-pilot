<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Credit Management
        </x-slot>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Current Balance: {{ number_format($credits) }} credits
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Plan: {{ ucfirst($plan) }}
                    </p>
                </div>
                
                @if($isLowCredits)
                    <div class="flex items-center space-x-2">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-500" />
                        <span class="text-sm text-amber-600 dark:text-amber-400">Low credits</span>
                    </div>
                @endif
            </div>

            @if($isApproachingLimit)
                <div class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                    <div class="flex items-center">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-500 mr-2" />
                        <span class="text-sm text-amber-700 dark:text-amber-300">
                            You're approaching your monthly credit limit
                        </span>
                    </div>
                </div>
            @endif

            <div class="flex space-x-3">
                {{ $this->purchaseCreditsAction }}
                {{ $this->upgradeSubscriptionAction }}
            </div>

            <div class="text-xs text-gray-500 dark:text-gray-400">
                <p>• Credits are used for AI API requests</p>
                <p>• Unused credits roll over to the next month</p>
                <p>• Upgrade your plan for better rates and higher limits</p>
            </div>
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>