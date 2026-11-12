<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Repository-only scope guard; static analysis is not a live code-execution proof. */
final class StaticAnalysisCoverageTest extends TestCase {
    private string $root;

    protected function setUp(): void {
        $this->root = dirname(__DIR__);
    }

    public function test_phpstan_reaches_every_first_party_runtime_php_file(): void {
        $config = file_get_contents($this->root . '/phpstan.neon.dist');
        self::assertIsString($config);
        preg_match_all('/^\s*-\s+(includes\/[A-Za-z0-9_\/.\-]+)\s*$/m', $config, $matches);
        $paths = $matches[1];
        self::assertNotEmpty($paths);
        self::assertSame(count($paths), count(array_unique($paths)), 'Duplicate PHPStan configuration entries');
        foreach ($paths as $path) {
            self::assertFalse(str_contains($path, '..'));
            self::assertTrue(file_exists($this->root . '/' . $path), 'Nonexistent PHPStan path: ' . $path);
        }

        $checked = 0;
        $directory = new RecursiveDirectoryIterator($this->root . '/includes', FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directory);
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            ++$checked;
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->root) + 1));
            $covered = false;
            foreach ($paths as $path) {
                if ($relative === $path || (is_dir($this->root . '/' . $path) && str_starts_with($relative, $path . '/'))) {
                    $covered = true;
                    break;
                }
            }
            self::assertTrue($covered, 'First-party runtime PHP excluded from PHPStan: ' . $relative);
        }
        self::assertGreaterThan(30, $checked, 'Runtime PHP inventory unexpectedly small');
    }

    public function test_optional_file23_interface_is_statically_mirrored_without_bundling_it_into_production(): void {
        $stub = file_get_contents($this->root . '/tests/phpstan-wordpress-stubs.php');
        $adapter = file_get_contents($this->root . '/includes/integration/class-file23-dashboard-adapter-runtime.php');
        $plugin = file_get_contents($this->root . '/sabri-universal-post-composer.php');
        self::assertIsString($stub);
        self::assertIsString($adapter);
        self::assertIsString($plugin);
        self::assertStringContainsString("interface SPDB_Provider_Adapter", $stub);
        self::assertStringContainsString("implements \SPDB_Provider_Adapter", $adapter);
        self::assertStringContainsString("list_items( array \$query ): array|\WP_Error", $adapter);
        self::assertStringContainsString("get_item( string \$object_type, string \$object_id ): array|\WP_Error", $adapter);
        self::assertStringContainsString("array \$payload ): array|\WP_Error", $adapter);
        self::assertStringNotContainsString('phpstan-wordpress-stubs.php', $plugin);
        self::assertStringNotContainsString('phpstan-file23', $plugin);
    }

    public function test_composer_static_analysis_uses_the_checked_configuration(): void {
        $raw = file_get_contents($this->root . '/composer.json');
        self::assertIsString($raw);
        $config = json_decode($raw, true);
        self::assertIsArray($config);
        $command = $config['scripts']['phpstan'] ?? '';
        self::assertIsString($command);
        self::assertStringContainsString('phpstan.neon.dist', $command);
    }
}
