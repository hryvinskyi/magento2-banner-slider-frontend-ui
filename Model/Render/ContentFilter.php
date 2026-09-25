<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\Render;

use Hryvinskyi\BannerSliderFrontendUi\Api\Render\ContentFilterInterface;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Processes the CMS directives of banner content with the CMS block filter.
 *
 * Content may place a slider widget, and that slider's banners may do the same, back to the first slider. A depth
 * counter bounds that nesting: content filtered while more than the allowed number of banner contents are already
 * being filtered comes back empty. The counter lives for one request and is cleared after it.
 */
class ContentFilter implements ContentFilterInterface, ResetAfterRequestInterface
{
    /**
     * @var int Banner contents being filtered right now, outermost included
     */
    private int $depth = 0;

    /**
     * @param FilterProvider $filterProvider
     * @param LoggerInterface $logger
     * @param int $maxDepth Deepest nesting of banner contents that is still filtered
     */
    public function __construct(
        private readonly FilterProvider $filterProvider,
        private readonly LoggerInterface $logger,
        private readonly int $maxDepth = 2
    ) {
    }

    /**
     * @inheritDoc
     */
    public function filter(?string $content): string
    {
        if ($content === null || trim($content) === '') {
            return '';
        }

        if ($this->depth >= $this->maxDepth) {
            $this->logger->warning('Banner slider: banner content nests sliders too deeply and was left out.', [
                'max_depth' => $this->maxDepth,
            ]);

            return '';
        }

        $this->depth++;
        try {
            return $this->filterProvider->getBlockFilter()->filter($content);
        } catch (\Exception $e) {
            $this->logger->error('Banner slider: banner content could not be filtered and was left out.', [
                'exception' => $e,
            ]);

            return '';
        } finally {
            $this->depth--;
        }
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->depth = 0;
    }
}
