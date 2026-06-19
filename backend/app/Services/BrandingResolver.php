<?php

namespace App\Services;

class BrandingResolver
{
    /**
     * CSS custom properties for SPA shell (WP SimpleVPBot_Branding_Resolver parity).
     *
     * @param  array<string, mixed>  $branding
     * @return array<string, string>
     */
    public static function toCssVariables(array $branding): array
    {
        $prim = self::sanitizeHexColor((string) ($branding['themePrimary'] ?? $branding['branding_theme_primary'] ?? ''));
        $accent = self::sanitizeHexColor((string) ($branding['themeAccent'] ?? $branding['branding_theme_accent'] ?? ''));
        $vars = [];
        if ($prim !== '') {
            $vars['--svp-brand-primary'] = $prim;
        }
        if ($accent !== '') {
            $vars['--svp-brand-accent'] = $accent;
        }

        $extra = $branding['css_variables_custom'] ?? $branding['cssVariablesCustom'] ?? null;
        if (is_array($extra)) {
            foreach ($extra as $key => $val) {
                if (! is_string($key) || ! is_string($val) || $val === '') {
                    continue;
                }
                if (! str_starts_with($key, '--')) {
                    continue;
                }
                $vars[$key] = $val;
            }
        }

        return $vars;
    }

    /** @return array<string, mixed> */
    public static function packFromSettings(SettingsStore $settings): array
    {
        $primary = (string) $settings->get('branding_theme_primary', '');
        $accent = (string) $settings->get('branding_theme_accent', '');
        $custom = $settings->get('css_variables_custom', []);
        if (! is_array($custom)) {
            $custom = [];
        }

        return [
            'themePrimary' => $primary,
            'themeAccent' => $accent,
            'customDomain' => (string) $settings->get('branding_custom_domain', ''),
            'cssVariables' => self::toCssVariables([
                'themePrimary' => $primary,
                'themeAccent' => $accent,
                'css_variables_custom' => $custom,
            ]),
        ];
    }

    public static function sanitizeHexColor(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return strtolower($color);
        }

        return '';
    }
}
