<?php

return [
    'permission:create,sliders' => [
        'before' => [
            'admin/sliders/add_slider',
        ],
    ],
    'permission:update,sliders' => [
        'before' => [
            'admin/sliders/update_slider',
        ],
    ],
    'permission:read,sliders' => [
        'before' => [
            'admin/sliders',
            'admin/sliders/list',
        ],
    ],
    'permission:delete,sliders' => [
        'before' => [
            'admin/sliders/delete_sliders',
        ],
    ],
];