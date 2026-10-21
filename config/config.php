<?php

declare(strict_types=1);

use Dumbo\Dumbo;

/*
 * Default config for every host. `config.<host>.php` overrides single keys.
 * Closures are called once the files are merged (after `.env` is loaded).
 */
return [
    'siteName' => 'your website',
    'tagline' => 'home is where your homepage is',
    'language' => 'en',
];
