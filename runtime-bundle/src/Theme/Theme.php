<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Theme;

/**
 * Design tokens of a site: a preset palette, optional token overrides, fonts, corner radius and light/dark mode.
 * Tokens are the shadcn CSS variables, so every Toolkit component follows the theme.
 */
final class Theme
{
    public const TOKENS = ['background', 'foreground', 'card', 'card-foreground', 'primary', 'primary-foreground', 'secondary', 'secondary-foreground', 'muted', 'muted-foreground', 'accent', 'accent-foreground', 'border', 'ring'];

    public const FONTS = ['Inter', 'DM Sans', 'Outfit', 'Poppins', 'Nunito', 'Space Grotesk', 'Playfair Display', 'Fraunces', 'Lora', 'Merriweather', 'Libre Baskerville', 'Cormorant Garamond', 'Caveat', 'JetBrains Mono'];

    /**
     * Design directions: one decision that sets the character of the whole site — palette, type, shape and the
     * treatment of headings, cards, sections and motion (see cadenza.css, [data-cz-style]).
     *
     * @var array<string, array{label: string, preset: string, fontHeading: string, fontBody: string, radius: string}>
     */
    public const DIRECTIONS = [
        'minimal' => ['label' => 'Minimal — calm, lots of white space, thin lines, nothing loud', 'preset' => 'neutral', 'fontHeading' => 'Inter', 'fontBody' => 'Inter', 'radius' => 'md'],
        'editorial' => ['label' => 'Editorial — magazine feel, large serif headlines, hairline rules, flat cards', 'preset' => 'editorial', 'fontHeading' => 'Fraunces', 'fontBody' => 'Inter', 'radius' => 'none'],
        'warm' => ['label' => 'Warm craft — soft rounded shapes, cream tones, gentle shadows (cafés, bakeries, crafts)', 'preset' => 'warm', 'fontHeading' => 'Fraunces', 'fontBody' => 'DM Sans', 'radius' => 'lg'],
        'bold' => ['label' => 'Bold — big uppercase headlines, strong contrast bands, pill buttons (events, sport, music)', 'preset' => 'midnight', 'fontHeading' => 'Space Grotesk', 'fontBody' => 'Inter', 'radius' => 'full'],
        'elegant' => ['label' => 'Elegant — refined serif, light weights, wide letter spacing, centred (weddings, beauty, hotels)', 'preset' => 'rose', 'fontHeading' => 'Cormorant Garamond', 'fontBody' => 'Lora', 'radius' => 'none'],
        'fresh' => ['label' => 'Fresh — friendly rounded type, colour accents, playful cards (kids, education, outdoors)', 'preset' => 'ocean', 'fontHeading' => 'Outfit', 'fontBody' => 'Nunito', 'radius' => 'lg'],
    ];

    public const RADII = ['none' => '0rem', 'sm' => '0.3rem', 'md' => '0.625rem', 'lg' => '1rem', 'full' => '1.5rem'];

