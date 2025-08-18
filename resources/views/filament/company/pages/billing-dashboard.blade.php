<x-filament::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Current Plan</x-slot>
            <div class="text-sm text-slate-600">Plan: {{ $plan?->name ?? '—' }} ({{ $plan?->slug }})</div>
            <div class="text-sm text-slate-600">Monthly credits: {{ $plan?->monthly_credits }}</div>
            <div class="text-sm text-slate-600">Credits remaining: {{ $company?->credits }}</div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Manage Subscription</x-slot>
            <div class="flex gap-3">
                <form method="POST" action="/billing/checkout/subscription">
                    @csrf
                    <select name="plan_slug" class="fi-input rounded-md border">
                        <option value="starter">Starter ($5)</option>
                        <option value="pro">Pro ($25)</option>
                        <option value="studio">Studio ($50)</option>
                    </select>
                    <x-filament::button type="submit">Change Plan</x-filament::button>
                </form>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Top-up Credits</x-slot>
            <form method="POST" action="/billing/checkout/topup">
                @csrf
                <input type="hidden" name="credits" value="10000" />
                <x-filament::button type="submit">Buy 10k credits ($5)</x-filament::button>
            </form>
        </x-filament::section>
    </div>
</x-filament::page>

