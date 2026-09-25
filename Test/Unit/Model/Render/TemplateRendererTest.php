<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\Render;

use Hryvinskyi\BannerSliderFrontendUi\Model\Render\TemplateRenderer;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Text;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemplateRenderer::class)]
class TemplateRendererTest extends TestCase
{
    /**
     * A template block is created with the template, receives the variables and renders
     *
     * @return void
     */
    public function testRendersThroughATemplateBlock(): void
    {
        $block = $this->createMock(Template::class);
        $assigned = [];
        $block->method('assign')->willReturnCallback(
            function (string $name, mixed $value) use (&$assigned, $block): Template {
                $assigned[$name] = $value;

                return $block;
            }
        );
        $block->method('toHtml')->willReturn('<p>slide</p>');
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects(self::once())->method('createBlock')
            ->with(Template::class, '', ['data' => ['template' => 'Vendor_Module::a.phtml']])
            ->willReturn($block);

        $html = (new TemplateRenderer($layout))->render('Vendor_Module::a.phtml', ['view' => 'v', 'other' => 2]);

        self::assertSame('<p>slide</p>', $html);
        self::assertSame(['view' => 'v', 'other' => 2], $assigned);
    }

    /**
     * A layout that does not return a template block renders nothing
     *
     * @return void
     */
    public function testNonTemplateBlockRendersNothing(): void
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('createBlock')->willReturn($this->createMock(Text::class));

        self::assertSame('', (new TemplateRenderer($layout))->render('Vendor_Module::a.phtml'));
    }
}
