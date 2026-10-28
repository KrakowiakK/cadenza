<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Mcp\Agent;

use Cadenza\Runtime\Form\Forms;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * What visitors sent through the site's forms — for the owner's questions ("how many guests are coming?").
 */
final class FormTools
{
    public function __construct(private readonly Forms $forms)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[McpTool(name: 'forms_list', description: 'Published forms on the site with their fields and numbers: answers, people, capacity, places left, new answers, open or closed.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function list(): array
    {
        return $this->forms->all();
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'form_answers', description: 'Answers sent through one form, newest first, with totals: counts for every option of choice fields, sums of number fields, yes-count of checkboxes.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function answers(
        #[Schema(description: 'Form node id (from forms_list)')]
        string $formId,
        int $limit = 100,
    ): array {
        $form = $this->forms->published($formId);
        if (null === $form) {
            return ['ok' => false, 'error' => 'No published form with this id; see forms_list.'];
        }
        $all = $this->forms->submissions($formId, 10000);
        $totals = [];
        foreach ($form['props']['fields'] ?? [] as $field) {
            $label = (string) $field['label'];
            $values = array_map(static fn ($s): mixed => $s->answer($label), $all);
            $totals[$label] = match ($field['type'] ?? 'text') {
                'select', 'radio' => array_count_values(array_map('strval', array_filter($values, 'is_string'))),
                'choices' => array_count_values(array_merge([], ...array_filter($values, 'is_array'))),
                'checkbox' => ['yes' => \count(array_filter($values, static fn (mixed $v): bool => true === $v)), 'no' => \count(array_filter($values, static fn (mixed $v): bool => true !== $v))],
                'number' => ['sum' => array_sum(array_filter($values, 'is_numeric')), 'answers' => \count(array_filter($values, 'is_numeric'))],
                default => ['filled' => \count(array_filter($values, static fn (mixed $v): bool => null !== $v && '' !== $v))],
            };
        }

        return ['ok' => true, 'form' => Forms::title($form)] + $this->forms->stats($form) + [
            'totals' => $totals,
            'answersList' => array_map(static fn ($s): array => $s->toArray(), \array_slice($all, 0, max(1, $limit))),
            'excel' => '/_cadenza/form/'.$formId.'/answers.xlsx',
        ];
    }
}
