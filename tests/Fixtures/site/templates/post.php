<?= partial('head') ?>
<article class="post">
<time><?= esc($page->date()->format('Y-m-d')) ?></time>
<?= $page->content()->md() ?>
</article>
<?= partial('footer') ?>
