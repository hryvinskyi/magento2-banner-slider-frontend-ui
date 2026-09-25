<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\Value;

use Magento\Framework\Escaper;

/**
 * An immutable set of HTML attributes of one element, rendered with a single rule for every value.
 *
 * Values:
 * - a string or an integer renders as `name="value"`, escaped;
 * - `true` renders the bare attribute (`hidden`, `playsinline`);
 * - `false` and `null` leave the attribute out, so a later `merge()` can switch an attribute off.
 *
 * Names must be plain attribute names (a letter first, then letters, digits, `_`, `:`, `.` or `-`). Event handler
 * attributes (`on…`) are rejected: behaviour belongs in scripts, which the storefront's content security policy
 * governs. URL-valued attributes (`href`, `src`, `poster`, and `data-hbs-src`, a `src` a script sets later) are escaped
 * as URLs, which also neutralises script URLs.
 *
 * @api
 */
class HtmlAttributes
{
    private const NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9_:.-]*$/';
    private const EVENT_HANDLER_PATTERN = '/^on/i';
    private const CLASS_ATTRIBUTE = 'class';
    private const URL_ATTRIBUTES = ['href' => true, 'src' => true, 'poster' => true, 'data-hbs-src' => true];

    /**
     * @var array<string, string|int|bool|null>
     */
    private readonly array $attributes;

    /**
     * @param array<string,string|int|bool|null> $attributes Attribute name => value
     * @throws \InvalidArgumentException When a name is not a plain attribute name or is an event handler
     */
    public function __construct(array $attributes = [])
    {
        foreach (array_keys($attributes) as $name) {
            $this->assertName((string)$name);
        }

        $this->attributes = $attributes;
    }

    /**
     * A copy with one attribute set, replacing any earlier value
     *
     * @param string $name
     * @param string|int|bool|null $value
     * @return HtmlAttributes
     * @throws \InvalidArgumentException When the name is not a plain attribute name or is an event handler
     */
    public function with(string $name, string|int|bool|null $value): HtmlAttributes
    {
        return new HtmlAttributes([$name => $value] + $this->attributes);
    }

    /**
     * A copy with the other set applied on top
     *
     * Class lists are joined (each class once, in first-seen order); every other attribute takes the other set's
     * value, including `false`/`null`, which removes it.
     *
     * @param HtmlAttributes $other
     * @return HtmlAttributes
     */
    public function merge(HtmlAttributes $other): HtmlAttributes
    {
        $merged = $this->attributes;
        foreach ($other->toArray() as $name => $value) {
            $merged[$name] = $name === self::CLASS_ATTRIBUTE
                ? $this->joinClasses($merged[$name] ?? null, $value)
                : $value;
        }

        return new HtmlAttributes($merged);
    }

    /**
     * Value of an attribute, or null when it is not set
     *
     * @param string $name
     * @return string|int|bool|null
     */
    public function get(string $name): string|int|bool|null
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Every attribute as set, including switched-off ones
     *
     * @return array<string,string|int|bool|null>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * The attributes as HTML, each preceded by a space, ready to follow the tag name
     *
     * @param Escaper $escaper
     * @return string
     */
    public function render(Escaper $escaper): string
    {
        $html = '';
        foreach ($this->attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $html .= ' ' . $name;
                continue;
            }

            $escaped = isset(self::URL_ATTRIBUTES[strtolower($name)])
                ? $escaper->escapeUrl((string)$value)
                : $escaper->escapeHtmlAttr((string)$value);
            $html .= ' ' . $name . '="' . $escaped . '"';
        }

        return $html;
    }

    /**
     * Join two class values into one space-separated list without duplicates
     *
     * @param string|int|bool|null $current
     * @param string|int|bool|null $added
     * @return string|null
     */
    private function joinClasses(string|int|bool|null $current, string|int|bool|null $added): ?string
    {
        $classes = [];
        foreach ([$current, $added] as $value) {
            if (!is_string($value)) {
                continue;
            }

            foreach (preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
                $classes[$class] = true;
            }
        }

        return $classes === [] ? null : implode(' ', array_keys($classes));
    }

    /**
     * Reject names that are not plain attribute names, and event handler attributes
     *
     * @param string $name
     * @return void
     * @throws \InvalidArgumentException
     */
    private function assertName(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid HTML attribute name.', $name));
        }

        if (preg_match(self::EVENT_HANDLER_PATTERN, $name) === 1) {
            throw new \InvalidArgumentException(sprintf(
                'The event handler attribute "%s" is not allowed; attach behaviour from a script instead.',
                $name
            ));
        }
    }
}
