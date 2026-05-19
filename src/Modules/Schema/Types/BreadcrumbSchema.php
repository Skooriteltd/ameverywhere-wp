<?php

namespace RankSavvy\Modules\Schema\Types;

class BreadcrumbSchema
{
    public function isApplicable(): bool
    {
        return !is_front_page();
    }

    public function generate(): array
    {
        $itemListElement = [];
        
        // Home
        $itemListElement[] = [
            '@type'    => 'ListItem',
            'position' => 1,
            'name'     => 'Home',
            'item'     => home_url('/'),
        ];

        // MVP: Simple implementation for single posts
        if (is_single()) {
            $post = get_post();
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
            }
        }

        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => home_url(add_query_arg([], $GLOBALS['wp']->request)) . '#breadcrumb',
            'itemListElement' => $itemListElement,
        ];
    }
}
