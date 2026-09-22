<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a task's optional `notify_email` -- an address independent of
 * assignee_employee_id, so a task can point at someone's inbox even when
 * they have no Performix account of their own (e.g. a manager looping in a
 * report). Deliberately separate from AppNotificationMail, which is built
 * around an in-app notification row and a known employee's name/id.
 */
class TaskNotifyMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $taskTitle,
        public ?string $taskDescription,
        public ?string $dueDate,
        public ?string $dueTime,
        public string $creatorName,
        public string $priority = 'medium',
    ) {}

    public function build()
    {
        // Subject is the task title itself, plainly -- so a recipient's
        // inbox reads like a real task assignment ("Send the client
        // proposal"), not a system-name prefix. The "TTD (Things To Do)"
        // identification still happens prominently in the body (header
        // eyebrow + intro line + footer) for a recipient who has no
        // Performix account and needs to know what this system even is.
        return $this->subject($this->taskTitle)->view('emails.task-notify');
    }
}
