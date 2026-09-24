<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage;

use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use InvalidArgumentException;
use Magento\Framework\DataObject;

/**
 * The breakpoint a responsive crop is shown at: its media query, the viewport width it starts at,
 * and the size of the image cut for it
 */
class CropBreakpoint
{
    public const MATCH_ALL_MEDIA_QUERY = '(min-width: 0px)';

    /**
     * @param string $mediaQuery
     * @param int $minWidth
     * @param int $width
     * @param int $height
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly string $mediaQuery,
        private readonly int $minWidth,
        private readonly int $width,
        private readonly int $height
    ) {
        if (trim($mediaQuery) === '') {
            throw new InvalidArgumentException('A breakpoint needs a media query.');
        }

        if ($minWidth < 0 || $width < 0 || $height < 0) {
            throw new InvalidArgumentException('Breakpoint widths and heights cannot be negative.');
        }
    }

    /**
     * Read the breakpoint the crop repository joins onto each crop
     *
     * The crop contract does not carry its breakpoint, so the joined columns are read from the model's data.
     * A crop without them matches every viewport and has no known size.
     *
     * @param ResponsiveCropInterface $crop
     * @return self
     */
    public static function fromCrop(ResponsiveCropInterface $crop): self
    {
        if (!$crop instanceof DataObject) {
            return new self(self::MATCH_ALL_MEDIA_QUERY, 0, 0, 0);
        }

        $mediaQuery = $crop->getData('media_query');

        return new self(
            is_string($mediaQuery) && trim($mediaQuery) !== '' ? $mediaQuery : self::MATCH_ALL_MEDIA_QUERY,
            self::toSize($crop->getData('min_width')),
            self::toSize($crop->getData('target_width')),
            self::toSize($crop->getData('target_height'))
        );
    }

    /**
     * Get the media query that selects this breakpoint
     *
     * @return string
     */
    public function getMediaQuery(): string
    {
        return $this->mediaQuery;
    }

    /**
     * Get the viewport width this breakpoint starts at
     *
     * @return int
     */
    public function getMinWidth(): int
    {
        return $this->minWidth;
    }

    /**
     * Get the width of the image cut for this breakpoint
     *
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Get the height of the image cut for this breakpoint
     *
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Whether both the width and the height of the cut image are known
     *
     * @return bool
     */
    public function hasDimensions(): bool
    {
        return $this->width > 0 && $this->height > 0;
    }

    /**
     * Turn a joined column value into a non-negative size
     *
     * @param mixed $value
     * @return int
     */
    private static function toSize(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int)$value) : 0;
    }
}
