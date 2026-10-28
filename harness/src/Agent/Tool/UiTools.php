<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Studio\Turn;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('show_page', 'Take the owner to a page of the site (the browser navigates there). Use it to show what you talk about.', method: 'navigate')]
#[AsTool('point_at', 'Highlight elements on the page the owner is looking at (scrolls to them, outlines them, moves the Cadenza cursor).', method: 'highlight')]
final class UiTools
{
    public function __construct(private readonly Turn $turn)
    {
    }

    /**
     * @param string $path Page path, e.g. "/menu"
     */
    public function navigate(string $path): string
    {
        $this->turn->emit('ui', ['command' => 'navigate', 'path' => '/'.ltrim($path, '/')]);
        $this->turn->path = '/'.ltrim($path, '/');

        return 'The owner now sees '.$path.'.';
    }

    /**
     * @param list<string> $nodeIds Ids of nodes on the current page
     */
    public function highlight(array $nodeIds): string
    {
        $this->turn->emit('ui', ['command' => 'highlight', 'nodeIds' => array_values($nodeIds)]);

        return 'Highlighted.';
    }
}
