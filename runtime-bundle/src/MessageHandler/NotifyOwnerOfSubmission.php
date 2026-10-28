<?php

declare(strict_types=1);

namespace Cadenza\Runtime\MessageHandler;

use Cadenza\Runtime\Entity\Submission;
use Cadenza\Runtime\Message\FormSubmitted;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

/**
 * A visitor sent a form: tell the owner by e-mail, when an address (cadenza_runtime.notify_email) and a mailer are set.
 */
#[AsMessageHandler]
final class NotifyOwnerOfSubmission
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%cadenza.notify_email%')]
        private readonly ?string $to = null,
        private readonly ?MailerInterface $mailer = null,
    ) {
    }

    public function __invoke(FormSubmitted $event): void
    {
        $submission = $this->em->find(Submission::class, $event->submissionId);
        if (null === $this->to || null === $this->mailer || null === $submission) {
            return;
        }
        $lines = [];
        foreach ($submission->getAnswers() as $answer) {
            $value = match (true) {
                \is_array($answer['value']) => implode(', ', $answer['value']),
                \is_bool($answer['value']) => $answer['value'] ? '✓' : '—',
                default => (string) ($answer['value'] ?? '—'),
            };
            $lines[] = $answer['label'].': '.$value;
        }
        $reply = array_values(array_filter($submission->getAnswers(), static fn (array $a): bool => 'email' === $a['type'] && \is_string($a['value'])))[0]['value'] ?? null;

        $email = (new Email())
            ->from($this->to)
            ->to($this->to)
            ->subject(\sprintf('%s — new answer', $event->formTitle))
            ->text(implode("\n", $lines));
        if (null !== $reply) {
            $email->replyTo($reply);
        }
        $this->mailer->send($email);
    }
}
