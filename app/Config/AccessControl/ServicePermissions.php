<?php

return [
    'permission:create,services' => [
        'before' => [
            'admin/services/add_service',
            'admin/services/insert_service',
        ],
    ],
    'permission:update,services' => [
        'before' => [
            'admin/services/edit_service/*',
            'admin/services/update_service',
            'admin/services/approve_service',
            'admin/services/disapprove_service',
            'admin/services/remove_seo_image',
        ],
    ],
    'permission:read,services' => [
        'before' => [
            'admin/services',
            'admin/services/list',
            'admin/services/service_detail/*',
        ],
    ],
    'permission:delete,services' => [
        'before' => [
            'admin/services/delete_service',
        ],
    ],
];