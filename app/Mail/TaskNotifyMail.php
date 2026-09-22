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
        // Leads with "TTD (Things To Do)" specifically because the
        // recipient may have no Performix account at all -- the subject is
        // often the only context they get for what this system even is.
        return $this->subject('TTD (Things To Do): ' . $this->taskTitle)->view('emails.task-notify');
    }
}
