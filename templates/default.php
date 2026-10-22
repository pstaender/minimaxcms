<?php /** @var Zeitpulse\MiniMaxCMS\Content\Page $page */ ?>
<?= partial('head') ?>
<article>
<?= optimized_content_images($page->content()->md()) ?>
</article>
<?= partial('footer') ?>
