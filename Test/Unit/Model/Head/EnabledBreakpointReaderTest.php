<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\Head;

use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderFrontendUi\Model\Head\EnabledBreakpointReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnabledBreakpointReader::class)]
class EnabledBreakpointReaderTest extends TestCase
{
    /**
     * @var BreakpointRepositoryInterface&MockObject
     */
    private MockObject $repository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->repository = $this->createMock(BreakpointRepositoryInterface::class);
    }

    /**
     * Only enabled breakpoints are read, in the repository's order, as rendering specs
     *
     * @return void
     */
    public function testReadsEnabledBreakpointsAsSpecs(): void
    {
        $desktop = new BreakpointSpec('desktop', '(min-width: 768px)', 768, 1920, 340);
        $mobile = new BreakpointSpec('mobile', '(max-width: 767px)', 0, 892, 588);
        $this->repository->expects(self::once())->method('getBySliderId')->with(1, true)
            ->willReturn([$this->breakpoint($desktop), $this->breakpoint($mobile)]);

        self::assertSame([$desktop, $mobile], (new EnabledBreakpointReader($this->repository))->forSlider(1));
    }

    /**
     * Each slider is read once per request, including a slider without breakpoints; a reset reads again
     *
     * @return void
     */
    public function testReadsEachSliderOncePerRequest(): void
    {
        $read = [];
        $this->repository->method('getBySliderId')->willReturnCallback(
            function (int $sliderId) use (&$read): array {
                $read[] = $sliderId;

                return [];
            }
        );
        $reader = new EnabledBreakpointReader($this->repository);

        $reader->forSlider(1);
        $reader->forSlider(1);
        $reader->forSlider(2);
        $reader->_resetState();
        $reader->forSlider(1);

        self::assertSame([1, 2, 1], $read);
    }

    /**
     * A stored breakpoint whose spec is the given one
     *
     * @param BreakpointSpec $spec
     * @return BreakpointInterface&MockObject
     */
    private function breakpoint(BreakpointSpec $spec): BreakpointInterface
    {
        $breakpoint = $this->createMock(BreakpointInterface::class);
        $breakpoint->method('toSpec')->willReturn($spec);

        return $breakpoint;
    }
}
