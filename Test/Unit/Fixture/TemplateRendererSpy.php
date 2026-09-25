<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture;

use Hryvinskyi\BannerSliderFrontendUi\Api\Render\TemplateRendererInterface;

/**
 * Records every template render and returns a marker naming the template.
 */
class TemplateRendererSpy implements TemplateRendererInterface
{
    /**
     * @var list<array{template: string, variables: array<string,mixed>}>
     */
    private array $renders = [];

    /**
     * @inheritDoc
     */
    public function render(string $template, array $variables = []): string
    {
        $this->renders[] = ['template' => $template, 'variables' => $variables];

        return '[' . $template . ']';
    }

    /**
     * Every render so far, in order
     *
     * @return list<array{template: string, variables: array<string,mixed>}>
     */
    public function getRenders(): array
    {
        return $this->renders;
    }

    /**
     * The variable of the last render of a template
     *
     * @param string $template
     * @param string $name
     * @return mixed
     */
    public function variable(string $template, string $name): mixed
    {
        $found = null;
        foreach ($this->renders as $render) {
            if ($render['template'] === $template) {
                $found = $render['variables'][$name] ?? null;
            }
        }

        return $found;
    }
}
