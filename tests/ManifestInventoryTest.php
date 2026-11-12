<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Repository-only inventory consistency; does not assert deployed artifact parity.
 */
final class ManifestInventoryTest extends TestCase {
    private string $root;

    protected function setUp(): void {
        $this->root = dirname(__DIR__);
    }

    /** @return list<string> */
    private function listedPaths(): array {
        $raw = file_get_contents($this->root . '/MANIFEST.md');
        self::assertIsString($raw, 'MANIFEST.md must be readable');
        preg_match_all('/^\s*-\s+\x60([^\x60]+)\x60\s*$/m', $raw, $matches);
        return $matches[1];
    }

    public function test_every_manifest_path_exists_in_the_source_tree(): void {
        $paths = $this->listedPaths();
        self::assertNotEmpty($paths);
        self::assertSame(count($paths), count(array_unique($paths)), 'Duplicate manifest entries');
        foreach ($paths as $path) {
            self::assertSame(false, str_contains($path, '..'), 'Parent traversal in manifest path: ' . $path);
            self::assertFileExists($this->root . '/' . $path, 'Stale manifest entry: ' . $path);
        }
    }

    public function test_every_runtime_test_asset_and_workflow_file_is_manifested(): void {
        $paths = $this->listedPaths();
        foreach (array('includes', 'tests', 'assets', '.github/workflows') as $directory) {
            $base = $this->root . '/' . $directory;
            self::assertDirectoryExists($base);
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->root) + 1));
                if (!preg_match('/\.(?:php|css|js|ya?ml)$/', $relative)) {
                    continue;
                }
                self::assertContains($relative, $paths, 'Source not inventoried in MANIFEST.md: ' . $relative);
            }
        }
    }

    public function test_adapter_documentation_describes_current_rest_contract(): void {
        $text = file_get_contents($this->root . '/docs/ADAPTER-CONTRACT.md');
        self::assertIsString($text);
        self::assertStringContainsString('current 1.0.0-rc.3 source includes authenticated private REST controllers', $text);
        self::assertStringNotContainsString('Phase 22E exposes guarded server-side PHP functions only; it has no REST', $text);
    }
}
