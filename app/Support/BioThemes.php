<?php

namespace App\Support;

/**
 * Bio page design system: the font registry, default design, and the premium
 * prebuilt templates the builder offers (one-click apply).
 *
 * Design shape:
 *   headerLayout: classic|banner|row
 *   font:         key into FONTS
 *   textColor:    hex
 *   bg:           {type: color, color} | {type: gradient, gradStart, gradStop, gradAngle} | {type: image, image}
 *   button:       {color, textColor, style: fill|outline|soft, shape: rounded|pill|square, shadow: none|sm|lg, frosted: bool}
 */
class BioThemes
{
    public const FONTS = [
        'jakarta' => "'Plus Jakarta Sans', ui-sans-serif, sans-serif",
        'poppins' => "'Poppins', ui-sans-serif, sans-serif",
        'dm' => "'DM Sans', ui-sans-serif, sans-serif",
        'space' => "'Space Grotesk', ui-sans-serif, sans-serif",
        'lora' => "'Lora', Georgia, serif",
        'mono' => "ui-monospace, 'Cascadia Code', Menlo, monospace",
        'system' => 'system-ui, -apple-system, sans-serif',
    ];

    /** @return array<string, string> font key => display label */
    public static function fontOptions(): array
    {
        return [
            'jakarta' => 'Plus Jakarta', 'poppins' => 'Poppins', 'dm' => 'DM Sans',
            'space' => 'Space Grotesk', 'lora' => 'Lora (serif)', 'mono' => 'Monospace', 'system' => 'System',
        ];
    }

