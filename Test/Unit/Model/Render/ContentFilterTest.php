<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\Render;

use Hryvinskyi\BannerSliderFrontendUi\Model\Render\ContentFilter;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Filter\Template as TemplateFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ContentFilter::class)]
class ContentFilterTest extends TestCase
{
    /**
     * @var TemplateFilter&MockObject
     */
    private MockObject $templateFilter;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var ContentFilter
     */
    private ContentFilter $contentFilter;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->templateFilter = $this->createMock(TemplateFilter::class);
        $filterProvider = $this->createMock(FilterProvider::class);
        $filterProvider->method('getBlockFilter')->willReturn($this->templateFilter);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->contentFilter = new ContentFilter($filterProvider, $this->logger, 2);
    }

    /**
     * Directives are processed by the CMS block filter
     *
     * @return void
     */
    public function testFiltersContent(): void
    {
        $this->templateFilter->method('filter')->with('<p>{{store url=""}}</p>')->willReturn('<p>https://shop/</p>');

        self::assertSame('<p>https://shop/</p>', $this->contentFilter->filter('<p>{{store url=""}}</p>'));
    }

    /**
     * Empty content is never sent to the filter
     *
     * @return void
     */
    public function testEmptyContentIsEmpty(): void
    {
        $this->templateFilter->expects(self::never())->method('filter');

        self::assertSame('', $this->contentFilter->filter(null));
        self::assertSame('', $this->contentFilter->filter("  \n"));
    }

    /**
     * A failing filter logs and returns nothing, never the raw directives
     *
     * @return void
     */
    public function testFailureReturnsEmptyAndLogs(): void
    {
        $error = new \RuntimeException('broken directive');
        $this->templateFilter->method('filter')->willThrowException($error);
        $this->logger->expects(self::once())->method('error')
            ->with(self::anything(), self::equalTo(['exception' => $error]));

        self::assertSame('', $this->contentFilter->filter('{{widget type="x"}}'));
    }

    /**
     * Content nested deeper than allowed comes back empty; the outer levels still render
     *
     * @return void
     */
    public function testRecursionGuard(): void
    {
        $calls = 0;
        $this->templateFilter->method('filter')->willReturnCallback(
            function (string $content) use (&$calls): string {
                $calls++;

                return 'level' . $calls . '[' . $this->contentFilter->filter($content) . ']';
            }
        );
        $this->logger->expects(self::once())->method('warning');

        self::assertSame('level1[level2[]]', $this->contentFilter->filter('{{widget slider}}'));
        self::assertSame(2, $calls);
    }

    /**
     * The depth counter goes back to zero after a failure and on reset
     *
     * @return void
     */
    public function testDepthIsRestoredAfterFailureAndReset(): void
    {
        $this->templateFilter->method('filter')->willReturnOnConsecutiveCalls(
            self::throwException(new \RuntimeException('x')),
            'a',
            'b'
        );

        self::assertSame('', $this->contentFilter->filter('first'));
        self::assertSame('a', $this->contentFilter->filter('second'));
        $this->contentFilter->_resetState();
        self::assertSame('b', $this->contentFilter->filter('third'));
    }
}
