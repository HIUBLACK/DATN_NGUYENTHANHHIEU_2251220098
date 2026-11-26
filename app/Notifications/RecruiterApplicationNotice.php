<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class RecruiterApplicationNotice extends Notification
{
    public function __construct(
        public string $applicationId,
        public string $jobTitle,
        public string $candidateName,
        public int $attempt,
        public string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'application_id' => $this->applicationId, 'job_title' => $this->jobTitle,
            'candidate_name' => $this->candidateName, 'attempt' => $this->attempt, 'status' => $this->status,
        ];
    }
}
