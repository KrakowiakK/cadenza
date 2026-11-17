<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Ask;

use Cadenza\Runtime\Entity\VisitorQuestion;
use Doctrine\ORM\EntityManagerInterface;

/**
 * What visitors asked the site, for the owner: the newest questions, and above all the ones the site could not answer.
 */
final class VisitorQuestions
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function record(string $question, ?string $answer, bool $answered, string $path): VisitorQuestion
    {
        $entry = new VisitorQuestion(mb_substr($question, 0, 320), $answer, $answered, mb_substr($path, 0, 255));
        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /**
     * @return array{total: int, unanswered: int, week: int, missing: list<array<string, mixed>>, recent: list<array<string, mixed>>}
     */
    public function summary(int $limit = 30): array
    {
        $repo = $this->em->getRepository(VisitorQuestion::class);
        $count = fn (array $by): int => $repo->count($by);
        $week = (int) $this->em->createQuery('SELECT COUNT(q.id) FROM '.VisitorQuestion::class.' q WHERE q.createdAt >= :since')
            ->setParameter('since', new \DateTimeImmutable('-7 days'))->getSingleScalarResult();
        // A question the site could not answer stops being "missing" once the same question got an answer later
        // (the owner added it to the site).
        $missing = [];
        $solved = [];
        foreach ($repo->findBy([], ['id' => 'DESC'], 400) as $q) {
            $key = mb_strtolower(trim($q->getQuestion(), " \t\n?!.,"));
            if ($q->isAnswered()) {
                $solved[$key] = true;
            } elseif (!isset($solved[$key]) && !isset($missing[$key])) {
                $missing[$key] = $q->toArray();
            }
        }

        return [
            'total' => $count([]),
            'unanswered' => \count($missing),
            'week' => $week,
            'missing' => \array_slice(array_values($missing), 0, $limit),
            'recent' => array_map(static fn (VisitorQuestion $q): array => $q->toArray(), $repo->findBy([], ['id' => 'DESC'], $limit)),
        ];
    }
}
