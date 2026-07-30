<?php

declare(strict_types=1);

return [
    '/' => [
        'slug' => 'home',
        'title' => 'Alan Fullmer | Web Accessibility & UX Developer',
        'description' => 'Alan Fullmer is a Tucson-based web developer focused on accessibility, UX, performance, practical interface repair, plugins, and photography-informed detail.',
        'template' => 'home.php',
    ],
    '/portfolio' => [
        'slug' => 'portfolio',
        'title' => 'Portfolio | Alan Fullmer',
        'description' => 'Selected web, accessibility, performance, and interface work by Alan Fullmer.',
        'template' => 'portfolio.php',
    ],
    '/plugins' => [
        'slug' => 'plugins',
        'title' => 'Plugins | Alan Fullmer',
        'description' => 'Plugin and tooling work by Alan Fullmer.',
        'template' => 'plugins.php',
    ],
    '/accessible-form' => [
        'slug' => 'accessible-form',
        'title' => 'Accessible Form Showcase | Alan Fullmer',
        'description' => 'A non-functional sample account form used to showcase accessible form patterns.',
        'template' => 'accessible-form.php',
        'styles' => ['/assets/css/forms.css'],
    ],
    '/contact' => [
        'slug' => 'contact',
        'title' => 'Contact | Alan Fullmer',
        'description' => 'Contact Alan Fullmer about web, accessibility, performance, or plugin work.',
        'template' => 'contact.php',
    ],
    '/privacy' => [
        'slug' => 'privacy',
        'title' => 'Privacy Policy | Alan Fullmer',
        'description' => 'Privacy policy for alanfullbeard.com.',
        'template' => 'privacy.php',
    ],
];
