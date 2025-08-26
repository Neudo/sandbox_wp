<?php

$context = \Timber\Timber::context();
$context['title'] = 'Mon Thème';
$context['content'] = 'Contenu de la page';

\Timber\Timber::render('pages/index.twig', $context);