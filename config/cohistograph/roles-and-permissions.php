<?php

return [
    'roles' => [
        'admin' => [
            'display_name' => '管理員',
            'description' => '擁有所有權限的管理員角色',
            'permissions' => [
                'user.manage',
                'role.manage',
            ],
        ]
    ],
    'permissions' => [
        'user.manage' => [
            'display_name' => '管理會員',
            'description' => '修改會員資料、調整會員權限、刪除會員等',
        ],
        'role.manage' => [
            'display_name' => '管理角色',
            'description' => '新增、修改、刪除角色',
        ],
    ],
];
