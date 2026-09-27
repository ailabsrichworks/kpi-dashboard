<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table companies
                add column if not exists legal_name text,
                add column if not exists registration_number text,
                add column if not exists industry text,
                add column if not exists country text,
                add column if not exists timezone text,
                add column if not exists financial_year_start text,
                add column if not exists financial_year_end text,
                add column if not exists estimated_employee_count integer,
                add column if not exists primary_contact_name text,
                add column if not exists primary_contact_email text,
                add column if not exists primary_contact_phone text,
                add column if not exists company_admin_name text,
                add column if not exists company_admin_email text,
                add column if not exists cam_name text,
                add column if not exists subscription_plan text,
                add column if not exists contract_start_date date,
                add column if not exists contract_end_date date,
                add column if not exists user_limit integer,
                add column if not exists subdomain text
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create unique index if not exists companies_subdomain_unique
              on companies (lower(subdomain))
              where subdomain is not null
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop index if exists companies_subdomain_unique');
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table companies
                drop column if exists subdomain,
                drop column if exists user_limit,
                drop column if exists contract_end_date,
                drop column if exists contract_start_date,
                drop column if exists subscription_plan,
                drop column if exists cam_name,
                drop column if exists company_admin_email,
                drop column if exists company_admin_name,
                drop column if exists primary_contact_phone,
                drop column if exists primary_contact_email,
                drop column if exists primary_contact_name,
                drop column if exists estimated_employee_count,
                drop column if exists financial_year_end,
                drop column if exists financial_year_start,
                drop column if exists timezone,
                drop column if exists country,
                drop column if exists industry,
                drop column if exists registration_number,
                drop column if exists legal_name
        SQL);
    }
};
