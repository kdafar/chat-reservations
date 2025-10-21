<?php

return [

    'models' => [
        // Change this from App\Models\Permission::class
        'permission' => Spatie\Permission\Models\Permission::class,

        // Change this from App\Models\Role::class
        'role' => Spatie\Permission\Models\Role::class,
    ],

    'Users' => [
        'users.view-any',
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
    ],

    'Roles' => [
        'roles.view-any',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'roles.assign-permissions',
    ],

    'Partners' => [
        'partners.view-any',
        'partners.view',
        'partners.create',
        'partners.update',
        'partners.delete',
    ],

    'Branches' => [
        'branches.view-any',
        'branches.view',
        'branches.create',
        'branches.update',
        'branches.delete',
        'branches.manage-hours',
        'branches.manage-coverage',
    ],

    'Menus' => [
        'menus.view-any',
        'menus.view',
        'menus.create',
        'menus.update',
        'menus.delete',
        'menu-sections.manage',
        'menu-items.manage',
        'modifiers.manage',
    ],

    'Orders' => [
        'orders.view-any',
        'orders.view',
        'orders.update-status',
        'orders.cancel',
        'orders.export',
    ],

    'Payments' => [
        'payments.view-any',
        'payments.view',
        'payments.refund',
        'payments.capture',
    ],

    'Gateways' => [
        'gateways.view-any',
        'gateways.view',
        'gateways.create',
        'gateways.update',
        'gateways.delete',
        'gateway-accounts.manage',
    ],

    'Homepage' => [
        'homepage.manage',
    ],
];
