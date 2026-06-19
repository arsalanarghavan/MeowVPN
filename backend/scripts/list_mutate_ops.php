<?php

require __DIR__ . '/../vendor/autoload.php';

$dirs = array_merge(
    glob(__DIR__ . '/../app/Modules/*/Mutations/*.php') ?: [],
    glob(__DIR__ . '/../app/Modules/*/*/Mutations/*.php') ?: [],
);

$ops = [];
foreach ($dirs as $f) {
    $c = file_get_contents($f);
    preg_match_all("/'([a-z][a-z0-9_]+)'\s*=>\s*\[self::class/", $c, $m);
    foreach ($m[1] as $op) {
        $ops[$op] = true;
    }
}

$catalog = \App\Support\MutateOpCatalog::all();

$laravel = array_keys($ops);
sort($laravel);
$canonical = array_values(array_unique($catalog));
sort($canonical);

echo 'Laravel handlers: ' . count($laravel) . PHP_EOL;
echo 'Catalog: ' . count($canonical) . PHP_EOL;
echo 'Missing from handlers: ' . PHP_EOL . implode(PHP_EOL, array_diff($canonical, $laravel)) . PHP_EOL;
echo 'Extra handlers: ' . PHP_EOL . implode(PHP_EOL, array_diff($laravel, $canonical)) . PHP_EOL;

if (($argv[1] ?? '') === '--write-catalog') {
    $export = var_export($laravel, true);
    $content = <<<PHP
<?php

namespace App\Support;

/** Canonical mutate ops registered in Laravel MutationRegistry (§15). */
class MutateOpCatalog
{
    /** @return list<string> */
    public static function all(): array
    {
        return {$export};
    }
}

PHP;
    file_put_contents(__DIR__ . '/../app/Support/MutateOpCatalog.php', $content);
    echo 'Wrote MutateOpCatalog.php' . PHP_EOL;
}
