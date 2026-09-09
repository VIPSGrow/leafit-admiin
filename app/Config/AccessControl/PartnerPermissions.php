<?php

return [
    'permission:create,partner' => [
        'before' => [
            'admin/partners/add_partner',
            'admin/partner/insert_partner',
        ],
    ],
    'permission:update,partner' => [
        'before' => [
            'admin/partner/deactivate_partner',
            'admin/partner/activate_partner',
            'admin/partner/approve_partner',
            'admin/partner/disapprove_partner',
        ],
    ],
    'permission:read,partner' => [
        'before' => [
            'admin/partners/provider_leaves',
            'admin/partners/provider_leaves_list',
            'admin/partners/provider_leaves_calendar',
        ],
    ],
    'permission:delete,partner' => [
        'before' => [
            'admin/partner/delete_partner'
        ],
    ],
];