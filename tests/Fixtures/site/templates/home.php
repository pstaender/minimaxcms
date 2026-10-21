<?= partial('head') ?>
<main class="home"><?= $page->content()->md() ?></main>
<?php foreach (array_reverse(array_filter($site->folders(), fn ($f) => ctype_digit($f->name()))) as $year): ?>
<section><h2><?= esc($year->name()) ?></h2>
<?php foreach ($year->pages() as $entry): ?><a href="<?= esc($entry->url()) ?>"><?= esc($entry->title()) ?></a>
<?php endforeach; ?></section>
<?php endforeach; ?>
<?= partial('footer') ?>
