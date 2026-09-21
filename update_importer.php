<?php
$f = "src/Modules/Import/CompetitorSeoImporter.php";
$c = file_get_contents($f);

// 1. Constants
$constants = <<<PHP
	private const RANKMATH_TITLE     = 'rank_math_title';
	private const RANKMATH_DESC      = 'rank_math_description';
	private const RANKMATH_KW        = 'rank_math_focus_keyword';
	private const RANKMATH_CANONICAL = 'rank_math_canonical_url';
PHP;
$c = str_replace(
	"private const AIOSEO_ROBOTS = '_aioseo_robots_default';",
	"private const AIOSEO_ROBOTS = '_aioseo_robots_default';\n\n$constants",
	$c
);

// 2. Detection
$detect = <<<PHP
		\$rankMathData = (int) \$wpdb->get_var(
			\$wpdb->prepare( "SELECT COUNT(*) FROM {\$wpdb->postmeta} WHERE meta_key = %s LIMIT 1", self::RANKMATH_TITLE )
		);

		return rest_ensure_response(
			array(
				'yoast_seo'      => array(
					'detected'   => \$yoastData > 0,
					'post_count' => \$yoastData,
				),
				'all_in_one_seo' => array(
					'detected'   => \$aioseoData > 0,
					'post_count' => \$aioseoData,
				),
				'rank_math'      => array(
					'detected'   => \$rankMathData > 0,
					'post_count' => \$rankMathData,
				),
			)
		);
PHP;
$c = preg_replace("/return rest_ensure_response\(\s*array\(\s*'yoast_seo'[\s\S]*?\)\s*\);/", $detect, $c);

// 3. Import Logic
$rm_logic = <<<PHP
		} elseif ( \$source === 'rankmath' ) {
			\$rows = \$wpdb->get_results(
				\$wpdb->prepare(
					"SELECT post_id, meta_key, meta_value FROM {\$wpdb->postmeta}
                     WHERE meta_key IN (%s, %s, %s, %s)
                     ORDER BY post_id LIMIT %d",
					self::RANKMATH_TITLE,
					self::RANKMATH_DESC,
					self::RANKMATH_KW,
					self::RANKMATH_CANONICAL,
					\$limit * 4
				)
			);

			\$byPost = array();
			foreach ( \$rows as \$row ) {
				\$byPost[ (int) \$row->post_id ][ \$row->meta_key ] = \$row->meta_value;
			}

			\$map = array(
				self::RANKMATH_TITLE     => '_ameverywhere_meta_title',
				self::RANKMATH_DESC      => '_ameverywhere_meta_description',
				self::RANKMATH_KW        => '_ameverywhere_focus_keyword',
				self::RANKMATH_CANONICAL => '_ameverywhere_canonical_url',
			);

			foreach ( array_slice( \$byPost, 0, \$limit, true ) as \$postId => \$meta ) {
				\$detail = array(
					'post_id' => \$postId,
					'fields'  => array(),
				);

				foreach ( \$map as \$fromKey => \$toKey ) {
					if ( empty( \$meta[ \$fromKey ] ) ) {
						continue;
					}
					\$existing = get_post_meta( \$postId, \$toKey, true );
					if ( ! empty( \$existing ) && ! \$overwrite ) {
						++\$skipped;
						continue;
					}
					if ( ! \$dryRun ) {
						update_post_meta( \$postId, \$toKey, \$meta[ \$fromKey ] );
					}
					\$detail['fields'][] = \$toKey;
					++\$imported;
				}

				\$details[] = \$detail;
			}
		}

		return compact( 'imported', 'skipped', 'details' );
	}
PHP;

$c = preg_replace("/\}\s*return compact\( 'imported', 'skipped', 'details' \);\s*\}/s", $rm_logic . "\n", $c);

file_put_contents($f, $c);
