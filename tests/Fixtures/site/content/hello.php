<?= partial('greeting', ['name' => 'PHP']) ?>
<p>Rendered from <?= esc($page->slug()) ?>.php</p>
