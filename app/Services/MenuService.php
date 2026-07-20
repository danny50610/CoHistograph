<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Lavary\Menu\Builder;
use Lavary\Menu\Item;
use Menu;

class MenuService
{
    public function __construct()
    {
        // 左側
        Menu::make('left', function (Builder $menu) {});

        // 右側
        Menu::make('right', function (Builder $menu) {
            // 會員
            if (Auth::check()) {
                $user = Auth::user();

                if (! $user->hasVerifiedEmail()) {
                    $menu->add('信箱尚未驗證', ['route' => 'verification.notice'])
                        ->active('email/verify*')
                        ->data(['text-danger' => true]);
                }

                $adminMenu = null;
                $adminPermissions = [
                    'user.manage' => function (\Lavary\Menu\Item $adminMenu) {
                        $adminMenu->add('會員管理', ['route' => 'user.index'])->active('user/*');
                    },
                    'role.manage' => function (\Lavary\Menu\Item $adminMenu) {
                        $adminMenu->add('權限管理', ['route' => 'role.index'])->active('role/*');
                    },
                    'graph-schema.manage' => function (\Lavary\Menu\Item $adminMenu) {
                        $adminMenu->add('Graph Schema 管理', ['route' => 'graph-schema.vertex-type.index'])->active('graph-schema/*');
                    },
                    'revision.review' => function (\Lavary\Menu\Item $adminMenu) {
                        $adminMenu->add('修訂審核', ['route' => 'admin.revisions.index'])->active('admin/revisions*');
                    },
                ];
                foreach ($adminPermissions as $permission => $callback) {
                    if ($user->hasPermission($permission)) {
                        if (is_null($adminMenu)) {
                            $adminMenu = $menu->add('網站管理', 'javascript:void(0)');
                        }

                        $callback($adminMenu);
                    }
                }

                /** @var \Lavary\Menu\Item $userMenu */
                $userMenu = $menu->add($user->name, 'javascript:void(0)');
                // $userMenu->add('個人資料', ['route' => 'profile'])->active('profile/*');
                $userMenu->add('我的修訂', ['route' => 'revisions.index'])->active('revisions*');
                $userMenu->add('登出', ['route' => 'logout'])->data(['method' => 'POST']);
            } else {
                // 遊客
                $menu->add('登入', ['route' => 'login']);
            }
        });
    }

    protected function addDivider(Item $subMenu)
    {
        $lastItem = $subMenu->children()->last();
        if ($lastItem) {
            $lastItem->divide();
        }
    }
}
