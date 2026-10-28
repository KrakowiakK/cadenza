<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Mcp\Agent;

use Cadenza\Runtime\Entity\Widget;
use Cadenza\Runtime\Widget\WidgetLibraries;
use Cadenza\Runtime\Widget\Widgets;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

/**
 * Widgets: games, 3D scenes, animations and small tools the agent writes with its own code.
 */
final class WidgetTools
{
    public function __construct(
        private readonly Widgets $widgets,
        private readonly WidgetLibraries $libraries,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'widget_define', description: 'Create a widget (or a new version of one): HTML with <style> and <script type="module"> that runs in a sandboxed frame filling the frame (100% width and height). No network, no cookies; the libraries of widget_libraries are imported by name (import * as THREE from "three"); localStorage works and is kept per visitor. Site colours as CSS variables: var(--primary), var(--background), var(--foreground), var(--muted), var(--accent); fonts var(--cz-font-heading), var(--cz-font-body). Photos of the site by their /media/… address. Place it with the "widget" block or a hero\'s "widget".')]
    public function define(string $name, string $label, string $description, string $code, ?string $shape = null): array
    {
        $result = $this->widgets->save($name, $label, $description, $code, $shape);
        if (null === $result['widget']) {
            return ['ok' => false, 'errors' => $result['errors']];
        }

        return ['ok' => true, 'name' => $result['widget']->getName(), 'version' => $result['widget']->getVersion(), 'shape' => $result['widget']->getShape(), 'notes' => $result['notes']];
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[McpTool(name: 'widgets_list', description: 'Widgets of this site, with the last error a browser reported for each (null when it runs fine).', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function list(): array
    {
        return array_map(static fn (Widget $w): array => [
            'name' => $w->getName(), 'label' => $w->getLabel(), 'description' => $w->getDescription(), 'version' => $w->getVersion(), 'shape' => $w->getShape(),
            'size' => \strlen($w->getCode()), 'lastError' => $w->getLastError(),
        ], $this->widgets->all());
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'widget_code', description: 'The code of one widget, to change it.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function code(string $name): array
    {
        $widget = $this->widgets->get($name);

        return null === $widget ? ['error' => 'No widget "'.$name.'".'] : ['name' => $name, 'description' => $widget->getDescription(), 'version' => $widget->getVersion(), 'shape' => $widget->getShape(), 'lastError' => $widget->getLastError(), 'code' => $widget->getCode()];
    }

    /**
     * @return array<string, string>
     */
    #[McpTool(name: 'widget_libraries', description: 'JavaScript libraries a widget can import by name, with versions.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function libraries(): array
    {
        return $this->libraries->all();
    }
}
