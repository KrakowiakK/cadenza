<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Form;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Entity\Submission;
use Cadenza\Runtime\Message\FormSubmitted;
use Cadenza\Runtime\Site\Document;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Forms on the site and what visitors send through them. A form is a node of the published page tree; its fields
 * are the only definition — answers are validated against them, stored, and announced with FormSubmitted.
 */
final class Forms
{
    public function __construct(
        private readonly SiteManager $site,
        private readonly EntityManagerInterface $em,
        #[Target('cadenza.event_bus')]
        private readonly MessageBusInterface $events,
    ) {
    }

    /**
     * The form node as published (drafts do not collect answers).
     *
     * @return array<string, mixed>|null
     */
    public function published(string $formId): ?array
    {
        $node = Document::findNode($this->site->document(), $formId);

        return null !== $node && 'form' === $node['type'] ? $node : null;
    }

    /**
     * Every published form with its numbers.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $doc = $this->site->document();
        $forms = [];
        foreach (Document::walk($doc) as $entry) {
            if ('form' !== $entry['node']['type']) {
                continue;
            }
            $page = Document::findPage($doc, $entry['page']);
            $forms[] = [
                'id' => $entry['node']['id'],
                'title' => self::title($entry['node']),
                'page' => $page['path'] ?? '/',
                'fields' => array_map(static fn (array $f): string => $f['label'].' ('.($f['type'] ?? 'text').')', $entry['node']['props']['fields'] ?? []),
            ] + $this->stats($entry['node']);
        }

        return $forms;
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array{answers: int, people: int, capacity: ?int, left: ?int, unseen: int, open: bool, closed: ?string}
     */
    public function stats(array $form): array
    {
        $row = $this->em->createQuery('SELECT COUNT(s.id) AS answers, COALESCE(SUM(s.people), 0) AS people, COALESCE(SUM(CASE WHEN s.seen = false THEN 1 ELSE 0 END), 0) AS unseen FROM '.Submission::class.' s WHERE s.formId = :id')
            ->setParameter('id', $form['id'])
            ->getSingleResult();
        $capacity = isset($form['props']['capacity']) && (int) $form['props']['capacity'] > 0 ? (int) $form['props']['capacity'] : null;
        $left = null !== $capacity ? max(0, $capacity - (int) $row['people']) : null;
        $closesAt = $form['props']['closesAt'] ?? null;
        $closed = match (true) {
            0 === $left => 'full',
            \is_string($closesAt) && '' !== $closesAt && false !== ($t = strtotime($closesAt.' 23:59:59')) && $t < time() => 'date',
            default => null,
        };

        return ['answers' => (int) $row['answers'], 'people' => (int) $row['people'], 'capacity' => $capacity, 'left' => $left, 'unseen' => (int) $row['unseen'], 'open' => null === $closed, 'closed' => $closed];
    }

    /**
     * Places taken per option of the fields that have a per-option limit.
     *
     * @param array<string, mixed> $form
     *
     * @return array<string, array{limit: int, taken: array<string, int>}> by field label
     */
    public function optionPlaces(array $form): array
    {
        $limited = array_filter($form['props']['fields'] ?? [], static fn (array $f): bool => \in_array($f['type'] ?? 'text', ['select', 'radio'], true) && (int) ($f['limit'] ?? 0) > 0);
        if ([] === $limited) {
            return [];
        }
        $places = [];
        foreach ($limited as $field) {
            $places[(string) $field['label']] = ['limit' => (int) $field['limit'], 'taken' => array_fill_keys(array_map('strval', $field['options'] ?? []), 0)];
        }
        foreach ($this->submissions($form['id'], 100000) as $submission) {
            foreach ($places as $label => $_) {
                $chosen = $submission->answer($label);
                if (\is_string($chosen) && isset($places[$label]['taken'][$chosen])) {
                    $places[$label]['taken'][$chosen] += $submission->getPeople();
                }
            }
        }

        return $places;
    }

    /**
     * Fields whose chosen option has fewer places left than the answer needs.
     *
     * @param array<string, mixed> $form
     * @param list<array{label: string, type: string, value: mixed}> $answers
     *
     * @return array<int, string> field index => "option_full"
     */
    public function fullOptions(array $form, array $answers, int $people): array
    {
        $places = $this->optionPlaces($form);
        $errors = [];
        foreach ($answers as $i => $answer) {
            $p = $places[$answer['label']] ?? null;
            if (null !== $p && \is_string($answer['value']) && ($p['taken'][$answer['value']] ?? 0) + $people > $p['limit']) {
                $errors[$i] = 'option_full';
            }
        }

        return $errors;
    }

