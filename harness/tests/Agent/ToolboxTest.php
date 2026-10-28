<?php

declare(strict_types=1);

namespace App\Tests\Agent;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every tool the agents are given loads and describes itself: a broken description is a broken studio.
 */
final class ToolboxTest extends KernelTestCase
{
    public function testEveryAgentHasAllItsTools(): void
    {
        foreach (['studio', 'studio_fallback', 'studio_vision'] as $agent) {
            $tools = static::getContainer()->get('ai.fault_tolerant_toolbox.'.$agent)->getTools();
            $names = array_map(static fn ($tool): string => $tool->getName(), $tools);

            foreach (['propose_changes', 'create_widget', 'leaderboard', 'widget_status', 'translate_page'] as $name) {
                self::assertContains($name, $names, $agent);
            }
            foreach ($tools as $tool) {
                self::assertNotSame('', trim($tool->getDescription()), $tool->getName());
            }
        }
    }
}
