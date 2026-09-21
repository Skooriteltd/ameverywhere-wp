<?php

// 1. Update ameverywhere.php
$f = 'ameverywhere.php';
$c = file_get_contents($f);
$c = preg_replace('/Author: AmEveryWhere Team/i', 'Author: Skoorite Limited', $c);
file_put_contents($f, $c);

// 2. Update composer.json
$f2 = 'composer.json';
if (file_exists($f2)) {
    $c2 = file_get_contents($f2);
    $c2 = str_replace('"name": "AmEveryWhere Team"', '"name": "Skoorite Limited"', $c2);
    file_put_contents($f2, $c2);
}

// 3. Update readme.txt
$f3 = 'readme.txt';
if (file_exists($f3)) {
    $c3 = file_get_contents($f3);
    // Add author mention in readme description
    $c3 = str_replace('Contributors: ameverywhere', 'Contributors: skoorite', $c3);
    
    // Add a credit in the description
    $introHook = "**AmEverywhere is the ultimate SEO and AI discoverability engine for modern WordPress.**";
    $newHook = "**AmEverywhere is the ultimate SEO and AI discoverability engine for modern WordPress, proudly created by Skoorite Limited.**";
    $c3 = str_replace($introHook, $newHook, $c3);
    
    file_put_contents($f3, $c3);
}

echo "Author updated successfully.";
