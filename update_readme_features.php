<?php
$f = "readme.txt";
$c = file_get_contents($f);

// Add Editor Tools and Analytics features
$newFeatures = <<<PHP
**Editor & On-Page Tools (Gutenberg)**
* Real-time SEO Checklist: evaluates keyword usage, title/meta length, headings, and alt text.
* Live SERP Previews: view exactly how your post will look on Google (desktop/mobile).
* Social Media Previews: view Facebook Open Graph and Twitter Card renders before publishing.
* Readability Checker: scores your content using the Flesch-Kincaid scale.
* FAQ Schema Builder: create rich-snippet accordions directly from the post sidebar.

**Compliance & Auditing**
* System-wide Audit History Log: tracks every SEO change (who changed it and when).
* FTC-compliant AI disclosure labels on AI-generated content.
* Cookie Notice: built-in lightweight notice banner.

**Migration & Setup**
* 1-Minute Setup Wizard.
* One-Click SEO Migration: seamlessly import titles, descriptions, and settings from Yoast SEO, Rank Math, and All in One SEO (AIOSEO).
PHP;

$c = str_replace(
	"**Schema & Entities**",
	$newFeatures . "\n\n**Schema & Entities**",
	$c
);

// Add GA4 and Recipe/Event to existing sections
$c = str_replace(
	"Comprehensive structured data: Article, BreadcrumbList, LocalBusiness, FAQPage, HowTo.",
	"Comprehensive structured data: Article, BreadcrumbList, LocalBusiness, FAQPage, HowTo, Recipe, and Event.",
	$c
);

$c = str_replace(
	"Google Search Console integration for impressions, clicks, and dying page detection.",
	"Google Search Console integration for impressions, clicks, and dying page detection.\n* Google Analytics 4 (GA4) Dashboard for session trends and top traffic channels.",
	$c
);

file_put_contents($f, $c);
