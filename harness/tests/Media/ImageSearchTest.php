<?php

declare(strict_types=1);

namespace App\Tests\Media;

use App\Media\ImageSearch;
use PHPUnit\Framework\TestCase;

final class ImageSearchTest extends TestCase
{
    public function testAPhotoMustBeAboutWhatWasAsked(): void
    {
        self::assertTrue(ImageSearch::isAbout('calm yoga room', 'File:Yoga practice in a cozy living room.jpg'));
        self::assertTrue(ImageSearch::isAbout('calm yoga room', 'Calm Yoga.png'));
        self::assertTrue(ImageSearch::isAbout('yoga studio', 'Elkedagyoga.nl yoga studio, Alphen aan den Rijn'));
    }

    public function testOneCommonWordIsNotEnough(): void
    {
        // "room" alone found a car in a showroom
        self::assertFalse(ImageSearch::isAbout('calm yoga room', 'Audi concept car in the showroom of the 2024 motor show'));
        self::assertFalse(ImageSearch::isAbout('yoga room', 'Sports car, red, parked in a room'));
    }

    public function testGenericWordsAndPluralsAreIgnored(): void
    {
        self::assertTrue(ImageSearch::isAbout('beautiful photos of mountains', 'Mountain ridge at sunrise, Tatra mountains'));
        self::assertTrue(ImageSearch::isAbout('photo', 'anything at all'), 'a query of generic words filters nothing');
    }
}
