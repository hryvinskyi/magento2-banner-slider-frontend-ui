<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Etc;

use Hryvinskyi\BannerSliderFrontendUi\Api\Attribute\ElementAttributePoolInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Attribute\ElementAttributeProviderInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Head\HeadAssetRegistrarInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\ContentFilterInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\SlideRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\TemplateRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\StorefrontContextProviderInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\View\PictureViewBuilderInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\View\SliderViewBuilderInterface;
use Hryvinskyi\BannerSliderFrontendUi\Model\Attribute\ElementAttributePool;
use Hryvinskyi\BannerSliderFrontendUi\Model\Head\HeadAssetRegistrar;
use Hryvinskyi\BannerSliderFrontendUi\Model\Render\CompositeSlideRenderer;
use Hryvinskyi\BannerSliderFrontendUi\Model\Render\ContentFilter;
use Hryvinskyi\BannerSliderFrontendUi\Model\Render\TemplateRenderer;
use Hryvinskyi\BannerSliderFrontendUi\Model\StorefrontContextProvider;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\PictureViewBuilder;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SliderViewBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the `di.xml` preferences in step with the service interfaces under `Api/`.
 *
 * Every service interface has one preference to a class that implements it, so injecting the interface always
 * resolves. The only interfaces without a preference are the ones modules contribute to a pool, which have many
 * implementations and no default.
 */
#[CoversNothing]
class DiPreferencesTest extends TestCase
{
    private const DI_FILE = __DIR__ . '/../../../etc/di.xml';
    private const API_DIR = __DIR__ . '/../../../Api';
    private const API_NAMESPACE = 'Hryvinskyi\\BannerSliderFrontendUi\\Api\\';

    /**
     * Interfaces implemented by pool contributions rather than by one preferred class
     */
    private const POOL_CONTRIBUTIONS = [ElementAttributeProviderInterface::class];

    /**
     * Each service interface is preferred to its implementation
     *
     * @param string $interface
     * @param string $implementation
     * @return void
     */
    #[TestWith([SliderViewBuilderInterface::class, SliderViewBuilder::class])]
    #[TestWith([PictureViewBuilderInterface::class, PictureViewBuilder::class])]
    #[TestWith([HeadAssetRegistrarInterface::class, HeadAssetRegistrar::class])]
    #[TestWith([StorefrontContextProviderInterface::class, StorefrontContextProvider::class])]
    #[TestWith([ElementAttributePoolInterface::class, ElementAttributePool::class])]
    #[TestWith([SlideRendererInterface::class, CompositeSlideRenderer::class])]
    #[TestWith([TemplateRendererInterface::class, TemplateRenderer::class])]
    #[TestWith([ContentFilterInterface::class, ContentFilter::class])]
    public function testInterfaceIsPreferredToItsImplementation(string $interface, string $implementation): void
    {
        self::assertSame($implementation, $this->preferences()[$interface] ?? null);
    }

    /**
     * Every preference names an existing interface and a class that implements it
     *
     * @return void
     */
    public function testEveryPreferenceResolvesToAnImplementation(): void
    {
        $preferences = $this->preferences();
        self::assertNotSame([], $preferences);

        foreach ($preferences as $interface => $type) {
            self::assertTrue(interface_exists($interface), sprintf('%s is not an interface.', $interface));
            self::assertTrue(class_exists($type), sprintf('%s is not a class.', $type));
            self::assertTrue(
                is_subclass_of($type, $interface),
                sprintf('%s does not implement %s.', $type, $interface)
            );
        }
    }

    /**
     * Every service interface under `Api/` has a preference
     *
     * @return void
     */
    public function testEveryServiceInterfaceHasAPreference(): void
    {
        $interfaces = array_values(array_diff($this->apiInterfaces(), self::POOL_CONTRIBUTIONS));
        self::assertNotSame([], $interfaces);

        $missing = array_values(array_diff($interfaces, array_keys($this->preferences())));
        self::assertSame([], $missing, 'Service interfaces without a preference in di.xml.');
    }

    /**
     * The preferences of `di.xml`
     *
     * @return array<string,string> Interface => preferred class
     */
    private function preferences(): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(self::DI_FILE));

        $preferences = [];
        foreach ($document->getElementsByTagName('preference') as $preference) {
            $preferences[$preference->getAttribute('for')] = $preference->getAttribute('type');
        }

        return $preferences;
    }

    /**
     * Names of the interfaces declared under `Api/`
     *
     * @return list<string>
     */
    private function apiInterfaces(): array
    {
        $root = realpath(self::API_DIR);
        self::assertIsString($root);

        $files = array_merge(
            glob($root . '/*Interface.php') ?: [],
            glob($root . '/*/*Interface.php') ?: []
        );

        $interfaces = [];
        foreach ($files as $file) {
            $relative = substr($file, strlen($root) + 1, -strlen('.php'));
            $interfaces[] = self::API_NAMESPACE . str_replace('/', '\\', $relative);
        }

        return $interfaces;
    }
}
