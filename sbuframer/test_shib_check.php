<?php
header('Content-Type: text/plain');

echo "Apache Module Detection Test\n";
echo "============================\n";

if (!function_exists('apache_get_modules')) {
    echo "Error: apache_get_modules() function is not available.\n";
    echo "This likely means you are running PHP-FPM, not mod_php.\n";
    echo "In PHP-FPM, we cannot list Apache modules from PHP.\n";
    exit;
}

$modules = apache_get_modules();
echo "Total loaded modules: " . count($modules) . "\n\n";

$shib_candidates = [
    'mod_shib',
    'mod_shib.c',
    'shibboleth_module',
    'shib_module',
    'mod_shib_24.so'
];

$found = false;
echo "Checking specific identifiers:\n";
foreach ($shib_candidates as $candidate) {
    if (in_array($candidate, $modules)) {
        echo "[MATCH] '$candidate' is loaded!\n";
        $found = true;
    } else {
        echo "[ ... ] '$candidate' not found in list.\n";
    }
}

echo "\nAll Loaded Modules:\n";
print_r($modules);
?>