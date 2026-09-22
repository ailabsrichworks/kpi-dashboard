// Supabase Edge Function: task-notification
//
// Triggered by a Database Webhook on `telegram_project_tasks`
// (Insert / Update / Delete). Notifies the task's creator (employee_id) and
// assignee (assignee_employee_id) via Telegram — and, on create/update, the
// person picked in "Notify via Telegram" (notify_employee_id) — the same
// three recipients app/Services/NotificationService.php already resolves,
// just reachable even when the row changes outside the Laravel app (a direct
// Supabase edit, another integration, etc).
//
// Setup:
//   1. Deploy this function (Supabase Dashboard -> Edge Functions -> Deploy
//      a new function -> name it "task-notification" -> paste this file).
//   2. Database -> Webhooks -> Create a new webhook
//        Table:  telegram_project_tasks
//        Events: Insert, Update, Delete
//        Type:   Supabase Edge Functions
//        Function: task-notification
//   TELEGRAM_BOT_TOKEN is already set as a project secret (see Edge
//   Functions -> Secrets) and SUPABASE_URL / SUPABASE_SERVICE_ROLE_KEY are
//   injected automatically into every function — nothing else to configure.
//
// NOTE: app/Http/Controllers/MiniAppTaskController.php already calls
// NotificationService on every store/update/progress/destroy through the
// mini-app UI, so wiring this webhook on top of that will send a SECOND
// Telegram message for actions taken through the app. This function exists
// to catch changes the app doesn't see (direct Supabase edits, another
// client). If double notifications for in-app actions are a problem, either
// skip creating the Insert/Update webhook events above (keep only Delete,
// which the app also already covers, or none) or turn off the app-level
// notify calls — don't run both for the same event type.

import { createClient } from 'https://esm.sh/@supabase/supabase-js@2.45.4'

const supabase = createClient(
  Deno.env.get('SUPABASE_URL')!,
  Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!,
)

const TELEGRAM_BOT_TOKEN = Deno.env.get('TELEGRAM_BOT_TOKEN')!

type TaskRow = {
  id: string
  title: string
  description?: string | null
  priority?: string | null
  due_date?: string | null
  due_time?: string | null
  employee_id: string
  assignee_employee_id?: string | null
  notify_employee_id?: string | null
}

type WebhookPayload = {
  type: 'INSERT' | 'UPDATE' | 'DELETE'
  table: string
  record: TaskRow | null
  old_record: TaskRow | null
}

Deno.serve(async (req) => {
  let payload: WebhookPayload
  try {
    payload = await req.json()
  } catch {
    return new Response('Invalid JSON', { status: 400 })
  }

  const { type, record, old_record } = payload
  const task = type === 'DELETE' ? old_record : record
  if (!task) {
    return json({ ok: true, notified: [], reason: 'no task row in payload' })
  }

  const recipientIds = new Set<string>()
  if (task.employee_id) recipientIds.add(task.employee_id)
  if (task.assignee_employee_id) recipientIds.add(task.assignee_employee_id)
  if (type !== 'DELETE' && task.notify_employee_id) recipientIds.add(task.notify_employee_id)

  if (recipientIds.size === 0) {
    return json({ ok: true, notified: [] })
  }

  const employeeIds = [...recipientIds]

  const [{ data: employees }, { data: roles }] = await Promise.all([
    supabase.from('employees').select('id, short_name').in('id', employeeIds),
    supabase
      .from('user_company_roles')
      .select('employee_id, user_id')
      .in('employee_id', employeeIds)
      .eq('is_active', true),
  ])

  const userIdByEmployeeId = new Map((roles ?? []).map((r) => [r.employee_id, r.user_id]))
  const nameByEmployeeId = new Map((employees ?? []).map((e) => [e.id, e.short_name]))

  const userIds = [...new Set([...userIdByEmployeeId.values()])]
  const { data: users } = userIds.length
    ? await supabase.from('users').select('id, telegram_chat_id').in('id', userIds)
    : { data: [] as { id: string; telegram_chat_id: number | null }[] }

  const chatIdByUserId = new Map((users ?? []).map((u) => [u.id, u.telegram_chat_id]))

  const message = buildMessage(type, task, nameByEmployeeId)

  const notified: string[] = []
  for (const employeeId of employeeIds) {
    const userId = userIdByEmployeeId.get(employeeId)
    const chatId = userId ? chatIdByUserId.get(userId) : null
    if (!chatId) continue

    const ok = await sendTelegram(chatId, message)
    if (ok) notified.push(employeeId)
  }

  return json({ ok: true, notified })
})

const PRIORITY_LABELS: Record<string, string> = {
  low: '🟢 Low',
  medium: '🟡 Medium',
  high: '🟠 High',
  critical: '🔴 Critical',
}

// Renders as dd/mm/yyyy | HH:MM:SS, per the format requested for every
// date/time shown in these notifications -- due_date/due_time come out of
// Postgres as "yyyy-mm-dd"/"HH:MM:SS", so this is just a re-arrangement, not
// a timezone conversion.
function formatDueDateTime(dueDate?: string | null, dueTime?: string | null): string | null {
  if (!dueDate) return null
  const [y, m, d] = dueDate.split('-')
  const time = dueTime ? dueTime.slice(0, 8) : '00:00:00'
  return `${d}/${m}/${y} | ${time}`
}

// Telegram's HTML parse mode only needs these three characters escaped --
// task titles/descriptions/names are free text and could contain any of
// them, which would otherwise break formatting or get silently dropped.
function escapeHtml(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function buildMessage(
  type: WebhookPayload['type'],
  task: TaskRow,
  nameByEmployeeId: Map<string, string>,
): string {
  const assigneeName = task.assignee_employee_id
    ? nameByEmployeeId.get(task.assignee_employee_id) ?? 'Unknown'
    : 'Unassigned'
  const creatorName = nameByEmployeeId.get(task.employee_id) ?? 'Unknown'
  const notifyName = task.notify_employee_id
    ? nameByEmployeeId.get(task.notify_employee_id) ?? 'Unknown'
    : null

  const title = escapeHtml(task.title ?? 'Untitled task')
  const description = task.description ? escapeHtml(task.description) : '-'
  const priority = PRIORITY_LABELS[task.priority ?? ''] ?? '⚪ Not set'
  const due = formatDueDateTime(task.due_date, task.due_time) ?? 'Not set'

  const heading = type === 'INSERT'
    ? '🆕 <b>New Task Created</b>'
    : type === 'UPDATE'
      ? '✏️ <b>Task Updated</b>'
      : '🗑️ <b>Task Deleted</b>'

  const dueLabel = type === 'DELETE' ? 'Was due' : 'Due'
  const assignedLabel = type === 'DELETE' ? 'Was assigned to' : 'Assigned to'

  const lines = [
    heading,
    '',
    `📌 <b>Title:</b> ${title}`,
    `📝 <b>Description:</b> ${description}`,
    `⚡ <b>Priority:</b> ${priority}`,
    `📅 <b>${dueLabel}:</b> ${due}`,
    `👤 <b>${assignedLabel}:</b> ${escapeHtml(assigneeName)}`,
    `✍️ <b>Created by:</b> ${escapeHtml(creatorName)}`,
  ]

  if (notifyName && type !== 'DELETE') {
    lines.push(`📨 <b>Notify:</b> ${escapeHtml(notifyName)}`)
  }

  return lines.join('\n')
}

async function sendTelegram(chatId: number, text: string): Promise<boolean> {
  try {
    const res = await fetch(`https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ chat_id: chatId, text, parse_mode: 'HTML' }),
    })
    return res.ok
  } catch {
    return false
  }
}

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}
