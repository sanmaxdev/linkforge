<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Conservative allowlist HTML sanitizer for the bio "HTML" block. Strips every
 * tag and attribute not on the allowlist (scripts, styles, iframes, on* handlers,
 * javascript: URLs), so a page owner's custom markup can't become stored XSS.
 * Pure ext-dom, no Composer dependency (works on shared hosting).
 */
class HtmlSanitizer
{
    /** tag => allowed attributes */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'b' => [], 'strong' => [], 'i' => [], 'em' => [],
        'u' => [], 's' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'span' => [], 'div' => [], 'hr' => [], 'code' => [], 'pre' => [],
        'a' => ['href'], 'img' => ['src', 'alt'],
    ];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();

        $root = $doc->getElementById('__root');
        if (! $root) {
            return '';
        }

        self::sanitizeChildren($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function sanitizeChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (! array_key_exists($tag, self::ALLOWED)) {
                    $node->removeChild($child); // drop element and its subtree

                    continue;
                }
                self::cleanAttributes($child, self::ALLOWED[$tag]);
                self::sanitizeChildren($child);
            } elseif ($child instanceof DOMComment) {
                $node->removeChild($child);
            }
            // Text nodes stay; saveHTML escapes them.
        }
    }

    private static function cleanAttributes(DOMElement $el, array $allowed): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);

            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->name);

                continue;
            }

            if (in_array($name, ['href', 'src'], true)) {
                $val = trim($attr->value);
                $ok = $name === 'src'
                    ? (bool) preg_match('~^https?:~i', $val)
                    : (bool) preg_match('~^(https?:|mailto:|tel:|#|/)~i', $val);

                if (! $ok) {
                    $el->removeAttribute($attr->name);
                }
            }
        }

        if (strtolower($el->tagName) === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener nofollow');
        }
    }
}
