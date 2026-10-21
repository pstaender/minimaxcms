<?php
/**
 * @var Zeitpulse\MiniMaxCMS\Content\Page|Zeitpulse\MiniMaxCMS\Content\Folder $page
 * @var Zeitpulse\MiniMaxCMS\Config\Config $config
 */
?>
<!doctype html>
<html lang="<?= esc($config->language) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($page->title() ?? 'Untitled') ?> · <?= esc($config->siteName) ?></title>
    <link rel="stylesheet" href="https://unpkg.com/minimaxcss@latest/minimax-starter.css"/>
    <link rel="stylesheet" href="<?= static_file_url('/assets/css/layout.css') ?>">
</head>
<body>
<header>
    <a href="/" class="site-name"><?= esc($config->siteName) ?></a>
    <p class="tagline"><?= esc($config->tagline) ?></p>
</header>
<main>
