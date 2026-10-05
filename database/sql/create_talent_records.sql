-- Run this once in the Supabase SQL Editor (dashboard -> SQL Editor -> New query).
--
-- Backs the Talent Tracker page (/talent-tracker): one row per training an
-- employee attended, or a session they spoke at / trained / answered
-- questions in. Staff themselves are NOT stored here -- the page reads the
-- existing `employees` table, so a person's name, position and department
-- always match the rest of the system.
--
-- Consistent with every other legacy table in this project: plain Supabase
-- Postgres table, no RLS, accessed only via SupabaseService (service_role)
-- from app/Http/Controllers/TalentTrackerController.php, which is where the
-- "only SLT and BTS may add/edit/delete" rule is enforced.

create table if not exists talent_records (
    id uuid primary key default gen_random_uuid(),
    company_code text not null,
    employee_id uuid not null,
    type text not null check (type in ('attended', 'speaker', 'trainer', 'qna', 'other')),
    title text not null,
    record_date date not null,
    notes text null,
    created_by uuid null,
    created_by_name text null,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create index if not exists talent_records_company_employee_idx on talent_records (company_code, employee_id);
create index if not exists talent_records_company_date_idx on talent_records (company_code, record_date desc);
