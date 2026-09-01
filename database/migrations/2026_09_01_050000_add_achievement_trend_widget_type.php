<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extends company_dashboard_widgets' widget_type allow-list with
 * 'achievement_trend', backed by the new company_period_kpi_summary view
 * (2026_09_01_040000).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement('alter table company_dashboard_widgets drop constraint company_dashboard_widgets_type_check');
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table company_dashboard_widgets add constraint company_dashboard_widgets_type_check
              check (widget_type in ('company_overview', 'pending_approvals', 'recent_submissions', 'period_status', 'department_achievement', 'achievement_trend'))
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('alter table company_dashboard_widgets drop constraint company_dashboard_widgets_type_check');
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table company_dashboard_widgets add constraint company_dashboard_widgets_type_check
              check (widget_type in ('company_overview', 'pending_approvals', 'recent_submissions', 'period_status', 'department_achievement'))
        SQL);
    }
};
