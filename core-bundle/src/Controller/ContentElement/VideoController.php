<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @ContentElement("vimeo", category="media")
 * @ContentElement("youtube", category="media")
 *
 * @phpstan-type VideoSourceParameters array{
 *      provider: 'vimeo'|'youtube',
 *      video_id: string,
 *      options: array<string, string>,
 *      base_url: string,
 *      query: string,
 *      url: string
 *  }
 */
class VideoController extends AbstractContentElementController
{
    public function __construct(private readonly Studio $studio)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        // Video source data, size and aspect ratio
        $sourceParameters = match ($type = $template->get('type')) {
            'vimeo' => $this->getVimeoSourceParameters($model),
            'youtube' => $this->getYoutubeSourceParameters($model, $request->getLocale()),
            default => throw new \InvalidArgumentException(sprintf('Unknown video provider "%s".', $type))
        };

        $template->set('source', $sourceParameters);

        $size = StringUtil::deserialize($model->playerSize, true);

        $template->set('width', $size[0] ?? 640);
        $template->set('height', $size[1] ?? 360);

        $template->set('aspect_ratio', $model->playerAspect);

        // Meta data
        $template->set('caption', $model->playerCaption);

        // Splash image
        $figure = !$model->splashImage ? null : $this->studio
            ->createFigureBuilder()
            ->fromUuid($model->singleSRC ?: '')
            ->setSize($model->size)
            ->buildIfResourceExists()
        ;

        $template->set('splash_image', $figure);

        return $template->getResponse();
    }

    /**
     * @return array<string, string|array<string, string>>
     *
     * @phpstan-return VideoSourceParameters
     */
    private function getVimeoSourceParameters(ContentModel $model): array
    {
        $options = [];

        foreach (StringUtil::deserialize($model->vimeoOptions, true) as $option) {
            [$option, $value] = match ($option) {
                'vimeo_portrait', 'vimeo_title', 'vimeo_byline' => [substr($option, 6), '0'],
                default => [substr($option, 6), '1'],
            };

            $options[$option] = $value;
        }

        if ($color = $model->playerColor) {
            $options['color'] = $color;
        }

        $query = http_build_query($options);

        if (($start = (int) $model->playerStart) > 0) {
            $options['start'] = $start;
            $query .= "#t={$start}s";
        }

        return [
            'provider' => 'vimeo',
            'video_id' => $videoId = $model->vimeo,
            'options' => $options,
            'base_url' => $baseUrl = "https://player.vimeo.com/video/$videoId",
            'query' => $query,
            'url' => empty($query) ? $baseUrl : "$baseUrl?$query",
        ];
    }

    /**
     * @return array<string, string|array<string, string>>
     *
     * @phpstan-return VideoSourceParameters
     */
    private function getYoutubeSourceParameters(ContentModel $model, string $locale): array
    {
        $options = [];
        $domain = 'https://www.youtube.com';

        foreach (StringUtil::deserialize($model->youtubeOptions, true) as $option) {
            if ('youtube_nocookie' === $option) {
                $domain = 'https://www.youtube-nocookie.com';

                continue;
            }

            [$option, $value] = match ($option) {
                'youtube_fs', 'youtube_rel', 'youtube_controls' => [substr($option, 8), '0'],
                'youtube_hl' => [substr($option, 8), \Locale::parseLocale($locale)[\Locale::LANG_TAG] ?? ''],
                'youtube_iv_load_policy' => [substr($option, 8), '3'],
                default => [substr($option, 8), '1'],
            };

            $options[$option] = $value;
        }

        if (($start = (int) $model->playerStart) > 0) {
            $options['start'] = $start;
        }

        if (($end = (int) $model->playerStop) > 0) {
            $options['end'] = $end;
        }

        return [
            'provider' => 'youtube',
            'video_id' => $videoId = $model->youtube,
            'options' => $options,
            'base_url' => $baseUrl = "$domain/embed/$videoId",
            'query' => $query = http_build_query($options),
            'url' => empty($query) ? $baseUrl : "$baseUrl?$query",
        ];
    }
}
