<?php

namespace App\Services;

use App\Models\RestaurantTable;

class TableAllocatorService
{
    public function allocate(int $branchId, int $partySize): ?RestaurantTable
    {
        // Smallest available table that fits the party
        return RestaurantTable::where('branch_id', $branchId)
            ->where('status', 'available')
            ->where('capacity', '>=', $partySize)
            ->orderBy('capacity', 'asc')
            ->lockForUpdate()
            ->first();
    }
}
