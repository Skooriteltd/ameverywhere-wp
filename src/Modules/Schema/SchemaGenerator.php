<?php

namespace RankSavvy\Modules\Schema;

class SchemaGenerator
{
    private array $schemaTypes = [];

    public function __construct()
    {
        // MVP: Hardcode for now, later use DI or a registry pattern.
        $this->schemaTypes[] = new Types\ArticleSchema();
        $this->schemaTypes[] = new Types\BreadcrumbSchema();
    }

    public function outputSchema(): void
    {
        if (is_admin()) {
            return;
        }

        $graphs = [];

        foreach ($this->schemaTypes as $type) {
            if ($type->isApplicable()) {
                $graphs[] = $type->generate();
            }
        }

        if (empty($graphs)) {
            return;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@graph'   => $graphs,
        ];

        echo "<!-- RankSavvy AI-Ready Schema -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        echo "</script>\n";
        echo "<!-- /RankSavvy Schema -->\n";
    }
}
