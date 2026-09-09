<?php

return [
    'permission:read,handyman' => [
        'before' => [
            'admin/handymen',
            'admin/handymen/list',
            'admin/partners/partner_handyman_list/*',
            'admin/partners/partner_handyman_list_data/*',
        ],
    ],
    'permission:update,handyman' => [
        'before' => [
            'admin/handymen/toggle-status',
            'admin/partners/partner_handyman_toggle_status',
        ],
    ],
];
