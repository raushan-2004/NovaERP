<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use Illuminate\Support\Facades\DB;

class NumberSeriesService
{
    /**
     * Generate the next sequential document number for the given prefix.
     *
     * Uses a lock on the number_series row to guarantee collision-safe sequential
     * numbers even under concurrent requests.
     *
     * @return string  e.g. "PR-2026-00001"
     */
    public function next(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {
            $year = (int) date('Y');

            // Lock the series row (creates it if missing via insertOrIgnore first)
            DB::table('number_series')->insertOrIgnore([
                'prefix'         => $prefix,
                'year'           => $year,
                'last_sequence'  => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $series = DB::table('number_series')
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $next = $series->last_sequence + 1;

            DB::table('number_series')
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->update([
                    'last_sequence' => $next,
                    'updated_at'    => now(),
                ]);

            return sprintf('%s-%d-%05d', $prefix, $year, $next);
        });
    }
}
