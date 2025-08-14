<?php

namespace App\Services;

use Lavary\Menu\Builder;
use Lavary\Menu\Item;
use Menu;

class MenuService
{
    public function __construct()
    {
        //左側
        Menu::make('left', function (Builder $menu) {
        });

        //右側
        Menu::make('right', function (Builder $menu) {

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
