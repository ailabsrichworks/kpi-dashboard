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
-- notify_employee_id: an optional employee to notify about this task via
-- Telegram, independent of assignee_employee_id -- lets a manager point a
-- task at someone's Telegram inbox even when they're not the assignee
-- (e.g. a superior looping in a report). Originally designed as a raw
-- notify_email TEXT column sent via SMTP, but this app has no real mail
-- driver configured in production (MAIL_MAILER=log, i.e. nothing is
-- actually delivered) -- redesigned to reuse the Telegram notification
-- pipeline every other task/approval notification in this app already
-- goes through (NotificationService::notify()), which is proven to
-- actually deliver real messages today. A UUID reference to employees.id
-- rather than a free-text address, since the recipient must always be a
-- real Performix employee -- the same closed set the dropdown itself is
-- built from (TaskAccessPolicy::visibleEmployeeIds()).
ALTER TABLE telegram_project_tasks
    ADD COLUMN IF NOT EXISTS due_time TIME NULL,
    ADD COLUMN IF NOT EXISTS notify_employee_id UUID NULL;
