<?php
/**
 * @var Zeitpulse\MiniMaxCMS\Content\Page $page
 * @var Zeitpulse\MiniMaxCMS\Content\Site $site
 */
?>
<?= partial('head') ?>
<?= $page->content()->md() ?>
<?= partial('footer') ?>
