<?php

namespace RankSavvy\Modules\Schema;

/**
 * Handles secure server-side fetching and parsing of competitor JSON-LD schemas.
 */
class CompetitorScraper
{
    /**
     * Scrapes a URL, extracts JSON-LD blocks, and maps them to RankSavvy schema structures.
     */
    public static function scrapeUrl(string $url): array
    {
        $url = esc_url_raw($url);
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => __('Invalid competitor URL supplied.', 'ranksavvy'),
            ];
        }

        // Fetch the remote HTML page securely
        $response = wp_safe_remote_get($url, [
            'timeout'    => 10,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36 RankSavvyProxy/1.0',
            'headers'    => [
                'Accept' => 'text/html',
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => sprintf(__('Failed to fetch URL: %s', 'ranksavvy'), $response->get_error_message()),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return [
                'success' => false,
                'message' => sprintf(__('Competitor URL returned status code %d.', 'ranksavvy'), $code),
            ];
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return [
                'success' => false,
                'message' => __('Competitor URL returned empty content.', 'ranksavvy'),
            ];
        }

        // Find all JSON-LD blocks
        if (!preg_match_all('/<script\b[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            return [
                'success' => false,
                'message' => __('No JSON-LD schemas found on the competitor page.', 'ranksavvy'),
            ];
        }

        $schemas = [];
        foreach ($matches[1] as $jsonText) {
            $jsonText = trim($jsonText);
            // strip HTML comments or CDATA wrapper inside script tags if present
            $jsonText = preg_replace('/^\s*<!\[CDATA\[|\]\]>\s*$/s', '', $jsonText);
            $jsonText = preg_replace('/^\s*<!--|-->\s*$/s', '', $jsonText);

            $decoded = json_decode($jsonText, true);
            if (is_array($decoded)) {
                $schemas[] = $decoded;
            }
        }

        if (empty($schemas)) {
            return [
                'success' => false,
                'message' => __('Could not decode any valid JSON-LD graphs.', 'ranksavvy'),
            ];
        }

        // Try to locate relevant target schemas from the graphs
        $extracted = self::locateTargetSchemas($schemas);

        return [
            'success' => true,
            'schemas' => $extracted,
        ];
    }

    /**
     * Searches through the found graphs to find supported schema types.
     */
    private static function locateTargetSchemas(array $schemas): array
    {
        $result = [];

        // Flatten the graphs if any schemas use @graph
        $flat = [];
        foreach ($schemas as $s) {
            if (isset($s['@graph']) && is_array($s['@graph'])) {
                foreach ($s['@graph'] as $node) {
                    $flat[] = $node;
                }
            } else {
                $flat[] = $s;
            }
        }

        foreach ($flat as $node) {
            if (!isset($node['@type'])) {
                continue;
            }

            $type = strtolower($node['@type']);

            // 1. Product mapping
            if ($type === 'product' && !isset($result['product'])) {
                $offers = isset($node['offers']) ? $node['offers'] : [];
                $price = '';
                $currency = 'USD';
                $availability = 'InStock';

                if (is_array($offers)) {
                    if (isset($offers['price'])) {
                        $price = $offers['price'];
                    }
                    if (isset($offers['priceCurrency'])) {
                        $currency = $offers['priceCurrency'];
                    }
                    if (isset($offers['availability'])) {
                        $availability = str_replace('https://schema.org/', '', $offers['availability']);
                    }
                }

                $rating = '';
                if (isset($node['aggregateRating']['ratingValue'])) {
                    $rating = $node['aggregateRating']['ratingValue'];
                }

                $result['product'] = [
                    'name'         => isset($node['name']) ? sanitize_text_field($node['name']) : '',
                    'description'  => isset($node['description']) ? sanitize_text_field($node['description']) : '',
                    'price'        => sanitize_text_field($price),
                    'currency'     => sanitize_text_field($currency),
                    'rating'       => sanitize_text_field($rating),
                    'availability' => sanitize_text_field($availability),
                ];
            }

            // 2. FAQ mapping
            if (($type === 'faqpage' || $type === 'faq') && !isset($result['faq'])) {
                $qaList = [];
                if (isset($node['mainEntity']) && is_array($node['mainEntity'])) {
                    foreach ($node['mainEntity'] as $q) {
                        if (isset($q['name']) && isset($q['acceptedAnswer']['text'])) {
                            $qaList[] = [
                                'question' => sanitize_text_field($q['name']),
                                'answer'   => wp_kses_post($q['acceptedAnswer']['text']),
                            ];
                        }
                    }
                }
                if (!empty($qaList)) {
                    $result['faq'] = [
                        'questions' => $qaList,
                    ];
                }
            }

            // 3. HowTo mapping
            if ($type === 'howto' && !isset($result['howto'])) {
                $steps = [];
                if (isset($node['step']) && is_array($node['step'])) {
                    foreach ($node['step'] as $s) {
                        $stepText = '';
                        if (isset($s['text'])) {
                            $stepText = $s['text'];
                        } elseif (isset($s['itemListElement'][0]['text'])) {
                            $stepText = $s['itemListElement'][0]['text'];
                        }
                        
                        if (!empty($stepText)) {
                            $steps[] = [
                                'name' => isset($s['name']) ? sanitize_text_field($s['name']) : '',
                                'text' => wp_kses_post($stepText),
                            ];
                        }
                    }
                }

                $supplies = [];
                if (isset($node['supply']) && is_array($node['supply'])) {
                    foreach ($node['supply'] as $sup) {
                        if (isset($sup['name'])) {
                            $supplies[] = $sup['name'];
                        }
                    }
                }

                $tools = [];
                if (isset($node['tool']) && is_array($node['tool'])) {
                    foreach ($node['tool'] as $t) {
                        if (isset($t['name'])) {
                            $tools[] = $t['name'];
                        }
                    }
                }

                $result['howto'] = [
                    'name'        => isset($node['name']) ? sanitize_text_field($node['name']) : '',
                    'description' => isset($node['description']) ? sanitize_text_field($node['description']) : '',
                    'steps'       => $steps,
                    'supplies'    => implode(', ', $supplies),
                    'tools'       => implode(', ', $tools),
                ];
            }

            // 4. LocalBusiness mapping
            if ($type === 'localbusiness' && !isset($result['localbusiness'])) {
                $address = isset($node['address']) ? $node['address'] : [];
                $result['localbusiness'] = [
                    'name'      => isset($node['name']) ? sanitize_text_field($node['name']) : '',
                    'telephone' => isset($node['telephone']) ? sanitize_text_field($node['telephone']) : '',
                    'street'    => isset($address['streetAddress']) ? sanitize_text_field($address['streetAddress']) : '',
                    'city'      => isset($address['addressLocality']) ? sanitize_text_field($address['addressLocality']) : '',
                    'postal'    => isset($address['postalCode']) ? sanitize_text_field($address['postalCode']) : '',
                    'country'   => isset($address['addressCountry']) ? sanitize_text_field($address['addressCountry']) : 'US',
                ];
            }

            // 5. Recipe mapping
            if ($type === 'recipe' && !isset($result['recipe'])) {
                $ingredients = isset($node['recipeIngredient']) ? $node['recipeIngredient'] : [];
                $steps = [];
                if (isset($node['recipeInstructions']) && is_array($node['recipeInstructions'])) {
                    foreach ($node['recipeInstructions'] as $idx => $s) {
                        $text = '';
                        if (is_string($s)) {
                            $text = $s;
                        } elseif (isset($s['text'])) {
                            $text = $s['text'];
                        }
                        if (!empty($text)) {
                            $steps[] = [
                                'name' => isset($s['name']) ? sanitize_text_field($s['name']) : ('Step ' . ($idx + 1)),
                                'text' => wp_kses_post($text),
                            ];
                        }
                    }
                }

                $calories = '';
                if (isset($node['nutrition']['calories'])) {
                    $calories = preg_replace('/[^\d]/', '', $node['nutrition']['calories']);
                }

                $result['recipe'] = [
                    'name'         => isset($node['name']) ? sanitize_text_field($node['name']) : '',
                    'description'  => isset($node['description']) ? sanitize_text_field($node['description']) : '',
                    'ingredients'  => is_array($ingredients) ? implode("\n", array_map('sanitize_text_field', $ingredients)) : sanitize_text_field($ingredients),
                    'instructions' => $steps,
                    'prep_time'    => isset($node['prepTime']) ? sanitize_text_field($node['prepTime']) : '',
                    'cook_time'    => isset($node['cookTime']) ? sanitize_text_field($node['cookTime']) : '',
                    'calories'     => sanitize_text_field($calories),
                ];
            }

            // 6. Event mapping
            if ($type === 'event' && !isset($result['event'])) {
                $location = isset($node['location']) ? $node['location'] : [];
                $venue = '';
                $address = '';
                if (isset($location['name'])) {
                    $venue = $location['name'];
                }
                if (isset($location['address']['streetAddress'])) {
                    $address = $location['address']['streetAddress'];
                }

                $price = '';
                $currency = 'USD';
                if (isset($node['offers']['price'])) {
                    $price = $node['offers']['price'];
                }
                if (isset($node['offers']['priceCurrency'])) {
                    $currency = $node['offers']['priceCurrency'];
                }

                $result['event'] = [
                    'name'       => isset($node['name']) ? sanitize_text_field($node['name']) : '',
                    'start_date' => isset($node['startDate']) ? date('Y-m-d\TH:i', strtotime($node['startDate'])) : '',
                    'end_date'   => isset($node['endDate']) ? date('Y-m-d\TH:i', strtotime($node['endDate'])) : '',
                    'venue'      => sanitize_text_field($venue),
                    'address'    => sanitize_text_field($address),
                    'performer'  => isset($node['performer']['name']) ? sanitize_text_field($node['performer']['name']) : '',
                    'price'      => sanitize_text_field($price),
                    'currency'   => sanitize_text_field($currency),
                ];
            }
        }

        return $result;
    }
}
