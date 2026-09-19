<?php

namespace AmEveryWhere\Modules\Schema\Types;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generates custom point-and-click schemas (Product, FAQ, HowTo, LocalBusiness, Recipe, Event)
 * based on post-level metadata.
 */
class CustomSchema
{
    /**
     * Determines if a custom schema is configured for the current post.
     */
    public function isApplicable(?int $postId = null): bool
    {
        $id = $postId !== null ? $postId : get_the_ID();
        if (!$id) {
            return false;
        }

        $schemaType = \AmEveryWhere\Modules\Schema\SchemaGenerator::getEffectivePrimarySchema($id);
        return !empty($schemaType) && $schemaType !== 'none';
    }

    /**
     * Generates the schema payload.
     */
    public function generate(?int $postId = null): array
    {
        $id = $postId !== null ? $postId : get_the_ID();
        if (!$id) {
            return [];
        }

        $schemaType = \AmEveryWhere\Modules\Schema\SchemaGenerator::getEffectivePrimarySchema($id);
        $schema = [];


        switch ($schemaType) {
            case 'product':
                $schema = $this->generateProductSchema($id);
                break;
            case 'faq':
                $schema = $this->generateFaqSchema($id);
                break;
            case 'howto':
                $schema = $this->generateHowToSchema($id);
                break;
            case 'localbusiness':
                $schema = $this->generateLocalBusinessSchema($id);
                break;
            case 'recipe':
                $schema = $this->generateRecipeSchema($id);
                break;
            case 'event':
                $schema = $this->generateEventSchema($id);
                break;
            case 'custom':
                $schema = [
                    '@type' => get_post_meta($id, '_ameverywhere_schema_custom_type', true) ?: 'Thing',
                    '@id'   => get_permalink($id) . '#custom',
                ];
                break;
            default:
                $schema = [];
                break;
        }

        if (!empty($schema)) {
            $schema = $this->mergeCustomProperties($id, $schema);
        }

        return $schema;
    }

