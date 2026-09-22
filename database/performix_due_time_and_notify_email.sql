-- NOT YET APPLIED to Supabase project mlggobjdsicuokblbsww.
-- (kept here for reference/reproducibility, same pattern as
-- performix_meeting_time.sql -- there is no direct Postgres/DDL access
-- from the app; apply manually via the Supabase SQL editor or MCP).
--
-- due_time: an optional time-of-day attached to a task's due_date --
-- distinct from meeting_time, which marks a scheduled meeting rather than
-- a deadline. Lets the New/Edit Task forms capture "due 5pm on the 30th",
-- not just "due sometime on the 30th".
--
-- notify_email: an optional email address to notify about this task,
-- independent of assignee_employee_id -- lets a manager point a task at
-- someone's inbox even when that person has no Performix account of their
-- own (e.g. a superior emailing a report who isn't set up in the system
-- yet).
ALTER TABLE telegram_project_tasks
    ADD COLUMN IF NOT EXISTS due_time TIME NULL,
    ADD COLUMN IF NOT EXISTS notify_email TEXT NULL;
