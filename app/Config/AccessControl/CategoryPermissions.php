<?php

return [
    'permission:create,categories' => [
        'before' => [
            'admin/category/add_category',
        ],
    ],
    'permission:update,categories' => [
        'before' => [
            'admin/category/update_category',
            'admin/categories/remove_seo_image',
        ],
    ],
    'permission:read,categories' => [
        'before' => [
            'admin/categories',
            'admin/categories/list',
        ],
    ],
    'permission:delete,categories' => [
        'before' => [
            'admin/category/remove_category',
        ],
    ],
];