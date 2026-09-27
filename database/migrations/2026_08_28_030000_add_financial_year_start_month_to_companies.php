<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performix Company Platform, Phase 1 (Performance Foundation): a real,
 * parseable financial-year start for the period engine.
 *
 * `companies.financial_year_start` has existed since the onboarding-intake
 * migration as a free-text month name ('January', 'April', ...) — written
 * once at intake and never read again anywhere in the app (confirmed by a
 * full-codebase audit). That's fine for display, but no period/quarter
 * calculation can be built on a free-text field with no guaranteed format.
 *
 * Adds `financial_year_start_month` (smallint, 1-12, default 1) as the real
 * input for `PerformancePeriodService`, backfilled from the existing text
 * column via a case-insensitive month-name match. `financial_year_start`
 * itself is untouched — still there for display, just no longer load-bearing
 * for any calculation.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table companies
                add column if not exists financial_year_start_month smallint not null default 1
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table companies add constraint companies_financial_year_start_month_check
              check (financial_year_start_month >= 1 and financial_year_start_month <= 12)
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            update companies set financial_year_start_month = case lower(trim(financial_year_start))
                when 'january' then 1 when 'february' then 2 when 'march' then 3
                when 'april' then 4 when 'may' then 5 when 'june' then 6
                when 'july' then 7 when 'august' then 8 when 'september' then 9
                when 'october' then 10 when 'november' then 11 when 'december' then 12
                else 1
            end
            where financial_year_start is not null
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('alter table companies drop constraint if exists companies_financial_year_start_month_check');
        DB::connection('pgsql')->statement('alter table companies drop column if exists financial_year_start_month');
    }
};
