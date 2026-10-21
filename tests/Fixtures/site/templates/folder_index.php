<?= partial('head') ?>
<h1><?= esc($folder->title()) ?></h1>
<ul>
<?php foreach ($folder->pages() as $post): ?>
<li><a href="<?= esc($post->url()) ?>"><?= esc($post->title()) ?></a></li>
<?php endforeach; ?>
</ul>
<?= partial('footer') ?>
