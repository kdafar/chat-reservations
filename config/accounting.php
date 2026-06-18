<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fiscal year start month
    |--------------------------------------------------------------------------
    | Month (1–12) the fiscal year begins on. EVA uses the calendar year
    | (January), which is standard for most Kuwait companies. Drives the
    | retained-earnings ("current year") figure on the Balance Sheet and the
    | year-to-date boundary for reports. Change here (or via env) if EVA ever
    | adopts a non-calendar fiscal year.
    */
    'fiscal_year_start_month' => (int) env('ACCOUNTING_FISCAL_YEAR_START_MONTH', 1),

    /*
    |--------------------------------------------------------------------------
    | Depreciation
    |--------------------------------------------------------------------------
    | Default method for fixed-asset depreciation. Straight-line is the only
    | method implemented. The monthly depreciation run posts on this day of the
    | month (clamped to the last day for short months).
    */
    'depreciation' => [
        'method' => 'straight_line',
        'run_day_of_month' => 28,
    ],
];
