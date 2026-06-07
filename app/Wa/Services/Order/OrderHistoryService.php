<?php

namespace App\Wa\Services\Order;

use App\Wa\Models\Order;
use Illuminate\Support\Collection;

class OrderHistoryService
{
    /**
     * Fetches the most recent, unique orders for a customer from the local database.
     * Orders with the exact same items as a more recent order in the list are skipped.
     */
    // MODIFIED: Added the optional ?int $businessTypeId parameter
    public function fetchRecentOrders(string $customerPhone, int $limit = 5, ?int $businessTypeId = null): Collection
    {
        // 1. Eager load items to prevent N+1 query issues.
        $query = Order::with(['items', 'restaurant'])
            ->where('customer_phone_number', $customerPhone)
            ->where('status', 'completed')
            ->latest();

        // ADDED: Conditionally filter orders by the provided business type ID.
        if ($businessTypeId) {
            $query->whereHas('restaurant', function ($q) use ($businessTypeId) {
                $q->where('business_type_id', $businessTypeId);
            });
        }

        // Fetch a larger buffer to find unique orders from.
        $potentialOrders = $query->take($limit * 2)->get();

        $uniqueOrders = new Collection;
        $seenItemSignatures = [];

        foreach ($potentialOrders as $order) {
            // Stop once we have collected the number of unique orders requested.
            if ($uniqueOrders->count() >= $limit) {
                break;
            }

            // 2. Generate a unique signature for the order's items.
            $signature = $this->generateItemSignature($order->items);

            // 3. If we haven't seen this combination of items before, add the order to our results.
            if (! in_array($signature, $seenItemSignatures)) {
                $uniqueOrders->push($order);
                $seenItemSignatures[] = $signature;
            }
        }

        return $uniqueOrders;
    }

    /**
     * Generates a unique signature for a collection of order items.
     * This allows for comparison between orders to see if they contain the exact same items.
     * The signature is consistent regardless of the order of items or addons.
     *
     * @param  Collection  $items  The collection of OrderItem models.
     * @return string A unique hash representing the items.
     */
    private function generateItemSignature(Collection $items): string
    {
        if ($items->isEmpty()) {
            return '';
        }

        // Sort items by their ID to ensure the signature is the same
        // regardless of the order in which they were added.
        $sortedItems = $items->sortBy('item_id_from_restaurant');

        return $sortedItems->map(function ($item) {
            // Also sort addons to ensure consistency.
            $addons = $item->addons ?? [];
            if (is_array($addons)) {
                sort($addons);
            }

            // Create a consistent string for each item: "itemId:quantity:json_addons"
            return sprintf(
                '%s:%d:%s',
                $item->item_id_from_restaurant,
                $item->quantity,
                json_encode($addons)
            );
        })->implode('|'); // Join all item strings with a separator.
    }

    /**
     * Fetches a single past order with its items from the local database.
     */
    public function fetchOrderForReorder(string|int $orderId): ?Order
    {
        return Order::with(['items', 'restaurant'])->find($orderId);
    }
}
