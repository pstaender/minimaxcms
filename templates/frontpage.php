<?php
/**
 * @var Zeitpulse\MiniMaxCMS\Content\Page $page
 * @var Zeitpulse\MiniMaxCMS\Content\Site $site
 */
?>
<?= partial('head') ?>
<div class="frontpage">
<?= $page->content()->md() ?>
</div>
<?= partial('footer') ?>
