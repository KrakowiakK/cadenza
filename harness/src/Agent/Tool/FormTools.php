<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Studio\SiteClient;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('read_answers', 'What visitors sent through the site\'s forms (RSVPs, sign-ups, orders, messages): numbers, totals per option and the answers. Use it when the owner asks who is coming, how many, what people chose or wrote. Without formId: an overview of all forms.', method: 'read')]
final class FormTools
{
    public function __construct(private readonly SiteClient $site)
    {
    }

    /**
     * @param string|null $formId Id of one form (from the overview); empty = overview of all forms
     */
    public function read(?string $formId = null): string
    {
        $forms = $this->site->forms();
        if ([] === $forms) {
            return 'The site has no published form yet. A form is the "form" pattern (RSVP, sign-up, order, contact); answers start arriving once its sketch is approved.';
        }
        if (null === $formId || '' === $formId) {
            if (1 !== \count($forms)) {
                return implode("\n", array_map(static fn (array $f): string => \sprintf('- %s "%s" on %s: %d answers, %d people%s%s%s; fields: %s',
                    $f['id'], $f['title'], $f['page'], $f['answers'], $f['people'],
                    null !== $f['capacity'] ? \sprintf(', %d of %d places left', $f['left'], $f['capacity']) : '',
                    $f['unseen'] > 0 ? \sprintf(', %d new', $f['unseen']) : '',
                    $f['open'] ? '' : ', closed ('.$f['closed'].')',
                    implode(', ', $f['fields'])), $forms))."\nCall read_answers with a formId for the answers.";
            }
            $formId = $forms[0]['id'];
        }
        $r = $this->site->formAnswers($formId);
        if (!($r['ok'] ?? false)) {
            return $r['error'] ?? 'Cannot read the answers.';
        }
        $out = [\sprintf('Form "%s": %d answers, %d people%s%s.', $r['form'], $r['answers'], $r['people'],
            null !== $r['capacity'] ? \sprintf(', %d of %d places left', $r['left'], $r['capacity']) : '',
            $r['open'] ? '' : ', closed')];
        $out[] = 'Totals:';
        foreach ($r['totals'] as $label => $total) {
            $out[] = '  - '.$label.': '.implode(', ', array_map(static fn (string|int $k, mixed $v): string => $k.' '.$v, array_keys($total), $total));
        }
        $out[] = 'Answers (newest first):';
        foreach ($r['answersList'] as $a) {
            $out[] = '  - '.$a['at'].': '.implode('; ', array_map(static fn (string $k, mixed $v): string => $k.': '.(\is_array($v) ? implode(', ', $v) : (\is_bool($v) ? ($v ? 'yes' : 'no') : (string) ($v ?? '—'))), array_keys($a['answers']), $a['answers']));
        }
        $out[] = 'The owner can download all answers as Excel from the form on the page (only they see the "Answers" strip there).';

        return implode("\n", $out);
    }
}
