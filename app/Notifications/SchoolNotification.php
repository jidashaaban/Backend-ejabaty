<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolNotification extends Notification
{

    use Queueable;
    protected $title;
    protected $message;
    protected $type; // e.g., 'quiz', 'mark', 'complaint'
    protected $related_id;
    /**
     * Create a new notification instance.
     */
    public function __construct($title, $message, $type, $related_id = null)
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
        $this->related_id = $related_id;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'related_id' => $this->related_id,
        ];
    }
}