    /** @var array<string, array{label: string, light: array<string, string>, dark: array<string, string>}> */
    public const PRESETS = [
        'neutral' => [
            'label' => 'Neutral — clean black and white',
            'light' => ['background' => 'oklch(1 0 0)', 'foreground' => 'oklch(0.145 0 0)', 'card' => 'oklch(1 0 0)', 'card-foreground' => 'oklch(0.145 0 0)', 'primary' => 'oklch(0.205 0 0)', 'primary-foreground' => 'oklch(0.985 0 0)', 'secondary' => 'oklch(0.97 0 0)', 'secondary-foreground' => 'oklch(0.205 0 0)', 'muted' => 'oklch(0.97 0 0)', 'muted-foreground' => 'oklch(0.556 0 0)', 'accent' => 'oklch(0.97 0 0)', 'accent-foreground' => 'oklch(0.205 0 0)', 'border' => 'oklch(0.922 0 0)', 'ring' => 'oklch(0.708 0 0)'],
            'dark' => ['background' => 'oklch(0.145 0 0)', 'foreground' => 'oklch(0.985 0 0)', 'card' => 'oklch(0.205 0 0)', 'card-foreground' => 'oklch(0.985 0 0)', 'primary' => 'oklch(0.922 0 0)', 'primary-foreground' => 'oklch(0.205 0 0)', 'secondary' => 'oklch(0.269 0 0)', 'secondary-foreground' => 'oklch(0.985 0 0)', 'muted' => 'oklch(0.269 0 0)', 'muted-foreground' => 'oklch(0.708 0 0)', 'accent' => 'oklch(0.269 0 0)', 'accent-foreground' => 'oklch(0.985 0 0)', 'border' => 'oklch(1 0 0 / 10%)', 'ring' => 'oklch(0.556 0 0)'],
        ],
        'warm' => [
            'label' => 'Warm — cream, coffee and terracotta (cafés, crafts, food)',
            'light' => ['background' => 'oklch(0.98 0.012 80)', 'foreground' => 'oklch(0.27 0.03 50)', 'card' => 'oklch(0.995 0.008 80)', 'card-foreground' => 'oklch(0.27 0.03 50)', 'primary' => 'oklch(0.55 0.13 45)', 'primary-foreground' => 'oklch(0.98 0.01 80)', 'secondary' => 'oklch(0.93 0.03 75)', 'secondary-foreground' => 'oklch(0.32 0.04 50)', 'muted' => 'oklch(0.94 0.02 78)', 'muted-foreground' => 'oklch(0.5 0.03 55)', 'accent' => 'oklch(0.88 0.06 85)', 'accent-foreground' => 'oklch(0.3 0.04 50)', 'border' => 'oklch(0.88 0.025 75)', 'ring' => 'oklch(0.55 0.13 45)'],
            'dark' => ['background' => 'oklch(0.2 0.02 50)', 'foreground' => 'oklch(0.95 0.015 80)', 'card' => 'oklch(0.24 0.025 50)', 'card-foreground' => 'oklch(0.95 0.015 80)', 'primary' => 'oklch(0.68 0.13 50)', 'primary-foreground' => 'oklch(0.18 0.02 50)', 'secondary' => 'oklch(0.3 0.03 50)', 'secondary-foreground' => 'oklch(0.95 0.015 80)', 'muted' => 'oklch(0.28 0.025 50)', 'muted-foreground' => 'oklch(0.72 0.03 70)', 'accent' => 'oklch(0.35 0.05 60)', 'accent-foreground' => 'oklch(0.95 0.015 80)', 'border' => 'oklch(0.33 0.03 50)', 'ring' => 'oklch(0.68 0.13 50)'],
        ],
        'ocean' => [
            'label' => 'Ocean — fresh blues (travel, tech, health)',
            'light' => ['background' => 'oklch(0.99 0.005 230)', 'foreground' => 'oklch(0.22 0.04 250)', 'card' => 'oklch(1 0 0)', 'card-foreground' => 'oklch(0.22 0.04 250)', 'primary' => 'oklch(0.52 0.15 245)', 'primary-foreground' => 'oklch(0.98 0.01 230)', 'secondary' => 'oklch(0.95 0.02 220)', 'secondary-foreground' => 'oklch(0.3 0.05 245)', 'muted' => 'oklch(0.96 0.015 225)', 'muted-foreground' => 'oklch(0.5 0.04 240)', 'accent' => 'oklch(0.9 0.06 195)', 'accent-foreground' => 'oklch(0.28 0.05 230)', 'border' => 'oklch(0.9 0.02 225)', 'ring' => 'oklch(0.52 0.15 245)'],
            'dark' => ['background' => 'oklch(0.18 0.03 250)', 'foreground' => 'oklch(0.96 0.01 230)', 'card' => 'oklch(0.22 0.035 250)', 'card-foreground' => 'oklch(0.96 0.01 230)', 'primary' => 'oklch(0.7 0.13 230)', 'primary-foreground' => 'oklch(0.18 0.03 250)', 'secondary' => 'oklch(0.28 0.04 250)', 'secondary-foreground' => 'oklch(0.96 0.01 230)', 'muted' => 'oklch(0.26 0.035 250)', 'muted-foreground' => 'oklch(0.72 0.03 235)', 'accent' => 'oklch(0.35 0.07 210)', 'accent-foreground' => 'oklch(0.96 0.01 230)', 'border' => 'oklch(0.3 0.04 250)', 'ring' => 'oklch(0.7 0.13 230)'],
        ],
        'forest' => [
            'label' => 'Forest — calm greens (nature, outdoors, wellness)',
            'light' => ['background' => 'oklch(0.985 0.008 140)', 'foreground' => 'oklch(0.24 0.035 150)', 'card' => 'oklch(0.995 0.005 140)', 'card-foreground' => 'oklch(0.24 0.035 150)', 'primary' => 'oklch(0.45 0.1 155)', 'primary-foreground' => 'oklch(0.98 0.01 140)', 'secondary' => 'oklch(0.94 0.025 140)', 'secondary-foreground' => 'oklch(0.3 0.05 150)', 'muted' => 'oklch(0.95 0.02 140)', 'muted-foreground' => 'oklch(0.5 0.035 150)', 'accent' => 'oklch(0.9 0.06 120)', 'accent-foreground' => 'oklch(0.28 0.05 150)', 'border' => 'oklch(0.89 0.025 140)', 'ring' => 'oklch(0.45 0.1 155)'],
            'dark' => ['background' => 'oklch(0.19 0.025 155)', 'foreground' => 'oklch(0.95 0.015 140)', 'card' => 'oklch(0.23 0.03 155)', 'card-foreground' => 'oklch(0.95 0.015 140)', 'primary' => 'oklch(0.7 0.12 150)', 'primary-foreground' => 'oklch(0.18 0.03 155)', 'secondary' => 'oklch(0.29 0.035 155)', 'secondary-foreground' => 'oklch(0.95 0.015 140)', 'muted' => 'oklch(0.27 0.03 155)', 'muted-foreground' => 'oklch(0.72 0.03 145)', 'accent' => 'oklch(0.35 0.06 130)', 'accent-foreground' => 'oklch(0.95 0.015 140)', 'border' => 'oklch(0.31 0.035 155)', 'ring' => 'oklch(0.7 0.12 150)'],
        ],
        'rose' => [
            'label' => 'Rose — soft pinks (beauty, weddings, fashion)',
            'light' => ['background' => 'oklch(0.99 0.006 10)', 'foreground' => 'oklch(0.25 0.03 10)', 'card' => 'oklch(1 0 0)', 'card-foreground' => 'oklch(0.25 0.03 10)', 'primary' => 'oklch(0.58 0.17 10)', 'primary-foreground' => 'oklch(0.99 0.005 10)', 'secondary' => 'oklch(0.95 0.02 10)', 'secondary-foreground' => 'oklch(0.32 0.05 10)', 'muted' => 'oklch(0.96 0.015 10)', 'muted-foreground' => 'oklch(0.52 0.04 10)', 'accent' => 'oklch(0.92 0.045 20)', 'accent-foreground' => 'oklch(0.3 0.05 10)', 'border' => 'oklch(0.91 0.02 10)', 'ring' => 'oklch(0.58 0.17 10)'],
            'dark' => ['background' => 'oklch(0.19 0.025 10)', 'foreground' => 'oklch(0.96 0.01 10)', 'card' => 'oklch(0.23 0.03 10)', 'card-foreground' => 'oklch(0.96 0.01 10)', 'primary' => 'oklch(0.72 0.14 10)', 'primary-foreground' => 'oklch(0.19 0.025 10)', 'secondary' => 'oklch(0.29 0.04 10)', 'secondary-foreground' => 'oklch(0.96 0.01 10)', 'muted' => 'oklch(0.27 0.03 10)', 'muted-foreground' => 'oklch(0.74 0.03 10)', 'accent' => 'oklch(0.34 0.06 15)', 'accent-foreground' => 'oklch(0.96 0.01 10)', 'border' => 'oklch(0.31 0.035 10)', 'ring' => 'oklch(0.72 0.14 10)'],
        ],
        'editorial' => [
            'label' => 'Editorial — paper and ink with a red accent (magazines, blogs, portfolios)',
            'light' => ['background' => 'oklch(0.975 0.006 90)', 'foreground' => 'oklch(0.18 0.005 90)', 'card' => 'oklch(0.99 0.004 90)', 'card-foreground' => 'oklch(0.18 0.005 90)', 'primary' => 'oklch(0.18 0.005 90)', 'primary-foreground' => 'oklch(0.975 0.006 90)', 'secondary' => 'oklch(0.93 0.008 90)', 'secondary-foreground' => 'oklch(0.18 0.005 90)', 'muted' => 'oklch(0.94 0.008 90)', 'muted-foreground' => 'oklch(0.48 0.01 90)', 'accent' => 'oklch(0.6 0.2 28)', 'accent-foreground' => 'oklch(0.98 0.005 90)', 'border' => 'oklch(0.86 0.01 90)', 'ring' => 'oklch(0.6 0.2 28)'],
            'dark' => ['background' => 'oklch(0.17 0.005 90)', 'foreground' => 'oklch(0.95 0.008 90)', 'card' => 'oklch(0.21 0.006 90)', 'card-foreground' => 'oklch(0.95 0.008 90)', 'primary' => 'oklch(0.95 0.008 90)', 'primary-foreground' => 'oklch(0.17 0.005 90)', 'secondary' => 'oklch(0.27 0.006 90)', 'secondary-foreground' => 'oklch(0.95 0.008 90)', 'muted' => 'oklch(0.25 0.006 90)', 'muted-foreground' => 'oklch(0.7 0.01 90)', 'accent' => 'oklch(0.66 0.19 28)', 'accent-foreground' => 'oklch(0.98 0.005 90)', 'border' => 'oklch(0.3 0.008 90)', 'ring' => 'oklch(0.66 0.19 28)'],
        ],
        'midnight' => [
            'label' => 'Midnight — deep navy with violet glow (music, events, gaming)',
            'light' => ['background' => 'oklch(0.985 0.006 285)', 'foreground' => 'oklch(0.22 0.04 285)', 'card' => 'oklch(1 0 0)', 'card-foreground' => 'oklch(0.22 0.04 285)', 'primary' => 'oklch(0.5 0.2 290)', 'primary-foreground' => 'oklch(0.98 0.01 285)', 'secondary' => 'oklch(0.95 0.02 285)', 'secondary-foreground' => 'oklch(0.3 0.06 285)', 'muted' => 'oklch(0.96 0.015 285)', 'muted-foreground' => 'oklch(0.5 0.04 285)', 'accent' => 'oklch(0.9 0.07 320)', 'accent-foreground' => 'oklch(0.28 0.06 290)', 'border' => 'oklch(0.9 0.02 285)', 'ring' => 'oklch(0.5 0.2 290)'],
            'dark' => ['background' => 'oklch(0.16 0.04 285)', 'foreground' => 'oklch(0.96 0.01 285)', 'card' => 'oklch(0.21 0.05 285)', 'card-foreground' => 'oklch(0.96 0.01 285)', 'primary' => 'oklch(0.72 0.17 295)', 'primary-foreground' => 'oklch(0.16 0.04 285)', 'secondary' => 'oklch(0.27 0.05 285)', 'secondary-foreground' => 'oklch(0.96 0.01 285)', 'muted' => 'oklch(0.25 0.045 285)', 'muted-foreground' => 'oklch(0.74 0.04 285)', 'accent' => 'oklch(0.4 0.12 320)', 'accent-foreground' => 'oklch(0.96 0.01 285)', 'border' => 'oklch(0.3 0.05 285)', 'ring' => 'oklch(0.72 0.17 295)'],
        ],
    ];

