<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\Render;

use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\SlideRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;

/**
 * Renders a slide with the first registered renderer that supports the banner's type.
 *
 * The renderers come from `di.xml` in the order of their items; a module adds a banner type's renderer, or replaces
 * one, by adding an item (with a `sortOrder` to run ahead of an existing one). A banner type no renderer supports
 * fails loudly with an exception instead of rendering an empty slide.
 */
class CompositeSlideRenderer implements SlideRendererInterface
{
    /**
     * @var list<SlideRendererInterface>
     */
    private readonly array $renderers;

    /**
     * @param array<string,SlideRendererInterface> $renderers
     */
    public function __construct(array $renderers = [])
    {
        $this->renderers = array_values($renderers);
    }

    /**
     * @inheritDoc
     */
    public function supports(BannerType $type): bool
    {
        return $this->find($type) !== null;
    }

    /**
     * @inheritDoc
     */
    public function render(SlideContext $context): string
    {
        $type = $context->getBanner()->getType();
        $renderer = $this->find($type);
        if ($renderer === null) {
            throw new \InvalidArgumentException(
                sprintf('No slide renderer is registered for banner type "%s".', $type->label())
            );
        }

        return $renderer->render($context);
    }

    /**
     * The first renderer that supports the type
     *
     * @param BannerType $type
     * @return SlideRendererInterface|null
     */
    private function find(BannerType $type): ?SlideRendererInterface
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($type)) {
                return $renderer;
            }
        }

        return null;
    }
}
