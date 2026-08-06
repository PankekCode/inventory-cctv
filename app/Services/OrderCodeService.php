<?php

namespace App\Services;

use App\Models\OrderCounter;

class OrderCodeService
{
    public function next(): string
    {
        $year = (int) now()->format('Y');

        $counter = OrderCounter::firstOrCreate(
            ['year' => $year],
            ['last_number' => 0],
        );

        $counter = OrderCounter::whereKey($counter->id)->lockForUpdate()->firstOrFail();
        $counter->increment('last_number');
        $counter->refresh();

        return sprintf(
            '%s-%d-%04d',
            config('commerce.order_prefix'),
            $year,
            $counter->last_number,
        );
    }
}
