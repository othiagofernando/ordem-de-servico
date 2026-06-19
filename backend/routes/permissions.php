<?php

/**
 * Global Scope Permissions
 * Accessible at /admin routes
 */
return [
    'global' => [
        'view companies' => ['admin.companies.index', 'admin.companies.show'],
        'manage companies' => ['admin.companies.index', 'admin.companies.store', 'admin.companies.update'],
        'delete companies' => ['admin.companies.destroy'],

        'view subscriptions' => ['admin.subscriptions.index', 'admin.subscriptions.show'],
        'manage subscriptions' => ['admin.subscriptions.index', 'admin.subscriptions.update'],

        'view billing' => ['admin.billing.index', 'admin.billing.show'],
        'manage billing' => ['admin.billing.index', 'admin.billing.update'],

        'view plans' => ['admin.plans.index', 'admin.plans.show'],
        'manage plans' => ['admin.plans.index', 'admin.plans.store', 'admin.plans.update'],
        'delete plans' => ['admin.plans.destroy'],

        'view users' => ['admin.users.index', 'admin.users.show'],
        'create users' => ['admin.users.store'],
        'update users' => ['admin.users.update'],
        'delete users' => ['admin.users.destroy'],

        'manage roles' => ['admin.roles.index', 'admin.roles.store', 'admin.roles.update', 'admin.roles.destroy'],

        'view audit' => ['admin.audit.index', 'admin.audit.show'],
        'view alerts' => ['admin.alerts.index'],
    ],

    'tenant' => [
        'view members' => ['members.index', 'members.show'],
        'create members' => ['members.store'],
        'update members' => ['members.update'],
        'remove members' => ['members.destroy'],

        'manage roles' => ['company-roles.index', 'company-roles.store', 'company-roles.update', 'company-roles.destroy'],

        'view service orders' => ['service-orders.index', 'service-orders.show'],
        'create service orders' => ['service-orders.create', 'service-orders.store'],
        'edit service orders' => ['service-orders.update'],
        'delete service orders' => ['service-orders.destroy'],
        'assign service orders' => ['service-orders.assignment.update'],
        'diagnose service orders' => ['service-orders.diagnosis.update'],
        'execute service orders' => ['service-orders.execution.start', 'service-orders.execution.entries.store', 'service-orders.execution.complete'],
        'view financials' => ['service-orders.financials.show'],

        'view customers' => ['customers.index', 'customers.show'],
        'create customers' => ['customers.store'],
        'edit customers' => ['customers.update'],

        'view equipments' => ['equipments.index', 'equipments.show'],
        'create equipments' => ['equipments.store'],
        'edit equipments' => ['equipments.update'],

        'view parts' => ['parts.index', 'parts.show'],
        'create parts' => ['parts.store'],
        'edit parts' => ['parts.update'],
        'delete parts' => ['parts.destroy'],

        'view settings' => ['settings.show'],
        'manage settings' => ['settings.update'],

        'view audit' => ['audit.index'],
    ],
];
