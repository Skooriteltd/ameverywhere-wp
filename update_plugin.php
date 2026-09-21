<?php
$f = "src/Plugin.php";
$c = file_get_contents($f);

// Bind singleton
$c = str_replace(
	"\$this->container->singleton( 'google_search_console', \AmEveryWhere\Modules\Analytics\GoogleSearchConsoleIntegration::class );",
	"\$this->container->singleton( 'google_search_console', \AmEveryWhere\Modules\Analytics\GoogleSearchConsoleIntegration::class );\n\t\t\$this->container->singleton( 'bing_webmaster', \AmEveryWhere\Modules\Analytics\BingWebmasterIntegration::class );",
	$c
);

// Call register()
$c = str_replace(
	"\$gsc = \$this->container->get( 'google_search_console' );\n\t\t\$gsc->register();",
	"\$gsc = \$this->container->get( 'google_search_console' );\n\t\t\$gsc->register();\n\n\t\t\$bing = \$this->container->get( 'bing_webmaster' );\n\t\t\$bing->register();",
	$c
);

file_put_contents($f, $c);