    /**
     * Checks the visitor's input against the form's fields.
     *
     * @param array<string, mixed> $form
     * @param array<int|string, mixed> $input values by field index
     *
     * @return array{answers: list<array{label: string, type: string, value: mixed}>, errors: array<int, string>, people: int}
     */
    public static function validate(array $form, array $input): array
    {
        $answers = [];
        $errors = [];
        $people = 1;
        $peopleField = mb_strtolower(trim((string) ($form['props']['peopleField'] ?? '')));
        foreach (array_values($form['props']['fields'] ?? []) as $i => $field) {
            $type = $field['type'] ?? 'text';
            $raw = $input[$i] ?? null;
            $options = array_values(array_map('strval', $field['options'] ?? []));
            $value = match ($type) {
                'checkbox' => null !== $raw && '' !== $raw && '0' !== $raw,
                'choices' => array_values(array_intersect($options, array_map('strval', \is_array($raw) ? $raw : []))),
                default => \is_scalar($raw) ? trim(mb_substr((string) $raw, 0, 'textarea' === $type ? 5000 : 300)) : '',
            };
            $empty = '' === $value || [] === $value || false === $value;
            if ($empty && ($field['required'] ?? false)) {
                $errors[$i] = 'required';
            } elseif (!$empty) {
                $error = match ($type) {
                    'email' => false === filter_var($value, \FILTER_VALIDATE_EMAIL) ? 'email' : null,
                    'phone' => !preg_match('/^\+?[\d\s()\/.-]{6,20}$/', $value) ? 'phone' : null,
                    'number' => !is_numeric(str_replace(',', '.', $value)) ? 'number' : null,
                    'date' => false === strtotime($value) ? 'date' : null,
                    'select', 'radio' => !\in_array($value, $options, true) ? 'option' : null,
                    default => null,
                };
                if (null !== $error) {
                    $errors[$i] = $error;
                }
            }
            if ('number' === $type && !isset($errors[$i]) && !$empty) {
                $value = 0 + str_replace(',', '.', $value);
                if ('' !== $peopleField && mb_strtolower(trim((string) $field['label'])) === $peopleField) {
                    $people = max(0, min(50, (int) $value));
                }
            }
            $answers[] = ['label' => (string) $field['label'], 'type' => $type, 'value' => $empty ? null : $value];
        }

        return ['answers' => $answers, 'errors' => $errors, 'people' => $people];
    }

    /**
     * @param array<string, mixed> $form
     * @param list<array{label: string, type: string, value: mixed}> $answers
     */
    public function store(array $form, array $answers, int $people): Submission
    {
        $submission = new Submission($form['id'], self::title($form), $answers, $people);
        $this->em->persist($submission);
        $this->em->flush();
        $this->events->dispatch(new FormSubmitted((int) $submission->getId(), $form['id'], self::title($form)));

        return $submission;
    }

    /**
     * @return list<Submission>
     */
    public function submissions(?string $formId = null, int $limit = 500): array
    {
        return $this->em->getRepository(Submission::class)->findBy(null !== $formId ? ['formId' => $formId] : [], ['id' => 'DESC'], $limit);
    }

    public function markSeen(string $formId): void
    {
        $this->em->createQuery('UPDATE '.Submission::class.' s SET s.seen = true WHERE s.formId = :id')->setParameter('id', $formId)->execute();
    }

    /**
     * The answers as an Excel file: one row per submission, one column per field.
     *
     * @param array<string, mixed> $form
     */
    public function export(array $form): string
    {
        $labels = array_map(static fn (array $f): string => (string) $f['label'], $form['props']['fields'] ?? []);
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('~[\\\\/?*\[\]:]~', ' ', self::title($form)) ?: 'Answers', 0, 31));
        $sheet->fromArray([['#', 'Date', ...$labels]]);
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $row = 2;
        foreach (array_reverse($this->submissions($form['id'], 10000)) as $n => $s) {
            $cells = [$n + 1, $s->getCreatedAt()->format('Y-m-d H:i')];
            foreach ($labels as $label) {
                $v = $s->answer($label);
                $cells[] = match (true) {
                    \is_array($v) => implode(', ', $v),
                    \is_bool($v) => $v ? '✓' : '',
                    default => $v,
                };
            }
            $sheet->fromArray([$cells], null, 'A'.$row++);
        }
        foreach (range(1, \count($labels) + 2) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
        $path = sys_get_temp_dir().'/cadenza-answers-'.bin2hex(random_bytes(6)).'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    /**
     * @param array<string, mixed> $form
     */
    public static function title(array $form): string
    {
        return (string) ($form['props']['title'] ?? null ?: 'Form '.$form['id']);
    }
}