    /**
     * @param array<string, mixed> $theme
     *
     * @return array<string, string> CSS variables for :root
     */
    public static function cssVariables(array $theme): array
    {
        $preset = self::PRESETS[$theme['preset'] ?? 'neutral'] ?? self::PRESETS['neutral'];
        $mode = 'dark' === ($theme['mode'] ?? 'light') ? 'dark' : 'light';
        $vars = [];
        foreach (array_merge($preset[$mode], $theme['tokens'] ?? []) as $token => $value) {
            $vars['--'.$token] = $value;
        }
        $vars['--radius'] = self::RADII[$theme['radius'] ?? 'md'] ?? self::RADII['md'];
        $vars['--cz-font-heading'] = '"'.($theme['fontHeading'] ?? 'Inter').'", ui-sans-serif, system-ui, sans-serif';
        $vars['--cz-font-body'] = '"'.($theme['fontBody'] ?? 'Inter').'", ui-sans-serif, system-ui, sans-serif';
        $vars['--cz-font-heading-serif'] = \in_array($theme['fontHeading'] ?? 'Inter', ['Playfair Display', 'Fraunces', 'Lora', 'Merriweather', 'Libre Baskerville', 'Cormorant Garamond'], true) ? '1' : '0';

        return $vars;
    }

    /**
     * @param array<string, mixed> $theme
     */
    public static function googleFontsUrl(array $theme): string
    {
        $families = array_unique([$theme['fontHeading'] ?? 'Inter', $theme['fontBody'] ?? 'Inter']);

        return 'https://fonts.googleapis.com/css2?'.implode('&', array_map(
            static fn (string $f): string => 'family='.str_replace(' ', '+', $f).':ital,wght@0,300;0,400;0,500;0,600;0,700;1,400',
            $families,
        )).'&display=swap';
    }
}
