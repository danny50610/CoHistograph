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
        //左側
        Menu::make('left', function (Builder $menu) {});

        //右側
        Menu::make('right', function (Builder $menu) {
            // 會員
            if (Auth::check()) {

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
