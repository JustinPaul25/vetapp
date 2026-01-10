<?php

namespace App\Traits;

use Carbon\Carbon;

trait HasDateFiltering
{
    /**
     * Apply date filtering to a query based on filter type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Http\Request $request
     * @param string $dateColumn The column to filter on (default: 'created_at')
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyDateFilter($query, $request, $dateColumn = 'created_at')
    {
        $filterType = $request->get('filter_type');

        switch ($filterType) {
            case 'date':
                if ($request->has('date') && !empty($request->date)) {
                    $date = Carbon::parse($request->date);
                    $query->whereDate($dateColumn, $date);
                }
                break;

            case 'month':
                if ($request->has('month') && !empty($request->month) && $request->has('year') && !empty($request->year)) {
                    $month = (int) $request->month;
                    $year = (int) $request->year;
                    $query->whereYear($dateColumn, $year)
                          ->whereMonth($dateColumn, $month);
                }
                break;

            case 'year':
                if ($request->has('year') && !empty($request->year)) {
                    $year = (int) $request->year;
                    $query->whereYear($dateColumn, $year);
                }
                break;

            case 'range':
                if ($request->has('date_from') && !empty($request->date_from) && $request->has('date_to') && !empty($request->date_to)) {
                    $dateFrom = Carbon::parse($request->date_from)->startOfDay();
                    $dateTo = Carbon::parse($request->date_to)->endOfDay();
                    $query->whereBetween($dateColumn, [$dateFrom, $dateTo]);
                }
                break;
        }

        return $query;
    }
}