    public static function fontFamily(?string $key): string
    {
        return self::FONTS[$key] ?? self::FONTS['jakarta'];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'headerLayout' => 'classic',
            'font' => 'jakarta',
            'textColor' => '#0f172a',
            'bg' => ['type' => 'color', 'color' => '#f8fafc'],
            'button' => ['color' => '#0f172a', 'textColor' => '#ffffff', 'style' => 'soft', 'shape' => 'rounded', 'shadow' => 'sm', 'frosted' => false],
        ];
    }

    /** Merge a stored design over the defaults so the render is always complete. */
    public static function resolve(?array $design): array
    {
        $d = self::defaults();
        $design = $design ?: [];
        $d = array_merge($d, array_intersect_key($design, $d));
        $d['bg'] = array_merge($d['bg'], $design['bg'] ?? []);
        $d['button'] = array_merge($d['button'], $design['button'] ?? []);

        // Sanitize every value that gets interpolated into the public page's inline
        // CSS, so a crafted colour / URL can't break out of the <style> block (XSS).
        $d['textColor'] = self::color($d['textColor'] ?? null, '#0f172a');
        $d['bg']['color'] = self::color($d['bg']['color'] ?? null, '#f8fafc');
        $d['bg']['gradStart'] = self::color($d['bg']['gradStart'] ?? null, '#10b981');
        $d['bg']['gradStop'] = self::color($d['bg']['gradStop'] ?? null, '#0f766e');
        $d['bg']['gradAngle'] = max(0, min(360, (int) ($d['bg']['gradAngle'] ?? 160)));
        $d['bg']['image'] = self::cssUrl($d['bg']['image'] ?? null);
        $d['button']['color'] = self::color($d['button']['color'] ?? null, '#0f172a');
        $d['button']['textColor'] = self::color($d['button']['textColor'] ?? null, '#ffffff');
        if (! in_array($d['button']['style'] ?? '', ['fill', 'outline', 'soft'], true)) {
            $d['button']['style'] = 'soft';
        }

        return $d;
    }

    /** Only a hex colour passes; anything else falls back to a safe default. */
    private static function color(?string $value, string $default): string
    {
        $value = trim((string) $value);

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1 ? $value : $default;
    }

    /** Only a plain http(s) URL with no characters that could break url('...') passes. */
    private static function cssUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        return ($value !== '' && preg_match('~^https?://[^\s\'"()<>]+$~', $value) === 1) ? $value : null;
    }

    /** The 12 premium prebuilt templates. */
    public static function templates(): array
    {
        $g = fn ($a, $b, $angle = 160) => ['type' => 'gradient', 'gradStart' => $a, 'gradStop' => $b, 'gradAngle' => $angle];
        $c = fn ($color) => ['type' => 'color', 'color' => $color];
        $btn = fn ($color, $text, $style = 'soft', $shape = 'rounded', $shadow = 'sm', $frosted = false) => compact('color', 'text', 'style', 'shape', 'shadow', 'frosted') + ['textColor' => $text];

        return [
            ['key' => 'minimal', 'name' => 'Minimal', 'headerLayout' => 'classic', 'font' => 'jakarta', 'textColor' => '#0f172a', 'bg' => $c('#ffffff'), 'button' => ['color' => '#0f172a', 'textColor' => '#ffffff', 'style' => 'soft', 'shape' => 'rounded', 'shadow' => 'sm', 'frosted' => false]],
            ['key' => 'midnight', 'name' => 'Midnight', 'headerLayout' => 'classic', 'font' => 'space', 'textColor' => '#f8fafc', 'bg' => $c('#0b1220'), 'button' => ['color' => '#1e293b', 'textColor' => '#f8fafc', 'style' => 'soft', 'shape' => 'pill', 'shadow' => 'none', 'frosted' => false]],
            ['key' => 'emerald', 'name' => 'Emerald', 'headerLayout' => 'banner', 'font' => 'jakarta', 'textColor' => '#ffffff', 'bg' => $g('#10b981', '#0f766e'), 'button' => ['color' => '#ffffff', 'textColor' => '#065f46', 'style' => 'soft', 'shape' => 'rounded', 'shadow' => 'sm', 'frosted' => false]],
            ['key' => 'sunset', 'name' => 'Sunset', 'headerLayout' => 'classic', 'font' => 'poppins', 'textColor' => '#ffffff', 'bg' => $g('#f97316', '#ec4899'), 'button' => ['color' => '#ffffff', 'textColor' => '#9d174d', 'style' => 'soft', 'shape' => 'pill', 'shadow' => 'lg', 'frosted' => true]],
            ['key' => 'ocean', 'name' => 'Ocean', 'headerLayout' => 'banner', 'font' => 'dm', 'textColor' => '#ffffff', 'bg' => $g('#0ea5e9', '#2563eb'), 'button' => ['color' => '#ffffff', 'textColor' => '#1e3a8a', 'style' => 'soft', 'shape' => 'rounded', 'shadow' => 'sm', 'frosted' => false]],
            ['key' => 'mono', 'name' => 'Mono', 'headerLayout' => 'classic', 'font' => 'space', 'textColor' => '#ffffff', 'bg' => $c('#000000'), 'button' => ['color' => '#ffffff', 'textColor' => '#ffffff', 'style' => 'outline', 'shape' => 'square', 'shadow' => 'none', 'frosted' => false]],
            ['key' => 'blush', 'name' => 'Blush', 'headerLayout' => 'classic', 'font' => 'poppins', 'textColor' => '#831843', 'bg' => $c('#fdf2f8'), 'button' => ['color' => '#db2777', 'textColor' => '#ffffff', 'style' => 'fill', 'shape' => 'pill', 'shadow' => 'sm', 'frosted' => false]],
            ['key' => 'neon', 'name' => 'Neon', 'headerLayout' => 'classic', 'font' => 'mono', 'textColor' => '#d1fae5', 'bg' => $c('#0a0a0a'), 'button' => ['color' => '#22c55e', 'textColor' => '#052e16', 'style' => 'fill', 'shape' => 'rounded', 'shadow' => 'lg', 'frosted' => false]],
            ['key' => 'aurora', 'name' => 'Aurora', 'headerLayout' => 'banner', 'font' => 'jakarta', 'textColor' => '#ffffff', 'bg' => $g('#7c3aed', '#4f46e5'), 'button' => ['color' => '#ffffff', 'textColor' => '#4c1d95', 'style' => 'soft', 'shape' => 'rounded', 'shadow' => 'sm', 'frosted' => true]],
            ['key' => 'cream', 'name' => 'Editorial', 'headerLayout' => 'row', 'font' => 'lora', 'textColor' => '#44403c', 'bg' => $c('#f4f1ea'), 'button' => ['color' => '#c2410c', 'textColor' => '#ffffff', 'style' => 'fill', 'shape' => 'rounded', 'shadow' => 'none', 'frosted' => false]],
            ['key' => 'slate', 'name' => 'Corporate', 'headerLayout' => 'row', 'font' => 'dm', 'textColor' => '#0f172a', 'bg' => $c('#f8fafc'), 'button' => ['color' => '#2563eb', 'textColor' => '#ffffff', 'style' => 'fill', 'shape' => 'rounded', 'shadow' => 'sm', 'frosted' => false]],
            ['key' => 'bubblegum', 'name' => 'Bubblegum', 'headerLayout' => 'classic', 'font' => 'poppins', 'textColor' => '#ffffff', 'bg' => $g('#f472b6', '#a78bfa'), 'button' => ['color' => '#ffffff', 'textColor' => '#9333ea', 'style' => 'soft', 'shape' => 'pill', 'shadow' => 'sm', 'frosted' => false]],
        ];
    }
}