    /**
     * Product Schema.
     */
    private function generateProductSchema(int $postId): array
    {
        $name = get_post_meta($postId, '_ameverywhere_schema_product_name', true) ?: get_the_title($postId);
        $desc = get_post_meta($postId, '_ameverywhere_schema_product_description', true) ?: wp_strip_all_tags(get_the_excerpt($postId));
        $price = get_post_meta($postId, '_ameverywhere_schema_product_price', true);
        $currency = get_post_meta($postId, '_ameverywhere_schema_product_currency', true) ?: 'USD';
        $rating = get_post_meta($postId, '_ameverywhere_schema_product_rating', true);
        $availability = get_post_meta($postId, '_ameverywhere_schema_product_availability', true) ?: 'InStock';

        $schema = [
            '@type'       => 'Product',
            '@id'         => get_permalink($postId) . '#product',
            'name'        => $name,
            'description' => $desc,
            'url'         => get_permalink($postId),
        ];

        if (has_post_thumbnail($postId)) {
            $schema['image'] = get_the_post_thumbnail_url($postId, 'full');
        }

        if (!empty($price)) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => floatval($price),
                'priceCurrency' => $currency,
                'availability'  => 'https://schema.org/' . $availability,
                'url'           => get_permalink($postId),
            ];
        }

        if (!empty($rating)) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => floatval($rating),
                'bestRating'  => 5,
                'ratingCount' => 1,
            ];
        }

        return $schema;
    }

    /**
     * FAQ Schema.
     */
    private function generateFaqSchema(int $postId): array
    {
        $faqsJson = get_post_meta($postId, '_ameverywhere_schema_faq_questions', true);
        $faqs = !empty($faqsJson) ? json_decode($faqsJson, true) : [];

        if (empty($faqs) || !is_array($faqs)) {
            return [];
        }

        $mainEntities = [];
        foreach ($faqs as $item) {
            if (empty($item['question']) || empty($item['answer'])) {
                continue;
            }
            $mainEntities[] = [
                '@type'          => 'Question',
                'name'           => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $item['answer'],
                ]
            ];
        }

        if (empty($mainEntities)) {
            return [];
        }

        return [
            '@type'      => 'FAQPage',
            '@id'        => get_permalink($postId) . '#faq',
            'mainEntity' => $mainEntities,
        ];
    }

    /**
     * HowTo Schema.
     */
    private function generateHowToSchema(int $postId): array
    {
        $name = get_post_meta($postId, '_ameverywhere_schema_howto_name', true) ?: get_the_title($postId);
        $desc = get_post_meta($postId, '_ameverywhere_schema_howto_description', true) ?: wp_strip_all_tags(get_the_excerpt($postId));
        
        $stepsJson = get_post_meta($postId, '_ameverywhere_schema_howto_steps', true);
        $steps = !empty($stepsJson) ? json_decode($stepsJson, true) : [];

        $suppliesText = get_post_meta($postId, '_ameverywhere_schema_howto_supplies', true);
        $toolsText = get_post_meta($postId, '_ameverywhere_schema_howto_tools', true);

        $schema = [
            '@type'       => 'HowTo',
            '@id'         => get_permalink($postId) . '#howto',
            'name'        => $name,
            'description' => $desc,
        ];

        if (!empty($steps) && is_array($steps)) {
            $stepElements = [];
            foreach ($steps as $idx => $step) {
                if (empty($step['text'])) {
                    continue;
                }
                $stepElements[] = [
                    '@type' => 'HowToStep',
                    'name'  => isset($step['name']) && $step['name'] !== '' ? $step['name'] : ('Step ' . ($idx + 1)),
                    'text'  => $step['text'],
                    'url'   => get_permalink($postId) . '#step-' . ($idx + 1),
                ];
            }
            if (!empty($stepElements)) {
                $schema['step'] = $stepElements;
            }
        }

        if (!empty($suppliesText)) {
            $suppliesList = array_map('trim', explode(',', $suppliesText));
            $supplyElements = [];
            foreach ($suppliesList as $supply) {
                $supplyElements[] = [
                    '@type' => 'HowToSupply',
                    'name'  => $supply,
                ];
            }
            $schema['supply'] = $supplyElements;
        }

        if (!empty($toolsText)) {
            $toolsList = array_map('trim', explode(',', $toolsText));
            $toolElements = [];
            foreach ($toolsList as $tool) {
                $toolElements[] = [
                    '@type' => 'HowToTool',
                    'name'  => $tool,
                ];
            }
            $schema['tool'] = $toolElements;
        }

        return $schema;
    }

    /**
     * LocalBusiness Schema.
     */
    private function generateLocalBusinessSchema(int $postId): array
    {
        $name = get_post_meta($postId, '_ameverywhere_schema_localbusiness_name', true) ?: get_bloginfo('name');
        $telephone = get_post_meta($postId, '_ameverywhere_schema_localbusiness_telephone', true);
        $street = get_post_meta($postId, '_ameverywhere_schema_localbusiness_street', true);
        $city = get_post_meta($postId, '_ameverywhere_schema_localbusiness_city', true);
        $postal = get_post_meta($postId, '_ameverywhere_schema_localbusiness_postal', true);
        $country = get_post_meta($postId, '_ameverywhere_schema_localbusiness_country', true) ?: 'US';

        $schema = [
            '@type'     => 'LocalBusiness',
            '@id'       => get_permalink($postId) . '#localbusiness',
            'name'      => $name,
            'url'       => get_permalink($postId),
            'telephone' => $telephone,
        ];

        if (has_post_thumbnail($postId)) {
            $schema['image'] = get_the_post_thumbnail_url($postId, 'full');
        }

        if (!empty($street) || !empty($city)) {
            $schema['address'] = [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $street,
                'addressLocality' => $city,
                'postalCode'      => $postal,
                'addressCountry'  => $country,
            ];
        }

        return $schema;
    }

    /**
     * Recipe Schema.
     */
    private function generateRecipeSchema(int $postId): array
    {
        $name = get_post_meta($postId, '_ameverywhere_schema_recipe_name', true) ?: get_the_title($postId);
        $desc = get_post_meta($postId, '_ameverywhere_schema_recipe_description', true) ?: wp_strip_all_tags(get_the_excerpt($postId));
        
        $ingredientsText = get_post_meta($postId, '_ameverywhere_schema_recipe_ingredients', true);
        $ingredients = [];
        if (!empty($ingredientsText)) {
            // Can be JSON or comma-separated
            $decoded = json_decode($ingredientsText, true);
            if (is_array($decoded)) {
                $ingredients = $decoded;
            } else {
                $ingredients = array_map('trim', explode(',', $ingredientsText));
            }
        }

        $instructionsJson = get_post_meta($postId, '_ameverywhere_schema_recipe_instructions', true);
        $instructions = !empty($instructionsJson) ? json_decode($instructionsJson, true) : [];

        $prepTimeRaw = get_post_meta($postId, '_ameverywhere_schema_recipe_prep_time', true);
        $cookTimeRaw = get_post_meta($postId, '_ameverywhere_schema_recipe_cook_time', true);
        $calories = get_post_meta($postId, '_ameverywhere_schema_recipe_calories', true);

        $schema = [
            '@type'       => 'Recipe',
            '@id'         => get_permalink($postId) . '#recipe',
            'name'        => $name,
            'description' => $desc,
            'url'         => get_permalink($postId),
        ];

        if (has_post_thumbnail($postId)) {
            $schema['image'] = get_the_post_thumbnail_url($postId, 'full');
        }

        if (!empty($ingredients)) {
            $schema['recipeIngredient'] = $ingredients;
        }

        if (!empty($instructions) && is_array($instructions)) {
            $instructionElements = [];
            foreach ($instructions as $idx => $step) {
                if (empty($step['text'])) {
                    continue;
                }
                $instructionElements[] = [
                    '@type' => 'HowToStep',
                    'name'  => isset($step['name']) && $step['name'] !== '' ? $step['name'] : ('Step ' . ($idx + 1)),
                    'text'  => $step['text'],
                ];
            }
            if (!empty($instructionElements)) {
                $schema['recipeInstructions'] = $instructionElements;
            }
        }

        $prepIso = $this->convertToIsoDuration($prepTimeRaw);
        if ($prepIso) {
            $schema['prepTime'] = $prepIso;
        }

        $cookIso = $this->convertToIsoDuration($cookTimeRaw);
        if ($cookIso) {
            $schema['cookTime'] = $cookIso;
        }

        if (!empty($calories)) {
            $schema['nutrition'] = [
                '@type'    => 'NutritionInformation',
                'calories' => $calories . ' calories',
            ];
        }

        return $schema;
    }

    /**
     * Event Schema.
     */
    private function generateEventSchema(int $postId): array
    {
        $name = get_post_meta($postId, '_ameverywhere_schema_event_name', true) ?: get_the_title($postId);
        $startDate = get_post_meta($postId, '_ameverywhere_schema_event_start_date', true);
        $endDate = get_post_meta($postId, '_ameverywhere_schema_event_end_date', true);
        $venue = get_post_meta($postId, '_ameverywhere_schema_event_venue', true) ?: 'Online';
        $address = get_post_meta($postId, '_ameverywhere_schema_event_address', true);
        $performerName = get_post_meta($postId, '_ameverywhere_schema_event_performer', true);
        $price = get_post_meta($postId, '_ameverywhere_schema_event_price', true);
        $currency = get_post_meta($postId, '_ameverywhere_schema_event_currency', true) ?: 'USD';

        $schema = [
            '@type'             => 'Event',
            '@id'               => get_permalink($postId) . '#event',
            'name'              => $name,
            'url'               => get_permalink($postId),
            'eventStatus'       => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => empty($address) ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode',
        ];

        if (has_post_thumbnail($postId)) {
            $schema['image'] = get_the_post_thumbnail_url($postId, 'full');
        }

        if (!empty($startDate)) {
            // Convert to ISO 8601
            $schema['startDate'] = date('c', strtotime($startDate));
        }

        if (!empty($endDate)) {
            $schema['endDate'] = date('c', strtotime($endDate));
        }

        if (!empty($address)) {
            $schema['location'] = [
                '@type'   => 'Place',
                'name'    => $venue,
                'address' => [
                    '@type'          => 'PostalAddress',
                    'streetAddress'  => $address,
                ]
            ];
        } else {
            $schema['location'] = [
                '@type' => 'VirtualLocation',
                'url'   => get_permalink($postId),
            ];
        }

        if (!empty($performerName)) {
            $schema['performer'] = [
                '@type' => 'Person',
                'name'  => $performerName,
            ];
        }

        if (!empty($price)) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => floatval($price),
                'priceCurrency' => $currency,
                'availability'  => 'https://schema.org/InStock',
                'url'           => get_permalink($postId),
            ];
        }

        return $schema;
    }

    /**
     * Converts a duration representation into ISO 8601 duration format.
     */
    private function convertToIsoDuration(string $time): string
    {
        $time = trim($time);
        if (empty($time)) {
            return '';
        }
        if (strpos($time, 'P') === 0) {
            return $time;
        }
        
        $hours = 0;
        $minutes = 0;
        
        if (preg_match('/(\d+)\s*(?:hour|hr|h)/i', $time, $m)) {
            $hours = intval($m[1]);
        }
        if (preg_match('/(\d+)\s*(?:minute|min|m)/i', $time, $m)) {
            $minutes = intval($m[1]);
        }
        
        if ($hours === 0 && $minutes === 0 && is_numeric($time)) {
            $minutes = intval($time);
        }
        
        $duration = 'PT';
        if ($hours > 0) {
            $duration .= $hours . 'H';
        }
        if ($minutes > 0 || $hours === 0) {
            $duration .= $minutes . 'M';
        }
        return $duration;
    }

    /**
     * Merge user-defined visual tree properties dynamically.
     */
    private function mergeCustomProperties(int $postId, array $schema): array
    {
        $customPropsJson = get_post_meta($postId, '_ameverywhere_custom_schema_properties', true);
        if (!empty($customPropsJson)) {
            $customProps = json_decode($customPropsJson, true);
            if (is_array($customProps)) {
                foreach ($customProps as $prop) {
                    if (isset($prop['key']) && isset($prop['value']) && $prop['key'] !== '') {
                        $schema[$prop['key']] = $prop['value'];
                    }
                }
            }
        }
        return $schema;
    }
}
