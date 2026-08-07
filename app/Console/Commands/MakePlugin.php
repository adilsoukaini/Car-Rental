<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class MakePlugin extends Command
{
    protected $signature = 'make:carrental-plugin {name}';

    protected $description = 'Scaffold a new plugin as a self-contained Composer package (provider, config, migrations dir) and register it in config/plugins.php + the root composer.json.';

    public function handle(): int
    {
        $slug = Str::slug((string) $this->argument('name'));

        if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $this->error(sprintf('"%s" is not a valid plugin name. Use kebab-case, e.g. my-new-feature.', $this->argument('name')));

            return self::FAILURE;
        }

        $studly = Str::studly($slug);
        $providerName = $studly.'ServiceProvider';
        $namespace = 'Plugins\\'.$studly;
        $package = 'carrental/'.$slug;

        $pluginDir = base_path('plugins/'.$slug);

        if (File::isDirectory($pluginDir)) {
            $this->error(sprintf('Plugin "%s" already exists at %s.', $slug, $pluginDir));

            return self::FAILURE;
        }

        $this->createDirectories($pluginDir);
        $this->createPluginComposerJson($pluginDir, $package, $namespace);
        $this->createServiceProvider($pluginDir, $slug, $studly, $providerName, $namespace);
        $this->createConfig($pluginDir, $slug);
        $this->createMigrationsGitkeep($pluginDir);

        $this->registerInConfig($slug, $providerName, $namespace);
        $this->registerInRootComposer($slug, $package);

        $this->runComposerDumpAutoload();

        $this->info(sprintf('Plugin "%s" created at plugins/%s/', $slug, $slug));
        $this->newLine();
        $this->line('Next steps:');
        $this->line(sprintf('1. Add your service provider logic to plugins/%s/src/%sServiceProvider.php', $slug, $studly));
        $this->line(sprintf('2. Activate the plugin: php artisan tinker --execute="App\\Models\\Plugin::create([\'slug\' => \'%s\', \'is_enabled\' => true])"', $slug));
        $this->line('3. Register filters, routes, etc. in the boot() method');

        return self::SUCCESS;
    }

    private function createDirectories(string $pluginDir): void
    {
        File::makeDirectory($pluginDir.'/src', 0755, true);
        File::makeDirectory($pluginDir.'/database/migrations', 0755, true);
        File::makeDirectory($pluginDir.'/config', 0755, true);
    }

    private function createPluginComposerJson(string $pluginDir, string $package, string $namespace): void
    {
        $composer = [
            'name' => $package,
            'description' => sprintf('%s plugin — scaffolded by make:carrental-plugin.', $package),
            'type' => 'library',
            'license' => 'MIT',
            'version' => '1.0.0',
            'autoload' => [
                'psr-4' => [
                    $namespace.'\\' => 'src/',
                ],
            ],
            'extra' => [
                'laravel' => [
                    'providers' => [],
                ],
            ],
            'minimum-stability' => 'stable',
        ];

        File::put(
            $pluginDir.'/composer.json',
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }

    private function createServiceProvider(string $pluginDir, string $slug, string $studly, string $providerName, string $namespace): void
    {
        $template = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\Support\ServiceProvider;

class {$providerName} extends ServiceProvider
{
    public function register(): void
    {
        \$this->mergeConfigFrom(__DIR__.'/../config/{$slug}.php', '{$slug}');
    }

    public function boot(): void
    {
        \$this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
PHP;

        File::put($pluginDir.'/src/'.$providerName.'.php', $template);
    }

    private function createConfig(string $pluginDir, string $slug): void
    {
        $template = <<<PHP
<?php

return [
    /*
     * Configuration for the {$slug} plugin.
     */
];
PHP;

        File::put($pluginDir.'/config/'.$slug.'.php', $template);
    }

    private function createMigrationsGitkeep(string $pluginDir): void
    {
        File::put($pluginDir.'/database/migrations/.gitkeep', '');
    }

    private function registerInConfig(string $slug, string $providerName, string $namespace): void
    {
        $path = base_path('config/plugins.php');
        $contents = File::get($path);

        $use = sprintf('use %s\\%s;', $namespace, $providerName);
        $contents = $this->insertUseStatement($contents, $use);

        $entry = sprintf("        '%s' => %s::class,", $slug, $providerName);
        $contents = $this->insertRegistryEntry($contents, $entry);

        File::put($path, $contents);
    }

    /**
     * Inserts a `use Plugins\...;` line into the file's contiguous plugin
     * use block, keeping the block alphabetically sorted (the existing block
     * is sorted, so this keeps the diff to a single inserted line).
     */
    private function insertUseStatement(string $contents, string $use): string
    {
        if (str_contains($contents, $use."\n")) {
            return $contents;
        }

        $lines = explode("\n", $contents);

        $start = null;
        $end = null;

        foreach ($lines as $i => $line) {
            if (str_starts_with($line, 'use Plugins\\')) {
                $start ??= $i;
                $end = $i;
            }
        }

        if ($start === null) {
            // No plugin use statements yet — insert right before `return [`.
            foreach ($lines as $i => $line) {
                if ($line === 'return [') {
                    array_splice($lines, $i, 0, [$use]);

                    return implode("\n", $lines);
                }
            }

            $lines[] = $use;

            return implode("\n", $lines);
        }

        $insertAt = $end + 1;

        for ($i = $start; $i <= $end; $i++) {
            if (strcmp($use, $lines[$i]) < 0) {
                $insertAt = $i;
                break;
            }
        }

        array_splice($lines, $insertAt, 0, [$use]);

        return implode("\n", $lines);
    }

    /**
     * Appends a plugin slug => provider entry to the 'registry' array.
     * Appended (not sorted) because the existing registry entries are not
     * ordered alphabetically — appending keeps the diff to one line.
     */
    private function insertRegistryEntry(string $contents, string $entry): string
    {
        if (str_contains($contents, $entry)) {
            return $contents;
        }

        $lines = explode("\n", $contents);

        $registryStart = null;
        foreach ($lines as $i => $line) {
            if (trim($line) === "'registry' => [") {
                $registryStart = $i;
                break;
            }
        }

        if ($registryStart === null) {
            return $contents;
        }

        $registryEnd = null;
        for ($i = $registryStart + 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '],') {
                $registryEnd = $i;
                break;
            }
        }

        if ($registryEnd === null) {
            return $contents;
        }

        $entries = array_slice($lines, $registryStart + 1, $registryEnd - $registryStart - 1);

        $lastEntry = null;
        foreach ($entries as $i => $line) {
            if (trim($line) !== '') {
                $lastEntry = $i;
            }
        }

        array_splice($entries, ($lastEntry ?? -1) + 1, 0, [$entry]);

        return implode("\n", array_merge(
            array_slice($lines, 0, $registryStart + 1),
            $entries,
            array_slice($lines, $registryEnd),
        ));
    }

    private function registerInRootComposer(string $slug, string $package): void
    {
        $path = base_path('composer.json');
        $contents = File::get($path);

        $contents = $this->insertComposerRequire($contents, $package);
        $contents = $this->insertComposerPathRepository($contents, $slug);

        File::put($path, $contents);
    }

    /**
     * Adds "carrental/{name}": "*" to the root composer.json require block,
     * in alphabetical position among the other carrental/* entries, leaving
     * every other line byte-for-byte untouched.
     */
    private function insertComposerRequire(string $contents, string $package): string
    {
        if (str_contains($contents, '"'.$package.'":')) {
            return $contents;
        }

        $lines = explode("\n", $contents);

        $newLine = sprintf('        "%s": "*",', $package);

        // Existing carrental require entries (8-space indent), keyed by line.
        $carrental = [];
        foreach ($lines as $i => $line) {
            if (str_starts_with($line, '        "carrental/')) {
                $carrental[$i] = $line;
            }
        }

        if ($carrental === []) {
            // No carrental entries yet — insert right after "require": {.
            foreach ($lines as $i => $line) {
                if (trim($line) === '"require": {') {
                    array_splice($lines, $i + 1, 0, [$newLine]);

                    return implode("\n", $lines);
                }
            }

            return $contents;
        }

        $insertAt = null;
        foreach ($carrental as $index => $line) {
            if (strcmp($newLine, $line) < 0) {
                $insertAt = $index;
                break;
            }
        }

        array_splice($lines, $insertAt ?? (array_key_last($carrental) + 1), 0, [$newLine]);

        return implode("\n", $lines);
    }

    /**
     * Adds an explicit {"type": "path", "url": "plugins/{name}"} repository
     * entry. The plugins/* glob already covers new plugins, but the explicit
     * entry makes the dependency resolvable regardless of the glob and fully
     * registers the plugin in composer.json. Overlapping path repos resolving
     * to the same directory are deduped cleanly by Composer (verified).
     */
    private function insertComposerPathRepository(string $contents, string $slug): string
    {
        if (str_contains($contents, '"url": "plugins/'.$slug.'"')) {
            return $contents;
        }

        $lines = explode("\n", $contents);

        $repositoriesStart = null;
        foreach ($lines as $i => $line) {
            if (trim($line) === '"repositories": [') {
                $repositoriesStart = $i;
                break;
            }
        }

        if ($repositoriesStart === null) {
            return $contents;
        }

        $insertAt = $repositoriesStart + 1;

        // An empty repositories array is closed right after the opening brace,
        // in which case the single new object needs no trailing comma.
        $isEmpty = isset($lines[$insertAt]) && trim($lines[$insertAt]) === '],';

        $block = $isEmpty
            ? [
                '        {',
                '            "type": "path",',
                '            "url": "plugins/'.$slug.'"',
                '        }',
            ]
            : [
                '        {',
                '            "type": "path",',
                '            "url": "plugins/'.$slug.'"',
                '        },',
            ];

        array_splice($lines, $insertAt, 0, $block);

        return implode("\n", $lines);
    }

    private function runComposerDumpAutoload(): void
    {
        $composer = $this->resolveComposerBinary();

        if ($composer === null) {
            $this->warn('Could not locate a composer binary — skipping composer dump-autoload. Run it manually.');

            return;
        }

        $env = array_merge(getenv(), ['COMPOSER_ALLOW_SUPERUSER' => '1']);

        $process = new Process([$composer, 'dump-autoload'], base_path(), $env, null, 300);
        $process->run();

        if ($process->isSuccessful()) {
            $this->line('Composer autoloader regenerated.');
        } else {
            $this->warn('composer dump-autoload did not complete cleanly:');
            $this->warn($process->getErrorOutput() ?: $process->getOutput());
        }
    }

    private function resolveComposerBinary(): ?string
    {
        // Prefer this project's known-working composer wrapper (PHP 8.4),
        // which may not survive a shell restart — the PATH scan below is the
        // fallback.
        $shim = '/tmp/php84bin/composer';

        if (is_file($shim) && is_executable($shim)) {
            return $shim;
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            $candidate = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer';

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
