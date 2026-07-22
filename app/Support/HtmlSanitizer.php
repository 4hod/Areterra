<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

// Minimal allowlist HTML sanitizer for rich-text fields (Policies body).
// The editor only ever produces basic formatting tags (SPEC.md: "Rich text
// editor: bold, italic, headings, lists") — everything else, and every
// attribute (href, src, onclick, style, etc.), is stripped rather than
// trusted. This exists because policy content is stored and later rendered
// with dangerouslySetInnerHTML; without server-side sanitisation, any user
// with manage_policies could inject script that runs for every viewer.
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote',
    ];

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        // Wrap in a body so DOMDocument doesn't invent <html><body> wrappers we'd have to strip.
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="ah-root">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $root = $dom->getElementById('ah-root');
        if (! $root) {
            return strip_tags($html);
        }

        self::sanitizeNode($dom, $root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    private static function sanitizeNode(DOMDocument $dom, DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                $node->removeChild($child);
                continue;
            }

            if (! in_array(strtolower($child->tagName), self::ALLOWED_TAGS, true)) {
                // Unwrap: keep the text/allowed children, drop the tag itself.
                self::sanitizeNode($dom, $child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            // Strip every attribute — the allowed tags never need one.
            while ($child->attributes && $child->attributes->length > 0) {
                $child->removeAttribute($child->attributes->item(0)->name);
            }

            self::sanitizeNode($dom, $child);
        }
    }
}
