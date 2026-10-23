<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * T-19: artículos I, II y III de la constitución, comprobados leyendo el código fuente.
 * Si alguien introduce una importación prohibida, esta prueba falla.
 */
final class LayerDependenciesTest extends TestCase
{
    private const SRC = __DIR__ . '/../../src/Library';

    /** @return array<string, string> ruta => contenido */
    private static function files(string $layer): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::SRC . '/' . $layer, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        return $files;
    }

    /** @param list<string> $forbidden prefijos de espacio de nombres prohibidos */
    private static function violations(string $layer, array $forbidden): array
    {
        $found = [];
        foreach (self::files($layer) as $path => $code) {
            foreach ($forbidden as $namespace) {
                $pattern = '/(?:\buse\s+|new\s+\\\\?|[\s(]\\\\)' . preg_quote($namespace, '/') . '/m';
                if (preg_match($pattern, $code) === 1) {
                    $found[] = basename($path) . " usa {$namespace}";
                }
            }
        }

        return $found;
    }

    public function test_article_I_domain_depends_on_nothing(): void
    {
        $this->assertSame([], self::violations('Domain', [
            'Library\\Application', 'Library\\Adapters', 'Library\\Bootstrap', 'Illuminate', 'App\\', 'PDO',
        ]));
    }

    public function test_article_II_application_only_knows_the_domain(): void
    {
        $this->assertSame([], self::violations('Application', [
            'Library\\Adapters', 'Library\\Bootstrap', 'Illuminate', 'App\\', 'PDO',
        ]));
    }

    public function test_article_III_adapters_are_only_instantiated_in_the_composition_root(): void
    {
        $offenders = [];
        foreach (['Domain', 'Application', 'Adapters'] as $layer) {
            foreach (self::files($layer) as $path => $code) {
                if (preg_match('/new\s+\\\\?(Library\\\\Adapters\\\\|Pg\w+Repository|PgReportReader|JwtTokenService|BcryptPasswordHasher|LocalDiskCoverStorage|SystemClock|RandomUuidGenerator)/', $code) === 1) {
                    $offenders[] = basename($path);
                }
            }
        }
        $this->assertSame([], $offenders);

        $composition = self::SRC . '/Bootstrap/Composition/PortBindings.php';
        $this->assertTrue(is_file($composition), 'existe un único punto de composición');
    }

    public function test_the_check_detects_a_forbidden_import(): void
    {
        $code = "<?php\nnamespace Library\\Domain\\Model;\nuse Illuminate\\Support\\Str;\n";
        $this->assertSame(1, preg_match('/(?:\buse\s+|new\s+\\\\?|[\s(]\\\\)' . preg_quote('Illuminate', '/') . '/m', $code));
    }
}
