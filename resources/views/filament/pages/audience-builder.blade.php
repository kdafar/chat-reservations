<x-filament::page>
    <div class="space-y-6">
        {{-- Filters --}}
        <x-filament::section>
            <x-slot name="heading">Filters</x-slot>

            <form wire:submit="applyFilters" class="fi-form">
                {{ $this->filtersForm }}

                <div class="mt-4 flex gap-3">
                    <x-filament::button type="submit">Apply Filters</x-filament::button>
                    <x-filament::button color="gray" type="button" wire:click="clearFilters">Clear</x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Results --}}
        <x-filament::section>
            <x-slot name="heading">
                Results ({{ $this->resultsCount }} found)
            </x-slot>

            @php
                $totalPages = max(1, (int) ceil(($this->resultsCount ?: 0) / max(1, $this->perPage)));
                $from = $this->resultsCount ? (($this->page - 1) * $this->perPage) + 1 : 0;
                $to = min($this->resultsCount, $this->page * $this->perPage);
            @endphp

            {{-- Top controls --}}
            <div class="mb-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-600">Per page</span>
                    <select wire:model="perPage"
                            class="block w-24 rounded-md border-gray-300 px-2 py-1 text-sm focus:border-primary-500 focus:ring-primary-500">
                        <option>10</option>
                        <option>25</option>
                        <option>50</option>
                        <option>100</option>
                    </select>
                </div>

                <div class="text-sm text-gray-600">
                    Page {{ $this->page }} of {{ $totalPages }}
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left">
                            <th class="px-4 py-2 font-medium">Phone</th>
                            <th class="px-4 py-2 font-medium">Bookings</th>
                            <th class="px-4 py-2 font-medium">Confirmed</th>
                            <th class="px-4 py-2 font-medium">Last Booking</th>
                            <th class="px-4 py-2 font-medium">Last Branch</th>
                            <th class="px-4 py-2 font-medium">Last Interaction</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->results as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">{{ $row['msisdn'] }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center rounded-md bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                        {{ $row['bookings_count'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center rounded-md bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                                        {{ $row['confirmed_count'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    {{ !empty($row['last_booking_at']) ? \Carbon\Carbon::parse($row['last_booking_at'])->format('Y-m-d H:i') : '—' }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ !empty($row['last_branch_id']) ? ($this->branchNames[$row['last_branch_id']] ?? '—') : '—' }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ !empty($row['last_interaction_at']) ? \Carbon\Carbon::parse($row['last_interaction_at'])->format('Y-m-d H:i') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500" colspan="6">
                                    No results. Try adjusting your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4 flex items-center justify-between">
                <div class="flex gap-2">
                    @if($this->page > 1)
                        <x-filament::button wire:click="$set('page', {{ $this->page - 1 }})">Previous</x-filament::button>
                    @else
                        <x-filament::button disabled>Previous</x-filament::button>
                    @endif

                    @if($this->page < $totalPages)
                        <x-filament::button wire:click="$set('page', {{ $this->page + 1 }})">Next</x-filament::button>
                    @else
                        <x-filament::button disabled>Next</x-filament::button>
                    @endif
                </div>

                <div class="text-sm text-gray-600">
                    Showing {{ $from }} – {{ $to }}
                </div>
            </div>

            {{-- Audience actions --}}
            <div class="mt-6 flex gap-3">
                <x-filament::button icon="heroicon-o-paper-airplane" wire:click="openAddForm">
                    Add Results to Campaign
                </x-filament::button>

                <x-filament::button icon="heroicon-o-sparkles" color="primary" wire:click="openCreateForm">
                    Create + Queue Campaign
                </x-filament::button>
            </div>

            {{-- Add to Campaign --}}
            @if($this->showAddForm)
                <div class="mt-6 rounded-lg border p-4">
                    <h3 class="mb-3 font-semibold">Add Results to Existing Campaign</h3>

                    <form wire:submit="submitAddForm" class="fi-form">
                        {{ $this->addToCampaignForm }}

                        <div class="mt-4 flex gap-3">
                            <x-filament::button type="submit">Add</x-filament::button>
                            <x-filament::button color="gray" type="button" wire:click="closeAddForm">Cancel</x-filament::button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Create Campaign --}}
            @if($this->showCreateForm)
                <div class="mt-6 rounded-lg border p-4">
                    <h3 class="mb-3 font-semibold">Create & Queue Campaign from Results</h3>

                    <form wire:submit="submitCreateForm" class="fi-form">
                        {{ $this->createCampaignForm }}

                        <div class="mt-4 flex gap-3">
                            <x-filament::button type="submit">Create & Queue</x-filament::button>
                            <x-filament::button color="gray" type="button" wire:click="closeCreateForm">Cancel</x-filament::button>
                        </div>
                    </form>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament::page>
