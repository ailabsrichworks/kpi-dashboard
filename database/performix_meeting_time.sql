-- NOT YET APPLIED to Supabase project mlggobjdsicuokblbsww.
-- (kept here for reference/reproducibility, same pattern as
-- performix_tasks_extension.sql — there is no direct Postgres/DDL access
-- from the app; apply manually via the Supabase SQL editor or MCP).
--
-- Adds an optional time-of-day to a Performix to-do task, distinct from its
-- date-only due_date, so the redesigned Kanban board can tell "due sometime
-- today" apart from "a meeting at 2:30 PM" — mirrors the Platform Tasks
-- feature's own meeting_time column
-- (database/migrations/2026_09_21_000000_add_meeting_time_to_tasks.php).
ALTER TABLE telegram_project_tasks
    ADD COLUMN IF NOT EXISTS meeting_time TIME NULL;
