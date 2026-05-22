<?php

namespace RankSavvy\Modules\Schema\Types;

class BreadcrumbSchema
{
    public function isApplicable(?int $postId = null): bool
    {
        if ($postId !== null) {
            return true; // We can generate breadcrumbs for any valid post
        }
        return !is_front_page();
    }

    public function generate(?int $postId = null): array
    {
        $itemListElement = [];
        
        // Home
        $itemListElement[] = [
            '@type'    => 'ListItem',
            'position' => 1,
            'name'     => 'Home',
            'item'     => home_url('/'),
        ];

        // Simple implementation for single posts
        $post = $postId !== null ? get_post($postId) : get_post();
        if ($post) {
            $categories = get_the_category($post->ID);
            
            if (!empty($categories)) {
                $category = $categories[0];
                $itemListElement[] = [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => $category->name,
                    'item'     => get_category_link($category->term_id),
                ];
                
                $itemListElement[] = [
                    '@type'    => 'ListItem',
                    'position' => 3,
                    'name'     => get_the_title($post->ID),
                    'item'     => get_permalink($post->ID),
                ];
            } else {
                $itemListElement[] = [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => get_the_title($post->ID),
                    'item'     => get_permalink($post->ID),
                ];
            }
        }

        $pageUrl = $post ? get_permalink($post->ID) : home_url(add_query_arg([], $GLOBALS['wp']->request));

        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => $pageUrl . '#breadcrumb',
            'itemListElement' => $itemListElement,
        ];
    }
}
