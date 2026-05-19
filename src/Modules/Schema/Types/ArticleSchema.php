<?php

namespace RankSavvy\Modules\Schema\Types;

class ArticleSchema
{
    public function isApplicable(): bool
    {
        return is_single();
    }

    public function generate(): array
    {
        $post = get_post();
        $schemaType = 'Article';

        // Check if News optimization is needed
        $isNews = get_post_meta($post->ID, '_ranksavvy_is_news', true);
        if ($isNews === 'yes') {
            $schemaType = 'NewsArticle';
        }

        $schema = [
            '@type'            => $schemaType,
            '@id'              => get_permalink($post->ID) . '#article',
            'headline'         => get_the_title($post->ID),
            'datePublished'    => get_the_date('c', $post->ID),
            'dateModified'     => get_the_modified_date('c', $post->ID),
            'author'           => [
                '@type' => 'Person',
                'name'  => get_the_author_meta('display_name', $post->post_author),
                'url'   => get_author_posts_url($post->post_author),
            ],
            'publisher'        => [
                '@type' => 'Organization',
                'name'  => get_bloginfo('name'),
                'logo'  => [
                    '@type' => 'ImageObject',
                    'url'   => get_site_icon_url() ?: '', // MVP: Fallback to site icon
                ]
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => get_permalink($post->ID),
            ]
        ];

        if (has_post_thumbnail($post->ID)) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url'   => get_the_post_thumbnail_url($post->ID, 'full'),
            ];
        }

        return $schema;
    }
}
