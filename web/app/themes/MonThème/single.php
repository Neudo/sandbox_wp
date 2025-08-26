<?php

$context = \Timber\Timber::context();
$post = \Timber\Timber::get_post();
$context['post'] = $post;

// Add related posts
$context['related_posts'] = \Timber\Timber::get_posts([
    'post_type' => 'post',
    'posts_per_page' => 3,
    'post__not_in' => [$post->ID],
    'category__in' => wp_get_post_categories($post->ID)
]);

\Timber\Timber::render('single.twig', $context);
