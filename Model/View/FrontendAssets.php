<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\View;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * Public URLs of the static files a slider needs in the current theme and locale.
 *
 * The file ids come from `di.xml`, so a theme or module can point them at its own copies.
 */
class FrontendAssets
{
    /**
     * @param AssetRepository $assetRepository
     * @param array<string,string> $stylesheets Name => file id of each stylesheet, in load order
     * @param array<string,string> $scripts Name => file id of each script a page without a module loader needs
     */
    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly array $stylesheets = [],
        private readonly array $scripts = []
    ) {
    }

    /**
     * URLs of the stylesheets, in load order
     *
     * @return list<string>
     * @throws LocalizedException When a file id cannot be resolved
     */
    public function getStylesheetUrls(): array
    {
        return array_values($this->urls($this->stylesheets));
    }

    /**
     * URLs of the scripts by name
     *
     * @return array<string,string>
     * @throws LocalizedException When a file id cannot be resolved
     */
    public function getScriptUrls(): array
    {
        return $this->urls($this->scripts);
    }

    /**
     * URLs of file ids, keeping the keys
     *
     * @param array<string,string> $fileIds
     * @return array<string,string>
     * @throws LocalizedException
     */
    private function urls(array $fileIds): array
    {
        $urls = [];
        foreach ($fileIds as $name => $fileId) {
            $urls[$name] = $this->assetRepository->getUrl($fileId);
        }

        return $urls;
    }
}
