<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Operation;

use Cadenza\Runtime\Catalog\Catalog;
use Cadenza\Runtime\Catalog\CoreNodeTypes;
use Cadenza\Runtime\Catalog\PatternNodeTypes;
use Cadenza\Runtime\Operation\OperationApplier;
use Cadenza\Runtime\Site\Document;
use PHPUnit\Framework\TestCase;

final class OperationApplierTest extends TestCase
{
    private OperationApplier $applier;

    protected function setUp(): void
    {
        $this->applier = new OperationApplier(new Catalog([new CoreNodeTypes(), new PatternNodeTypes()]));
    }

    public function testCreatesAPageWithNestedNodesAndAssignsIds(): void
    {
        $result = $this->applier->apply(Document::empty('Test'), [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [
                ['type' => 'hero', 'props' => ['title' => 'Hello'], 'actions' => [['type' => 'button', 'props' => ['label' => 'Go']]]],
            ]],
        ]);

        self::assertTrue($result->isValid(), implode(' ', $result->errors));
        $page = Document::findPage($result->document, '/');
        self::assertSame('hero', $page['nodes'][0]['type']);
        self::assertSame('button', $page['nodes'][0]['slots']['actions'][0]['type']);
        self::assertCount(3, $result->created, 'page, hero and button get ids');
    }

    public function testRejectsTheWholeBatchWhenOneOperationIsInvalid(): void
    {
        $doc = Document::empty();
        $result = $this->applier->apply($doc, [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home'],
            ['op' => 'node.insert', 'page' => '/', 'node' => ['type' => 'nonexistent']],
        ]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('unknown type "nonexistent"', $result->errors[0]);
    }

    public function testErrorsNameTheAllowedValues(): void
    {
        $result = $this->applier->apply(Document::empty(), [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [['type' => 'heading', 'props' => ['text' => 'Hi', 'level' => 9]]]],
        ]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('must be one of [1,2,3,4]', $result->errors[0]);
    }

    public function testSiteLanguageIsALanguageCode(): void
    {
        $ok = $this->applier->apply(Document::empty(), [['op' => 'site.update', 'language' => 'pl']]);
        self::assertTrue($ok->isValid());
        self::assertSame('pl', $ok->document['language']);

        self::assertFalse($this->applier->apply(Document::empty(), [['op' => 'site.update', 'language' => 'Polish']])->isValid());
    }

    public function testUpdateMergesPropsAndReplaceKeepsTheId(): void
    {
        $doc = $this->page([['type' => 'heading', 'props' => ['text' => 'Old']]]);
        $id = $doc['pages'][0]['nodes'][0]['id'];

        $updated = $this->applier->apply($doc, [['op' => 'node.update', 'id' => $id, 'props' => ['text' => 'New', 'align' => 'center']]]);
        self::assertSame(['text' => 'New', 'align' => 'center'], $updated->document['pages'][0]['nodes'][0]['props']);

        $replaced = $this->applier->apply($doc, [['op' => 'node.replace', 'id' => $id, 'node' => ['type' => 'text', 'props' => ['markdown' => 'Body']]]]);
        self::assertSame($id, $replaced->document['pages'][0]['nodes'][0]['id']);
        self::assertSame('text', $replaced->document['pages'][0]['nodes'][0]['type']);
    }

    public function testInsertAfterMoveAndRemove(): void
    {
        $doc = $this->page([['type' => 'heading', 'props' => ['text' => 'A']], ['type' => 'heading', 'props' => ['text' => 'B']]]);
        [$a, $b] = array_column($doc['pages'][0]['nodes'], 'id');

        $inserted = $this->applier->apply($doc, [['op' => 'node.insert', 'after' => $a, 'node' => ['type' => 'divider']]]);
        self::assertSame(['heading', 'divider', 'heading'], array_column($inserted->document['pages'][0]['nodes'], 'type'));

        $moved = $this->applier->apply($doc, [['op' => 'node.move', 'id' => $b, 'before' => $a]]);
        self::assertSame([$b, $a], array_column($moved->document['pages'][0]['nodes'], 'id'));

        $removed = $this->applier->apply($doc, [['op' => 'node.remove', 'id' => $a]]);
        self::assertSame([$b], array_column($removed->document['pages'][0]['nodes'], 'id'));
    }

    public function testANodeCannotMoveIntoItself(): void
    {
        $doc = $this->page([['type' => 'section', 'children' => [['type' => 'heading', 'props' => ['text' => 'In']]]]]);
        $section = $doc['pages'][0]['nodes'][0]['id'];

        $result = $this->applier->apply($doc, [['op' => 'node.move', 'id' => $section, 'parent' => $section]]);
        self::assertFalse($result->isValid());
    }

    public function testImagePropsOnlyAcceptTheMediaLibrary(): void
    {
        $result = $this->applier->apply(Document::empty(), [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [['type' => 'image', 'props' => ['src' => 'https://example.com/cat.jpg']]]],
        ]);
        self::assertFalse($result->isValid());

        $ok = $this->applier->apply(Document::empty(), [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [['type' => 'image', 'props' => ['src' => '/media/abc.jpg']]]],
        ]);
        self::assertTrue($ok->isValid(), implode(' ', $ok->errors));
    }

    public function testThemeDirectionSetsTheWholeLook(): void
    {
        $result = $this->applier->apply(Document::empty(), [['op' => 'theme.update', 'direction' => 'editorial']]);

        self::assertSame('editorial', $result->document['theme']['style']);
        self::assertSame('Fraunces', $result->document['theme']['fontHeading']);
    }

    public function testAssignedIdsMakeReapplyingDeterministic(): void
    {
        $operations = $this->applier->assignIds([], [['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [['type' => 'heading', 'props' => ['text' => 'A']]]]]);

        $first = $this->applier->apply(Document::empty(), $operations);
        $second = $this->applier->apply(Document::empty(), $operations);
        self::assertSame($first->document, $second->document);
    }

    public function testPropsNextToTypeAreAcceptedForSmallerModels(): void
    {
        $result = $this->applier->apply(Document::empty(), [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [['type' => 'heading', 'text' => 'Loose']]],
        ]);

        self::assertTrue($result->isValid(), implode(' ', $result->errors));
        self::assertSame('Loose', $result->document['pages'][0]['nodes'][0]['props']['text']);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return array<string, mixed>
     */
    private function page(array $nodes): array
    {
        $result = $this->applier->apply(Document::empty(), [['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => $nodes]]);
        self::assertTrue($result->isValid(), implode(' ', $result->errors));

        return $result->document;
    }
}
