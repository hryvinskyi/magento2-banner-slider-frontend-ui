<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\Source;

use Hryvinskyi\BannerSliderFrontendUi\Model\Source\WidgetSliderOptions;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WidgetSliderOptions::class)]
class WidgetSliderOptionsTest extends TestCase
{
    /**
     * An empty choice comes first, then every slider
     *
     * @return void
     */
    public function testEmptyChoiceFirst(): void
    {
        $sliders = $this->createMock(OptionSourceInterface::class);
        $sliders->method('toOptionArray')->willReturn([
            ['value' => 2, 'label' => 'Footer'],
            ['value' => 1, 'label' => 'Hero'],
        ]);

        $options = (new WidgetSliderOptions($sliders))->toOptionArray();

        self::assertCount(3, $options);
        self::assertSame('', $options[0]['value']);
        self::assertInstanceOf(Phrase::class, $options[0]['label']);
        self::assertSame('-- Use the location --', $options[0]['label']->render());
        self::assertSame(['value' => 2, 'label' => 'Footer'], $options[1]);
        self::assertSame(['value' => 1, 'label' => 'Hero'], $options[2]);
    }
}
