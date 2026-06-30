<?php

declare(strict_types=1);

namespace Elementary;

class HtmlSanitizer
{
  private const ALLOWED_TAGS = [
    'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins',
    'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'blockquote', 'pre', 'code', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    'hr', 'span', 'div', 'sub', 'sup', 'figure', 'figcaption',
  ];

  private const GLOBAL_ATTRS = ['class', 'id', 'style', 'title'];

  private const TAG_ATTRS = [
    'a'   => ['href', 'target', 'rel'],
    'img' => ['src', 'alt', 'width', 'height'],
    'td'  => ['colspan', 'rowspan'],
    'th'  => ['colspan', 'rowspan'],
  ];

  public static function sanitize(string $html): string
  {
    $html = trim($html);
    if ($html === '') {
      return '';
    }

    $previous = libxml_use_internal_errors(true);
    $doc = new \DOMDocument('1.0', 'UTF-8');
    $wrapped = '<?xml encoding="UTF-8"><div>' . $html . '</div>';
    $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $doc->getElementsByTagName('div')->item(0);
    if (!$root) {
      return '';
    }

    self::sanitizeNode($root);

    $result = '';
    foreach ($root->childNodes as $child) {
      $result .= $doc->saveHTML($child);
    }

    return $result;
  }

  private static function sanitizeNode(\DOMNode $node): void
  {
    if ($node->nodeType !== XML_ELEMENT_NODE) {
      return;
    }

    /** @var \DOMElement $element */
    $element = $node;
    $tag = strtolower($element->tagName);

    if (!in_array($tag, self::ALLOWED_TAGS, true)) {
      self::unwrapElement($element);
      return;
    }

    $allowedAttrs = array_merge(self::GLOBAL_ATTRS, self::TAG_ATTRS[$tag] ?? []);
    $toRemove = [];
    foreach ($element->attributes as $attr) {
      $name = strtolower($attr->name);
      if (!in_array($name, $allowedAttrs, true) || self::isUnsafeAttributeValue($name, $attr->value)) {
        $toRemove[] = $name;
      }
    }
    foreach ($toRemove as $name) {
      $element->removeAttribute($name);
    }

    if ($tag === 'a') {
      self::sanitizeLink($element);
    }
    if ($tag === 'img') {
      self::sanitizeImage($element);
    }

    $children = [];
    foreach ($element->childNodes as $child) {
      $children[] = $child;
    }
    foreach ($children as $child) {
      self::sanitizeNode($child);
    }
  }

  private static function unwrapElement(\DOMElement $element): void
  {
    $parent = $element->parentNode;
    if (!$parent) {
      return;
    }

    while ($element->firstChild) {
      $parent->insertBefore($element->firstChild, $element);
    }
    $parent->removeChild($element);
  }

  private static function isUnsafeAttributeValue(string $name, string $value): bool
  {
    $value = trim($value);
    if ($value === '') {
      return false;
    }

    if (preg_match('/^\s*(javascript|data|vbscript):/i', $value)) {
      return true;
    }

    if ($name === 'style' && preg_match('/expression\s*\(|url\s*\(\s*["\']?\s*javascript:/i', $value)) {
      return true;
    }

    if (preg_match('/^on/i', $name)) {
      return true;
    }

    return false;
  }

  private static function sanitizeLink(\DOMElement $element): void
  {
    $href = trim($element->getAttribute('href'));
    if ($href === '' || preg_match('/^\s*(javascript|data|vbscript):/i', $href)) {
      $element->removeAttribute('href');
      return;
    }

    if (!preg_match('~^(https?://|/|#|mailto:)~i', $href)) {
      $element->removeAttribute('href');
    }

    $target = strtolower($element->getAttribute('target'));
    if ($target === '_blank') {
      $element->setAttribute('rel', 'noopener noreferrer');
    }
  }

  private static function sanitizeImage(\DOMElement $element): void
  {
    $src = trim($element->getAttribute('src'));
    if ($src === '' || preg_match('/^\s*(javascript|data|vbscript):/i', $src)) {
      $element->removeAttribute('src');
    }
  }
}
