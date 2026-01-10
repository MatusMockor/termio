<?php

declare(strict_types=1);

namespace App\Services\Shared;

use Illuminate\Database\Eloquent\Model;

final class SortOrderService
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int>  $order
     */
    public function reorder(string $modelClass, array $order): void
    {
        foreach ($order as $position => $id) {
            $modelClass::where('id', $id)->update(['sort_order' => $position]);
        }
    }
}
