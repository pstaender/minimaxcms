<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS;

use Dumbo\Dumbo;
use Zeitpulse\MiniMaxCMS\Config\Config;
use Zeitpulse\MiniMaxCMS\Content\Assets;
use Zeitpulse\MiniMaxCMS\Content\ContentRepository;
use Zeitpulse\MiniMaxCMS\Content\Site;
use Zeitpulse\MiniMaxCMS\Controller\ContentController;
use Zeitpulse\MiniMaxCMS\Controller\ErrorController;
use Zeitpulse\MiniMaxCMS\Http\Kernel;
use Zeitpulse\MiniMaxCMS\Markdown\MarkdownRenderer;
use Zeitpulse\MiniMaxCMS\Routing\Redirects;
use Zeitpulse\MiniMaxCMS\View\View;

/**
 * Builds the Dumbo application: wires up config, content, views and routes.
 */
final class App
{
    public static function create(Config $config): Dumbo
    {
        $app = new Kernel($config->environment);

        $assets = new Assets($config->contentDir);
        $content = new ContentRepository($config->contentDir, new MarkdownRenderer($assets));
        $view = new View($config, new Site($content));

        $pages = new ContentController($content, $assets, new Redirects($config->redirectsFile), $view);
        $errors = new ErrorController($content, $view, $config);

        // Flat file CMS: every GET is resolved against content/ (see ContentController).
        $app->get('/*', $pages->show(...));

        $app->notFound($errors->notFound(...));
        $app->onError($errors->serverError(...));

        return $app;
    }
}
