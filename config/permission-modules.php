<?php

return [
    'modules' => [
        'catalog' => [
            'label' => 'Product Catalog',
            'description' => 'Maintain the product master, pricing, and categories.',
            'icon' => 'inventory_2',
            'permissions' => [
                'view' => ['product.read'],
                'write' => ['product.create', 'product.update'],
                'delete' => ['product.delete'],
            ],
        ],
        'sales' => [
            'label' => 'Sales & POS',
            'description' => 'Run POS transactions, refunds, and adjustments.',
            'icon' => 'point_of_sale',
            'permissions' => [
                'view' => ['sale.read'],
                'write' => ['sale.create', 'sale.refund', 'sale.void'],
                'delete' => [],
            ],
        ],
        'inventory' => [
            'label' => 'Inventory',
            'description' => 'Track stock counts, adjustments, and transfers.',
            'icon' => 'inventory',
            'permissions' => [
                'view' => ['inventory.read'],
                'write' => ['inventory.create', 'inventory.update', 'inventory.adjust'],
                'delete' => [],
            ],
        ],
        'customers' => [
            'label' => 'Customers & Loyalty',
            'description' => 'Manage customer profiles and loyalty records.',
            'icon' => 'groups',
            'permissions' => [
                'view' => ['customer.read', 'customer.loyalty.read'],
                'write' => ['customer.create', 'customer.update', 'customer.loyalty.record'],
                'delete' => ['customer.delete'],
            ],
        ],
        'reports' => [
            'label' => 'Reports',
            'description' => 'Sales, inventory, customer, and finance dashboards.',
            'icon' => 'insights',
            'permissions' => [
                'view' => [
                    'report.sales',
                    'report.inventory',
                    'report.customers',
                    'report.financial',
                ],
                'write' => [],
                'delete' => [],
            ],
        ],
        'settings' => [
            'label' => 'Settings',
            'description' => 'Configure tenant-wide preferences and integrations.',
            'icon' => 'settings',
            'permissions' => [
                'view' => ['settings.read'],
                'write' => ['settings.update'],
                'delete' => [],
            ],
        ],
        'user_management' => [
            'label' => 'User & Role Management',
            'description' => 'Invite users, assign sites, and configure roles.',
            'icon' => 'admin_panel_settings',
            'permissions' => [
                'view' => ['user.management'],
                'write' => ['user.management'],
                'delete' => [],
            ],
        ],
    ],

    'permission_definitions' => [
        'product.create' => [
            'name' => 'Create Products',
            'module' => 'products',
            'description' => 'Add new items to the catalog.',
        ],
        'product.read' => [
            'name' => 'View Products',
            'module' => 'products',
            'description' => 'View product lists and details.',
        ],
        'product.update' => [
            'name' => 'Update Products',
            'module' => 'products',
            'description' => 'Edit existing product information.',
        ],
        'product.delete' => [
            'name' => 'Delete Products',
            'module' => 'products',
            'description' => 'Archive or remove products from catalog.',
        ],
        'sale.create' => [
            'name' => 'Create Sales',
            'module' => 'sales',
            'description' => 'Complete POS transactions and invoices.',
        ],
        'sale.read' => [
            'name' => 'View Sales',
            'module' => 'sales',
            'description' => 'View completed transactions and receipts.',
        ],
        'sale.refund' => [
            'name' => 'Process Refunds',
            'module' => 'sales',
            'description' => 'Issue refunds for prior transactions.',
        ],
        'sale.void' => [
            'name' => 'Void Sales',
            'module' => 'sales',
            'description' => 'Cancel pending or erroneous sales.',
        ],
        'inventory.create' => [
            'name' => 'Create Inventory Records',
            'module' => 'inventory',
            'description' => 'Receive stock and create adjustments.',
        ],
        'inventory.read' => [
            'name' => 'View Inventory',
            'module' => 'inventory',
            'description' => 'View current stock levels and valuation.',
        ],
        'inventory.update' => [
            'name' => 'Update Inventory',
            'module' => 'inventory',
            'description' => 'Edit SKU attributes that affect stock.',
        ],
        'inventory.adjust' => [
            'name' => 'Adjust Inventory',
            'module' => 'inventory',
            'description' => 'Perform manual adjustments for shrinkage, etc.',
        ],
        'customer.create' => [
            'name' => 'Create Customers',
            'module' => 'customers',
            'description' => 'Add new customer profiles.',
        ],
        'customer.read' => [
            'name' => 'View Customers',
            'module' => 'customers',
            'description' => 'View customer lists and insights.',
        ],
        'customer.update' => [
            'name' => 'Update Customers',
            'module' => 'customers',
            'description' => 'Edit customer information.',
        ],
        'customer.delete' => [
            'name' => 'Delete Customers',
            'module' => 'customers',
            'description' => 'Archive or remove customers.',
        ],
        'customer.loyalty.read' => [
            'name' => 'View Customer Loyalty',
            'module' => 'customers',
            'description' => 'See loyalty balances and history.',
        ],
        'customer.loyalty.record' => [
            'name' => 'Record Loyalty Transactions',
            'module' => 'customers',
            'description' => 'Issue or redeem loyalty points.',
        ],
        'report.sales' => [
            'name' => 'Access Sales Reports',
            'module' => 'reports',
            'description' => 'View sales KPIs and dashboards.',
        ],
        'report.inventory' => [
            'name' => 'Access Inventory Reports',
            'module' => 'reports',
            'description' => 'Analyze stock performance and turns.',
        ],
        'report.customers' => [
            'name' => 'Access Customer Reports',
            'module' => 'reports',
            'description' => 'Customer cohort and behavior insights.',
        ],
        'report.financial' => [
            'name' => 'Access Financial Reports',
            'module' => 'reports',
            'description' => 'High-level P&L, tax, and ledger reporting.',
        ],
        'settings.read' => [
            'name' => 'View Settings',
            'module' => 'settings',
            'description' => 'Read tenant configuration.',
        ],
        'settings.update' => [
            'name' => 'Update Settings',
            'module' => 'settings',
            'description' => 'Modify configuration and integrations.',
        ],
        'user.management' => [
            'name' => 'Manage Users',
            'module' => 'settings',
            'description' => 'Invite, edit, and deactivate users.',
        ],
    ],
];
