<?php

declare(strict_types=1);

namespace App\Tests\Agent;

use App\Agent\Tool\SiteTools;
use PHPUnit\Framework\TestCase;

final class ContrastingLooksTest extends TestCase
{
    public function testTwoLooksOfOneFamilyAreNotShownSideBySide(): void
    {
        self::assertSame(['warm', 'elegant', 'bold'], SiteTools::contrasting(['warm', 'elegant', 'fresh']));
        self::assertSame(['editorial', 'bold', 'warm'], SiteTools::contrasting(['editorial', 'elegant']));
    }

    public function testThePicksOfTheModelStayWhenTheyAlreadyDiffer(): void
    {
        self::assertSame(['minimal', 'bold', 'fresh'], SiteTools::contrasting(['minimal', 'bold', 'fresh']));
    }

    public function testOneOfTheThreeIsAlwaysTheDaringOne(): void
    {
        self::assertSame(['warm', 'editorial', 'bold'], SiteTools::contrasting(['warm', 'editorial', 'minimal']));
        self::assertSame(['warm', 'minimal', 'bold'], SiteTools::contrasting(['warm', 'minimal']));
    }
}
