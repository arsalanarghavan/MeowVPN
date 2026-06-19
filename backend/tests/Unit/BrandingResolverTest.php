<?php

namespace Tests\Unit;

use App\Services\BrandingResolver;
use PHPUnit\Framework\TestCase;

class BrandingResolverTest extends TestCase
{
    public function test_to_css_variables_from_theme_colors(): void
    {
        $vars = BrandingResolver::toCssVariables([
            'themePrimary' => '#2563eb',
            'themeAccent' => '#7c3aed',
        ]);

        $this->assertSame('#2563eb', $vars['--svp-brand-primary']);
        $this->assertSame('#7c3aed', $vars['--svp-brand-accent']);
    }

    public function test_merges_custom_css_variables(): void
    {
        $vars = BrandingResolver::toCssVariables([
            'themePrimary' => '#111111',
            'css_variables_custom' => [
                '--svp-sidebar-width' => '280px',
                'invalid' => 'skip',
            ],
        ]);

        $this->assertSame('280px', $vars['--svp-sidebar-width']);
        $this->assertArrayNotHasKey('invalid', $vars);
    }
}
