<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Form;

use Cadenza\Runtime\Form\Forms;
use PHPUnit\Framework\TestCase;

final class FormsValidateTest extends TestCase
{
    private const FORM = ['id' => 'f1', 'type' => 'form', 'props' => ['peopleField' => 'Guests', 'fields' => [
        ['label' => 'Name', 'type' => 'text', 'required' => true],
        ['label' => 'Guests', 'type' => 'number'],
        ['label' => 'Diet', 'type' => 'choices', 'options' => ['vegan', 'gluten-free']],
        ['label' => 'Consent', 'type' => 'checkbox', 'required' => true],
        ['label' => 'Phone', 'type' => 'phone'],
    ]]];

    public function testValidAnswersAreTypedAndCountPeople(): void
    {
        $r = Forms::validate(self::FORM, ['  Ann ', '3', ['vegan', 'invented'], '1', '+48 600 100 200']);

        self::assertSame([], $r['errors']);
        self::assertSame(3, $r['people']);
        self::assertSame(['Ann', 3, ['vegan'], true, '+48 600 100 200'], array_column($r['answers'], 'value'));
    }

    public function testMissingAndMalformedValuesAreReportedByField(): void
    {
        $r = Forms::validate(self::FORM, ['', 'many', [], null, 'call me']);

        self::assertSame([0 => 'required', 1 => 'number', 3 => 'required', 4 => 'phone'], $r['errors']);
        self::assertSame(1, $r['people']);
    }
}
