<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class TypedClassConstantCompatibilityTest extends TestCase
{
    /**
     * @return array<int, array<int, string>>
     */
    public static function runtimePhpSourceProvider(): array
    {
        $moduleRoot = dirname(__DIR__, 3);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS)
        );

        $runtimeFiles = [];
        $moduleRootLength = strlen($moduleRoot) + 1;

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            if (!str_ends_with($fileInfo->getFilename(), '.php')) {
                continue;
            }

            $absolutePath = $fileInfo->getPathname();
            $relativePath = substr($absolutePath, $moduleRootLength);

            if (str_starts_with($relativePath, 'Test/')
                || str_starts_with($relativePath, 'vendor/')
            ) {
                continue;
            }

            $runtimeFiles[] = [$relativePath];
        }

        usort(
            $runtimeFiles,
            static fn(array $a, array $b): int => strnatcasecmp($a[0], $b[0])
        );

        return $runtimeFiles;
    }

    /**
     * @return array<int, array<int, array<int, string>|string>>
     */
    public static function classConstantDeclarationProvider(): array
    {
        return [
            [
                'const string FOO = "typed";',
                ['const string FOO ='],
            ],
            [
                'private const array BAR = ["typed"];',
                ['private const array BAR ='],
            ],
            [
                "final\npublic const string BAZ = 'x';",
                ['final public const string BAZ ='],
            ],
            [
                "public const\nstring QUX = \"yes\";",
                ['public const string QUX ='],
            ],
            [
                'public const int BAZ = 42;',
                ['public const int BAZ ='],
            ],
            [
                'private const BLAH = "untyped";',
                [],
            ],
            [
                'protected const BLAH = ["no type" , "another"], BAZ = "second";',
                [],
            ],
        ];
    }

    #[DataProvider('runtimePhpSourceProvider')]
    public function testFeatureSourceFilesDoNotUseTypedClassConstants(string $relativePath): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $filePath = $moduleRoot . '/' . $relativePath;

        $source = (string) file_get_contents($filePath);

        self::assertStringContainsString('<?php', $source);

        $foundTyped = $this->collectTypedClassConstantDeclarations($source);

        self::assertSame(
            [],
            $foundTyped,
            sprintf(
                'Typed class constants are not supported on some runtimes. Update this module source file: %s. Found:%s',
                $relativePath,
                PHP_EOL . ' - ' . implode(PHP_EOL . ' - ', $foundTyped)
            )
        );
    }

    #[DataProvider('classConstantDeclarationProvider')]
    public function testClassConstantTypedDetectionWorksForVisibleAndNonVisibleDeclarations(string $source, array $expectedMatches): void
    {
        self::assertSame(
            $expectedMatches,
            $this->collectTypedClassConstantDeclarations($source),
            'Typed class-constant detection no longer identifies constants consistently.'
        );
    }

    /**
     * @return string[]
     */
    private function collectTypedClassConstantDeclarations(string $source): array
    {
        $tokenizableSource = str_starts_with(trim($source), '<?') ? $source : "<?php\n" . $source;
        $tokens = token_get_all($tokenizableSource);
        $matches = [];

        for ($i = 0; $i < count($tokens); $i++) {
            if (!$this->isTokenType($tokens[$i], T_CONST)) {
                continue;
            }

            $equalIndex = $this->findNextEqualsToken($tokens, $i + 1);
            if (!is_int($equalIndex)) {
                continue;
            }

            $declarationStart = $this->findTypedDeclarationStart($tokens, $i);
            if (!$this->isTypedClassConstantDeclaration($tokens, $i + 1, $equalIndex)) {
                continue;
            }

            $matches[] = $this->formatDeclarationText($tokens, $declarationStart, $equalIndex);
        }

        return $matches;
    }

    private function findNextEqualsToken(array $tokens, int $startIndex): ?int
    {
        $tokenCount = count($tokens);

        for ($i = $startIndex; $i < $tokenCount; $i++) {
            if (is_array($tokens[$i])) {
                if (in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                continue;
            }

            if ($tokens[$i] === '=') {
                return $i;
            }
        }

        return null;
    }

    private function isTypedClassConstantDeclaration(array $tokens, int $afterConstIndex, int $equalIndex): bool
    {
        $meaningfulTokenCount = 0;

        for ($i = $afterConstIndex; $i < $equalIndex; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                $id = $token[0];
                if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $meaningfulTokenCount++;
                continue;
            }

            if ($token === '|' || $token === '?' || $token === '\\') {
                $meaningfulTokenCount++;
            }
        }

        return $meaningfulTokenCount > 1;
    }

    private function findTypedDeclarationStart(array $tokens, int $constIndex): int
    {
        $start = $constIndex;

        for ($i = $constIndex - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (is_array($token)) {
                $id = $token[0];
                if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                if ($id === T_FINAL || $id === T_PUBLIC || $id === T_PROTECTED || $id === T_PRIVATE) {
                    $start = $i;
                    continue;
                }

                break;
            }

            break;
        }

        return $start;
    }

    private function isTokenType(mixed $token, int $type): bool
    {
        return is_array($token) && $token[0] === $type;
    }

    private function formatDeclarationText(array $tokens, int $start, int $equalIndex): string
    {
        $raw = '';

        for ($i = $start; $i <= $equalIndex; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $raw .= ' ';
                    continue;
                }

                $raw .= $token[1];
                continue;
            }

            $raw .= $token;
        }

        return preg_replace('/\s+/', ' ', trim($raw));
    }
}
