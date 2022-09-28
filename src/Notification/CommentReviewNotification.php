<?php

namespace App\Notification;

use App\Entity\Comment;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Bridge\Discord\DiscordOptions;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordEmbed;

class CommentReviewNotification extends Notification implements EmailNotificationInterface, ChatNotificationInterface
{
    private $comment;

    public function __construct(Comment $comment)
    {
        $this->comment = $comment;

        parent::__construct('New comment posted');
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, string $transport = null): ?EmailMessage
    {
        $message = EmailMessage::fromNotification($this, $recipient, $transport);
        $message->getMessage()
            ->htmlTemplate('emails/comment_notification.html.twig')
            ->context(['comment' => $this->comment])
        ;

        return $message;
    }

    public function asChatMessage(RecipientInterface $recipient, string $transport = null): ?ChatMessage
    {
        $message = ChatMessage::fromNotification($this, $recipient, $transport);
        $message->subject($this->getSubject());
        $message->options((new DiscordOptions())
            ->username('Guestbook')
            ->addEmbed(new DiscordEmbed())
            ->description('descript.io')
            ->url('http://ava.tar/pic.png')
            ->timestamp(new \DateTime('2020-10-12 9:14:15+0000'))
            ->color(2021216)
            ->title('New song added!')
        );

        return $message;
    }
}