<?php

return [

    'clinic_item' => [
        'sections' => [
            'item_details' => 'Item Details',
            'inventory_units' => 'Inventory & Units',
            'inventory_units_description' => 'Configure how this item is stocked and consumed.',
            'pricing' => 'Pricing (Per Usage Unit)',
        ],
        'fields' => [
            'branch' => 'Branch',
            'type' => 'Type',
            'active' => 'Active',
            'name_en' => 'Name (EN)',
            'name_ar' => 'Name (AR)',
            'track_stock' => 'Track Stock',
            'stock_unit' => 'Stock Unit',
            'usage_unit' => 'Usage Unit (Base)',
            'conversion_factor' => 'Conversion Factor',
            'consume_step' => 'Consumption Step',
            'is_billable' => 'Billable to Patient',
            'default_cost' => 'Default Cost',
            'default_price' => 'Default Price',
            'name' => 'Name',
            'stock_q' => 'Stock?',
            'unit' => 'Unit',
            'cost' => 'Cost',
            'price' => 'Price',
        ],
        'placeholders' => [
            'stock_unit' => 'e.g. Box, Vial',
            'usage_unit' => 'e.g. Tablet, ml, Unit',
        ],
        'helpers' => [
            'branch' => 'Leave empty for a shared item usable in all branches.',
            'track_stock' => 'Enable strict inventory tracking for this consumable.',
            'stock_unit' => 'How you buy it from suppliers.',
            'usage_unit' => 'Doctors consume this unit. Cost/price are per this unit.',
            'conversion_factor' => 'How many Usage Units in 1 Stock Unit? (e.g. 1 Box = 50 Tablets)',
            'consume_step' => 'Recommended increment step for usage (e.g. 0.5 for ml).',
            'default_cost' => 'Cost per Usage Unit (e.g. cost per ml / unit).',
            'default_price' => 'Price per Usage Unit.',
        ],
        'types' => [
            'consumable' => 'Consumable',
            'service' => 'Service',
        ],
        'shared' => 'Shared',
        'filter_stockable' => 'Stockable',
    ],

    'clinic_item_stock' => [
        'sections' => [
            'stock_base' => 'Stock (Base Units)',
        ],
        'fields' => [
            'branch' => 'Branch',
            'clinic_item' => 'Clinic Item',
            'qty_on_hand_base' => 'Qty On Hand (Base)',
            'min_threshold_base' => 'Low Stock Threshold (Base)',
            'bin_location' => 'Bin Location',
            'item' => 'Item',
            'on_hand_base' => 'On Hand (Base)',
            'threshold' => 'Threshold',
            'bin' => 'Bin',
            'qty_stock_units' => 'Qty (Stock Units)',
            'qty_base' => 'Qty (Base Units)',
            'notes' => 'Notes',
        ],
        'helpers' => [
            'qty_stock_units' => 'Enter boxes/vials/bottles count. Will convert to base using conversion_factor.',
            'qty_base' => 'Alternatively enter base qty directly (ml/units/pcs).',
        ],
        'actions' => [
            'receive_stock' => 'Receive Stock',
        ],
        'notifications' => [
            'enter_qty' => 'Please enter Qty (Stock Units) or Qty (Base Units)',
            'received_success' => 'Stock received successfully',
        ],
    ],

    'clinic_package' => [
        'sections' => [
            'package' => 'Package',
            'package_items' => 'Package Items',
            'package_items_description' => 'Define required clinic items (base qty). These are used to build the stock request when doctor selects the package.',
        ],
        'fields' => [
            'branch' => 'Branch',
            'active' => 'Active',
            'name_en' => 'Name (EN)',
            'name_ar' => 'Name (AR)',
            'default_price' => 'Default Price',
            'clinic_item' => 'Clinic Item',
            'qty_base' => 'Qty (Base)',
            'consumable' => 'Consumable',
            'name' => 'Name',
            'price' => 'Price',
            'items' => 'Items',
        ],
        'helpers' => [
            'branch' => 'Leave empty to make it global (available for all branches).',
            'consumable' => 'If false, it is "non-consumable" and can be informational only.',
        ],
        'actions' => [
            'add_item' => 'Add item',
        ],
        'global' => 'Global',
    ],

    'clinic_stock_movement' => [
        'fields' => [
            'at' => 'At',
            'branch' => 'Branch',
            'item' => 'Item',
            'type' => 'Type',
            'delta_base' => 'Delta (Base)',
            'before' => 'Before',
            'after' => 'After',
            'by_user_id' => 'By (User ID)',
            'related_type' => 'Related Type',
            'related_id' => 'Related ID',
            'notes' => 'Notes',
        ],
        'types' => [
            'restock' => 'Restock',
            'consume' => 'Consume',
            'adjustment' => 'Adjustment',
        ],
    ],

    'visit_stock_request' => [
        'sections' => [
            'request' => 'Request',
        ],
        'fields' => [
            'visit' => 'Visit',
            'branch' => 'Branch',
            'requested_by' => 'Requested By',
            'fulfilled_by' => 'Fulfilled By',
            'fulfilled_at' => 'Fulfilled At',
            'items_availability' => 'Items & Availability',
            'req_by' => 'Req By',
            'time' => 'Time',
            'fulfillment_notes' => 'Fulfillment Notes',
            'resume_visit_status' => 'Resume Visit Status',
            'reason' => 'Reason',
        ],
        'statuses' => [
            'pending' => 'Pending',
            'fulfilled' => 'Fulfilled',
            'cancelled' => 'Cancelled',
        ],
        'resume_options' => [
            'awaiting_doctor' => 'Awaiting Doctor (Queue)',
            'in_progress' => 'In Progress (Room)',
        ],
        'helpers' => [
            'resume_status' => 'Where should the patient go after this stock arrives?',
        ],
        'actions' => [
            'fulfill' => 'Fulfill',
            'cancel' => 'Cancel',
        ],
        'notifications' => [
            'fulfilled_title' => 'Stock request fulfilled',
            'fulfilled_body' => 'Items consumed and visit updated.',
            'fulfill_failed_title' => 'Fulfillment failed',
        ],
        'empty_items' => 'No items',
        'visit_prefix' => 'Visit #',
    ],
];
