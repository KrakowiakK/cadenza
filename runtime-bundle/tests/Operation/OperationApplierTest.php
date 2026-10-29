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

    public function testButtonsAmongTheHeroPropsAreItsActions(): void
    {
        // As a model wrote the opening of a new site: a slot among the props, buttons without a type, a link by name.
        $result = $this->applier->apply(Document::empty('Test'), [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [
                ['type' => 'hero', 'props' => ['title' => 'Piekarnia', 'actions' => [['text' => 'Nasze wypieki', 'href' => '#wypieki']]]],
                ['type' => 'section', 'children' => [['type' => 'heading', 'props' => ['text' => 'Wypieki']]]],
            ]],
        ]);

        self::assertTrue($result->isValid(), implode(' ', $result->errors));
        $page = Document::findPage($result->document, '/');
        $button = $page['nodes'][0]['slots']['actions'][0];
        self::assertSame(['button', 'Nasze wypieki'], [$button['type'], $button['props']['label']]);
        self::assertSame('#'.$page['nodes'][1]['id'], $button['props']['href'], 'the link points to the section of the heading "Wypieki"');
    }

    public function testABlockWithTheHeadingOfASectionBecomesASection(): void
    {
        $result = $this->applier->apply(Document::empty('Test'), [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [
                ['type' => 'faq', 'props' => ['eyebrow' => 'FAQ', 'title' => 'Pytania', 'background' => 'muted', 'align' => 'center',
                    'items' => [['question' => 'Parking?', 'answer' => 'Za budynkiem.']]]],
            ]],
        ]);

        self::assertTrue($result->isValid(), implode(' ', $result->errors));
        $section = Document::findPage($result->document, '/')['nodes'][0];
        self::assertSame(['section', 'muted'], [$section['type'], $section['props']['background']]);
        self::assertSame(['heading', 'faq'], array_column($section['slots']['children'], 'type'));
        self::assertSame(['text' => 'Pytania', 'eyebrow' => 'FAQ', 'align' => 'center'], $section['slots']['children'][0]['props']);
        self::assertSame('Parking?', $section['slots']['children'][1]['props']['items'][0]['question']);
    }

    public function testAnIdAmongThePropsIsTheNodesId(): void
    {
        $result = $this->applier->apply(Document::empty('Test'), [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [
                ['type' => 'heading', 'props' => ['id' => 'zapisy', 'text' => 'Zapisy']],
            ]],
        ]);

        self::assertTrue($result->isValid(), implode(' ', $result->errors));
        self::assertSame('zapisy', Document::findPage($result->document, '/')['nodes'][0]['id']);
    }

    public function testATypeBesideTheNodeIsItsType(): void
    {
        $base = $this->applier->apply(Document::empty('Test'), [['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => []]])->document;
        $result = $this->applier->apply($base, [['op' => 'node.insert', 'page' => '/', 'type' => 'heading', 'node' => ['text' => 'Gry']]]);

        self::assertTrue($result->isValid(), implode(' ', $result->errors));
        self::assertSame('Gry', Document::findPage($result->document, '/')['nodes'][0]['props']['text']);
    }

    public function testAnObjectWhereATextBelongsBecomesItsWords(): void
    {
        $result = $this->applier->apply(Document::empty('Test'), [['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [
            ['type' => 'list', 'props' => ['items' => ['Punktualność', ['title' => 'Telefony', 'text' => 'wyciszone']]]],
        ]]]);

        self::assertTrue($result->isValid(), implode(' ', $result->errors));
        self::assertSame(['Punktualność', 'Telefony — wyciszone'], Document::findPage($result->document, '/')['nodes'][0]['props']['items']);
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

    public function testPagesCanBeForMembersOnly(): void
    {
        $result = $this->applier->apply(Document::empty(), [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home'],
            ['op' => 'page.create', 'path' => '/stay', 'title' => 'Stay', 'access' => 'members'],
        ]);
        self::assertTrue($result->isValid());
        self::assertSame('members', Document::findPage($result->document, '/stay')['access']);
        self::assertSame([false, true], array_column(Document::navItems($result->document), 'members'));

        $opened = $this->applier->apply($result->document, [['op' => 'page.update', 'page' => '/stay', 'access' => 'public']]);
        self::assertSame('public', Document::findPage($opened->document, '/stay')['access']);

        self::assertFalse($this->applier->apply($result->document, [['op' => 'page.update', 'page' => '/stay', 'access' => 'secret']])->isValid());
    }

    public function testEachLanguageHasItsOwnMenu(): void
    {
        $result = $this->applier->apply(Document::empty(), [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Start', 'nodes' => [['id' => 'about', 'type' => 'heading', 'props' => ['text' => 'O mnie']]]],
            ['op' => 'site.update', 'language' => 'pl', 'nav' => [['label' => 'O mnie', 'href' => '#about']]],
            ['op' => 'page.create', 'path' => '/en', 'title' => 'Home', 'lang' => 'en', 'translationOf' => '/', 'nodes' => [['id' => 'about-en', 'type' => 'heading', 'props' => ['text' => 'About me']]]],
            ['op' => 'page.create', 'path' => '/de', 'title' => 'Start', 'lang' => 'de', 'translationOf' => '/'],
            ['op' => 'site.update', 'lang' => 'en', 'nav' => [['label' => 'About me', 'href' => '/en#about-en']]],
        ]);
        self::assertTrue($result->isValid(), implode(' ', $result->errors));
        $doc = $result->document;

        self::assertSame('O mnie', Document::navItems($doc, Document::findPage($doc, '/'))[0]['label']);
        self::assertSame('About me', Document::navItems($doc, Document::findPage($doc, '/en'))[0]['label']);
        self::assertSame([['label' => 'Start', 'href' => '/de', 'members' => false]], Document::navItems($doc, Document::findPage($doc, '/de')), 'no menu of its own: the pages of that language');
        self::assertSame(['pl' => '/', 'en' => '/en', 'de' => '/de'], array_column(Document::translations($doc, Document::findPage($doc, '/en')), 'href', 'lang'));
    }

    public function testLinksToSectionsMustNameARealSection(): void
    {
        $doc = $this->applier->apply(Document::empty(), [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [['id' => 'nabout', 'type' => 'heading', 'props' => ['text' => 'About']]]],
        ])->document;

        $wrong = $this->applier->apply($doc, [['op' => 'site.update', 'nav' => [['label' => 'O mnie', 'href' => '/#o-mnie']]]]);
        self::assertFalse($wrong->isValid());
        self::assertStringContainsString('nabout (heading)', $wrong->errors[0]);

        self::assertTrue($this->applier->apply($doc, [['op' => 'site.update', 'nav' => [['label' => 'O mnie', 'href' => '/#nabout'], ['label' => 'Blog', 'href' => '/blog']]]])->isValid());
        self::assertFalse($this->applier->apply($doc, [['op' => 'node.insert', 'page' => '/', 'node' => ['type' => 'button', 'props' => ['label' => 'Go', 'href' => '#nowhere']]]])->isValid());
    }

    public function testLanguageNextToAMenuNamesTheMenusLanguage(): void
    {
        $result = $this->applier->apply(Document::empty(), [
            ['op' => 'page.create', 'path' => '/', 'title' => 'Start', 'nodes' => [['id' => 'about', 'type' => 'heading', 'props' => ['text' => 'O mnie']]]],
            ['op' => 'page.create', 'path' => '/en', 'title' => 'Home', 'lang' => 'en', 'translationOf' => '/', 'nodes' => [['id' => 'about-en', 'type' => 'heading', 'props' => ['text' => 'About']]]],
            ['op' => 'site.update', 'language' => 'pl', 'nav' => [['label' => 'O mnie', 'href' => '/#about']]],
            ['op' => 'site.update', 'language' => 'en', 'nav' => [['label' => 'About', 'href' => '/en#about-en']]],
        ]);

        self::assertTrue($result->isValid(), implode(' ', $result->errors));
        self::assertSame('pl', $result->document['language']);
        self::assertSame('O mnie', $result->document['nav']['items'][0]['label']);
        self::assertSame('About', $result->document['nav']['byLanguage']['en'][0]['label']);
    }

    public function testSiteUpdateRefusesKeysItWouldIgnore(): void
    {
        $result = $this->applier->apply(Document::empty(), [['op' => 'site.update', 'enNav' => [], 'nav' => null]]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('"enNav"', $result->errors[0]);
        self::assertStringContainsString('"lang": "en"', $result->errors[0]);
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
