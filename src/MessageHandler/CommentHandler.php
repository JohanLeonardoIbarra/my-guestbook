<?php

namespace App\MessageHandler;

use App\Message\Comment;
use App\Notification\CommentReviewNotification;
use App\Repository\CommentRepository;
use App\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Bridge\Discord\DiscordTransport;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\NotifierInterface;

final class CommentHandler implements MessageHandlerInterface
{
    private $spamChecker;
    private $entityManager;
    private $commentRepository;
    private $bus;
    private $workflow;
    private $logger;
    private $mailer;
    private $adminEmail;
    private $notifier;

    public function __construct(EntityManagerInterface $entityManager, NotifierInterface $notifier, SpamChecker $spamChecker, CommentRepository $commentRepository, LoggerInterface $logger, MessageBusInterface $bus, WorkflowInterface $commentStateMachine, MailerInterface $mailer, string $adminEmail)
    {
        $this->spamChecker = $spamChecker;
        $this->entityManager = $entityManager;
        $this->commentRepository = $commentRepository;
        $this->bus = $bus;
        $this->logger = $logger;
        $this->workflow = $commentStateMachine;
        $this->mailer = $mailer;
        $this->adminEmail = $adminEmail;
        $this->notifier = $notifier;
    }

    public function __invoke(Comment $message)
    {
        $comment = $this->commentRepository->find($message->getId());
        if (!$comment) {
            return;
        }

        $discord = new DiscordTransport("0Umx3xka6vPSHxW_K3whRybPT1BeQGMh9TKxf_CYXQQHt6FBMC6a5T5xYs9Cls-ALozy", "1024782139998351380");
        $notification = new CommentReviewNotification($comment);
        $this->notifier->send($notification, ...$this->notifier->getAdminRecipients());

        dd("Hola");
        if ($this->workflow->can($comment, 'accept')) {
            $score = $this->spamChecker->getSpamScore($comment, $message->getContext());
            $transition = 'accept';
            if (2 === $score) {
                $transition = 'reject_spam';
            }elseif (1 === $score) {
                $transition = 'might_be_spam';
            }

            $this->workflow->apply($comment, $transition);
            $this->entityManager->flush();

            $this->bus->dispatch($message);
        } elseif ($this->workflow->can($comment, 'publish') || $this->workflow->can($comment, 'publish_ham')) {
            //$this->notifier->send(new CommentReviewNotification($comment), ...$this->notifier->getAdminRecipients());
            $notification = new CommentReviewNotification($comment);
            $this->notifier->send($notification, ...$this->notifier->getAdminRecipients());
        } elseif ($this->logger) {
            $this->logger->debug('Dropping comment message', ['comment' => $comment->getId(), 'state' => $comment->getState()]);
        }
    }
}
