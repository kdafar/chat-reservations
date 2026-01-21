<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold">Nurse Station</h2>
                <p class="text-sm text-gray-500">
                    Today’s visits. Open a visit to record notes/vitals, add items used, and sync follow-up.
                </p>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
