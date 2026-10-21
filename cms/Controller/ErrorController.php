<?php

declare(strict_types=1);

namespace Zeitpulse\MiniMaxCMS\Controller;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zeitpulse\MiniMaxCMS\Config\Config;
use Zeitpulse\MiniMaxCMS\Content\ContentRepository;
use Zeitpulse\MiniMaxCMS\View\View;

/**
 * Renders `content/404.php` and `content/500.php` (plain text fallbacks if they are missing or fail).
 *
 * Error pages get `$status` and, for 500, `$error` (the Throwable in development, null in production).
 */
final readonly class ErrorController
{
    public function __construct(
        private ContentRepository $content,
        private View $view,
        private Config $config,
    ) {}

    public function notFound(Context $c): ResponseInterface
    {
        $page = $this->content->errorPage(404);
        if ($page === null) {
            return $c->text('404 Not Found', 404);
        }

        return $c->send($this->view->renderPage($page, ['status' => 404]), $page->contentType() . '; charset=utf-8', 404);
    }

    public function serverError(Throwable $error, Context $c): ResponseInterface
    {
        error_log((string) $error);
        $details = $this->config->isDevelopment() ? $error : null;

        try {
            $page = $this->content->errorPage(500);
            if ($page !== null) {
                return $c->send(
                    $this->view->renderPage($page, ['status' => 500, 'error' => $details]),
                    $page->contentType() . '; charset=utf-8',
                    500,
                );
            }
        } catch (Throwable $pageError) {
            error_log('Error page failed: ' . $pageError);
        }

        return $c->text('500 Internal Server Error' . ($details ? "\n\n{$details}" : ''), 500);
    }
}
