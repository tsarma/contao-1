<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Twig\Loader;

use Contao\CoreBundle\HttpKernel\Bundle\ContaoModuleBundle;
use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoaderWarmer;
use Contao\CoreBundle\Twig\Loader\TemplateLocator;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Twig\Error\LoaderError;
use Webmozart\PathUtil\Path;

class ContaoFilesystemLoaderTest extends TestCase
{
    public function testAddsPath(): void
    {
        $loader = $this->getContaoFilesystemLoader();

        $path1 = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/paths/1');
        $path2 = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/paths/2');

        $loader->addPath($path1);
        $loader->addPath($path2, 'Contao');
        $loader->addPath($path1, 'Contao_foo-Bar_Baz2');

        $this->assertTrue($loader->exists('@Contao/1.html.twig'));
        $this->assertTrue($loader->exists('@Contao/2.html.twig'));
        $this->assertTrue($loader->exists('@Contao_foo-Bar_Baz2/1.html.twig'));
        $this->assertFalse($loader->exists('@Contao_foo-Bar_Baz2/2.html.twig'));
    }

    public function testPrependsPath(): void
    {
        $loader = $this->getContaoFilesystemLoader();

        $path1 = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/paths/1');
        $path2 = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/paths/2');

        $loader->prependPath($path1);
        $loader->prependPath($path2, 'Contao');
        $loader->prependPath($path1, 'Contao_Foo');

        $this->assertTrue($loader->exists('@Contao/1.html.twig'));
        $this->assertTrue($loader->exists('@Contao/2.html.twig'));
        $this->assertTrue($loader->exists('@Contao_Foo/1.html.twig'));
        $this->assertFalse($loader->exists('@Contao_Foo/2.html.twig'));
    }

    public function testDoesNotAllowToAddNonContaoNamespacedPath(): void
    {
        $loader = $this->getContaoFilesystemLoader();

        $this->expectException(LoaderError::class);
        $this->expectExceptionMessage("Tried to register an invalid Contao namespace 'Foo'.");

        $loader->addPath('foo/path', 'Foo');
    }

    public function testDoesNotAllowToPrependNonContaoNamespacedPath(): void
    {
        $loader = $this->getContaoFilesystemLoader();

        $this->expectException(LoaderError::class);
        $this->expectExceptionMessage("Tried to register an invalid Contao namespace 'Foo'.");

        $loader->prependPath('foo/path', 'Foo');
    }

    public function testToleratesInvalidPaths(): void
    {
        $loader = $this->getContaoFilesystemLoader();

        $loader->addPath('non/existing/path');
        $loader->prependPath('non/existing/path');

        $this->assertEmpty($loader->getPaths());
    }

    public function testClearsPaths(): void
    {
        $loader = $this->getContaoFilesystemLoader();
        $loader->addPath(Path::canonicalize(__DIR__.'/../../Fixtures/Twig/paths/1'));

        $this->assertTrue($loader->exists('@Contao/1.html.twig'));

        $loader->clear();

        $this->assertFalse($loader->exists('@Contao/1.html.twig'));
    }

    public function testPersistsAndRecallsPaths(): void
    {
        $path1 = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/paths/1');
        $path2 = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/paths/2');

        $cacheAdapter = new ArrayAdapter();

        $loader1 = $this->getContaoFilesystemLoader($cacheAdapter);
        $loader1->addPath($path1);
        $loader1->addPath($path2, 'Contao_Foo');

        // Persist
        $this->assertEmpty(array_filter($cacheAdapter->getValues()));

        $loader1->persist();

        $this->assertNotEmpty(array_filter($cacheAdapter->getValues()));

        // Recall
        $loader2 = $this->getContaoFilesystemLoader($cacheAdapter);

        $this->assertSame([$path1], $loader2->getPaths());
        $this->assertSame([$path2], $loader2->getPaths('Contao_Foo'));
    }

    public function testPersistsAndRecallsHierarchy(): void
    {
        $locator = $this->createMock(TemplateLocator::class);
        $locator
            ->method('findTemplates')
            ->willReturn([
                'foo.html.twig' => '/path/to/templates/foo.html.twig',
            ])
        ;

        $cacheAdapter = new ArrayAdapter();

        $loader1 = $this->getContaoFilesystemLoader($cacheAdapter, $locator);
        $loader1->addPath(
            Path::canonicalize(__DIR__.'/../../Fixtures/Twig/paths/1'),
            'Contao_App',
            true
        );

        $chains = $loader1->getInheritanceChains();

        $this->assertNotEmpty($chains);

        // Persist
        $this->assertEmpty(array_filter($cacheAdapter->getValues()));

        $loader1->persist();

        $this->assertNotEmpty(array_filter($cacheAdapter->getValues()));

        // Recall
        $loader2 = $this->getContaoFilesystemLoader($cacheAdapter);

        $this->assertSame($chains, $loader2->getInheritanceChains());
    }

    public function testGetsCacheKey(): void
    {
        $path = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/paths/1');

        $loader = $this->getContaoFilesystemLoader(null, new TemplateLocator('/', [], []));
        $loader->addPath($path, 'Contao', true);

        $this->assertSame(
            Path::join($path, '1.html.twig'),
            Path::normalize($loader->getCacheKey('@Contao/1.html.twig'))
        );
    }

    public function testGetCacheKeyDelegatesToThemeTemplate(): void
    {
        $basePath = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance');

        $loader = $this->getContaoFilesystemLoader();
        $loader->addPath(Path::join($basePath, 'templates/'));
        $loader->addPath(Path::join($basePath, 'templates/my/theme'), 'Contao_Theme_my_theme');

        $this->assertSame(
            Path::join($basePath, 'templates/text.html.twig'),
            Path::normalize($loader->getCacheKey('@Contao/text.html.twig'))
        );

        // Reset and switch context
        $loader->reset();

        $page = new \stdClass();
        $page->templateGroup = 'templates/my/theme';

        $GLOBALS['objPage'] = $page;

        $this->assertSame(
            Path::join($basePath, 'templates/my/theme/text.html.twig'),
            Path::normalize($loader->getCacheKey('@Contao/text.html.twig'))
        );

        unset($GLOBALS['objPage']);
    }

    public function testGetsSourceContext(): void
    {
        $path = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/paths/1');

        $loader = $this->getContaoFilesystemLoader();
        $loader->addPath($path);

        $source = $loader->getSourceContext('@Contao/1.html.twig');

        $this->assertSame('@Contao/1.html.twig', $source->getName());
        $this->assertSame(Path::join($path, '1.html.twig'), Path::normalize($source->getPath()));
    }

    public function testGetSourceContextDelegatesToThemeTemplate(): void
    {
        $basePath = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance');

        $loader = $this->getContaoFilesystemLoader();
        $loader->addPath(Path::join($basePath, 'templates/'));
        $loader->addPath(Path::join($basePath, 'templates/my/theme'), 'Contao_Theme_my_theme');

        $source = $loader->getSourceContext('@Contao/text.html.twig');

        $this->assertSame('@Contao/text.html.twig', $source->getName());
        $this->assertSame(Path::join($basePath, 'templates/text.html.twig'), Path::normalize($source->getPath()));

        // Reset and switch context
        $loader->reset();

        $page = new \stdClass();
        $page->templateGroup = 'templates/my/theme';

        $GLOBALS['objPage'] = $page;

        $source = $loader->getSourceContext('@Contao/text.html.twig');

        $this->assertSame('@Contao_Theme_my_theme/text.html.twig', $source->getName());
        $this->assertSame(Path::join($basePath, 'templates/my/theme/text.html.twig'), Path::normalize($source->getPath()));

        unset($GLOBALS['objPage']);
    }

    public function testGetsSourceContextFromHtml5File(): void
    {
        $path = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/legacy/templates');

        $loader = $this->getContaoFilesystemLoader(null, new TemplateLocator('/', [], []));
        $loader->addPath($path);

        $source = $loader->getSourceContext('@Contao/foo.html5');

        $this->assertSame('@Contao/foo.html5', $source->getName());
        $this->assertSame(Path::join($path, 'foo.html5'), Path::normalize($source->getPath()));

        // Block names should end up as tokens separated by \n
        $this->assertSame("A\nB", $source->getCode());
    }

    public function testExists(): void
    {
        $loader = $this->getContaoFilesystemLoader();
        $loader->addPath(Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance/templates'));

        $this->assertTrue($loader->exists('@Contao/text.html.twig'));
        $this->assertFalse($loader->exists('@Contao/foo.html.twig'));
    }

    public function testExistsDelegatesToThemeTemplate(): void
    {
        $loader = $this->getContaoFilesystemLoader();
        $loader->addPath(
            Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance/templates/my/theme'),
            'Contao_Theme_my_theme'
        );

        $page = new \stdClass();
        $page->templateGroup = 'templates/my/theme';

        $GLOBALS['objPage'] = $page;

        $this->assertTrue($loader->exists('@Contao/text.html.twig'));
        $this->assertFalse($loader->exists('@Contao/foo.html.twig'));

        unset($GLOBALS['objPage']);
    }

    /**
     * @dataProvider provideTemplateFilemtimeSamples
     * @preserveGlobalState disabled
     * @runInSeparateProcess because filemtime gets mocked
     */
    public function testIsFresh(array $mtimeMappings, bool $isFresh, bool $expectWarning = false): void
    {
        $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance');
        $cacheTime = 1623924000;

        $locator = new TemplateLocator($projectDir, [], []);
        $loader = $this->getContaoFilesystemLoader(null, $locator);
        (new ContaoFilesystemLoaderWarmer($loader, $locator, $projectDir, 'prod'))->warmUp();

        $this->mockFilemtime($mtimeMappings);

        if ($expectWarning) {
            $this->expectWarning();
            $this->expectWarningMessageMatches('/filemtime\(\): stat failed for .*/');
        }

        $this->assertSame($isFresh, $loader->isFresh('@Contao/text.html.twig', $cacheTime));
    }

    public function provideTemplateFilemtimeSamples(): \Generator
    {
        $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance');
        $cacheTime = 1623924000;

        $fresh = $cacheTime;
        $expired = $cacheTime + 100;

        $textPath1 = Path::join($projectDir, 'templates/text.html.twig');
        $textPath2 = Path::join($projectDir, 'contao/templates/some/random/text.html.twig');

        yield 'all fresh in chain' => [
            [
                $textPath1 => $fresh,
                $textPath2 => $fresh,
            ],
            true,
        ];

        yield 'at least one expired  in chain' => [
            [
                $textPath1 => $fresh,
                $textPath2 => $expired,
            ],
            false,
        ];

        yield 'filemtime fails' => [
            [
                $textPath1 => $fresh,
                // do not register $textPath2
            ],
            false,
            true,
        ];
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess because filemtime gets mocked
     */
    public function testIsFreshDelegatesToThemeTemplate(): void
    {
        $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance');
        $cacheTime = 1623924000;
        $expired = $cacheTime + 100;

        $locator = new TemplateLocator($projectDir, [], []);
        $loader = $this->getContaoFilesystemLoader(null, $locator);
        (new ContaoFilesystemLoaderWarmer($loader, $locator, $projectDir, 'prod'))->warmUp();

        $this->mockFilemtime([
            Path::join($projectDir, 'templates/my/theme/text.html.twig') => $expired,
        ]);

        $page = new \stdClass();
        $page->templateGroup = 'templates/my/theme';

        $GLOBALS['objPage'] = $page;

        $this->assertFalse($loader->isFresh('@Contao/text.html.twig', $cacheTime));

        unset($GLOBALS['objPage']);
    }

    public function testGetsHierarchy(): void
    {
        $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance');

        $bundles = [
            'CoreBundle' => 'class',
            'FooBundle' => ContaoModuleBundle::class,
            'BarBundle' => 'class',
            'App' => 'class',
        ];

        $bundlesMetadata = [
            'App' => ['path' => Path::join($projectDir, 'contao')],
            'FooBundle' => ['path' => Path::join($projectDir, 'vendor-bundles/FooBundle')],
            'CoreBundle' => ['path' => Path::join($projectDir, 'vendor-bundles/CoreBundle')],
            'BarBundle' => ['path' => Path::join($projectDir, 'vendor-bundles/BarBundle')],
        ];

        $locator = new TemplateLocator($projectDir, $bundles, $bundlesMetadata);
        $loader = $this->getContaoFilesystemLoader(null, $locator);

        $warmer = new ContaoFilesystemLoaderWarmer($loader, $locator, $projectDir, 'prod');
        $warmer->warmUp();

        $expectedChains = [
            'text' => [
                $globalPath = Path::join($projectDir, '/templates/text.html.twig') => '@Contao_Global/text.html.twig',
                $appPath = Path::join($projectDir, '/contao/templates/some/random/text.html.twig') => '@Contao_App/text.html.twig',
                $barPath = Path::join($projectDir, '/vendor-bundles/BarBundle/contao/templates/text.html.twig') => '@Contao_BarBundle/text.html.twig',
                $fooPath = Path::join($projectDir, '/vendor-bundles/FooBundle/templates/any/text.html.twig') => '@Contao_FooBundle/text.html.twig',
                $corePath = Path::join($projectDir, '/vendor-bundles/CoreBundle/Resources/contao/templates/text.html.twig') => '@Contao_CoreBundle/text.html.twig',
            ],
            'bar' => [Path::join($projectDir, '/src/Resources/contao/templates/bar.html.twig') => '@Contao_App/bar.html.twig'],
            'baz' => [Path::join($projectDir, '/app/Resources/contao/templates/baz.html.twig') => '@Contao_App/baz.html.twig'],
        ];

        // Full hierarchy
        $this->assertSame(
            $expectedChains,
            $loader->getInheritanceChains(),
            'get all chains'
        );

        // Get first
        $this->assertSame(
            '@Contao_Global/text.html.twig',
            $loader->getFirst('text'),
            'get first template in chain'
        );

        // Next element by path
        $this->assertSame(
            '@Contao_Global/text.html.twig',
            $loader->getDynamicParent('text.html.twig', 'other/template.html.twig'),
            'chain: root -> global (using short name)'
        );

        $this->assertSame(
            '@Contao_Global/text.html.twig',
            $loader->getDynamicParent('text', 'other/template.html.twig'),
            'chain: root -> global (using identifier)'
        );

        $this->assertSame(
            '@Contao_App/text.html.twig',
            $loader->getDynamicParent('text.html.twig', $globalPath),
            'chain: global -> app'
        );

        $this->assertSame(
            '@Contao_BarBundle/text.html.twig',
            $loader->getDynamicParent('text.html.twig', $appPath),
            'chain: app -> bar bundle'
        );

        $this->assertSame(
            '@Contao_FooBundle/text.html.twig',
            $loader->getDynamicParent('text.html.twig', $barPath),
            'chain: bar bundle -> foo bundle'
        );

        $this->assertSame(
            '@Contao_CoreBundle/text.html.twig',
            $loader->getDynamicParent('text.html.twig', $fooPath),
            'chain: foo bundle -> core bundle'
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("The template '$corePath' does not have a parent 'text' it can extend from.");

        $loader->getDynamicParent('text.html.twig', $corePath);
    }

    /**
     * @dataProvider provideInvalidDynamicParentQueries
     */
    public function testGetDynamicParentThrowsIfTemplateCannotBeFound(string $identifier, string $sourcePath, string $expectedException): void
    {
        $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance');

        $locator = new TemplateLocator($projectDir, [], []);
        $loader = $this->getContaoFilesystemLoader(null, $locator);
        (new ContaoFilesystemLoaderWarmer($loader, $locator, $projectDir, 'prod'))->warmUp();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($expectedException);

        $loader->getDynamicParent($identifier, $sourcePath);
    }

    public function provideInvalidDynamicParentQueries(): \Generator
    {
        yield 'invalid chain' => [
            'random',
            '/path/to/template/x.html.twig',
            "The template 'random' could not be found in the template hierarchy.",
        ];

        $templatePath = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance/contao/templates/some/random/text.html.twig');

        yield 'last in chain' => [
            'text',
            $templatePath,
            "The template '$templatePath' does not have a parent 'text' it can extend from.",
        ];
    }

    public function testGetFirstThrowsIfChainDoesNotExist(): void
    {
        $loader = $this->getContaoFilesystemLoader();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("The template 'foo' could not be found in the template hierarchy.");

        $loader->getFirst('foo.html.twig');
    }

    /**
     * @param array<string, int> $pathToMtime
     */
    private function mockFilemtime(array $pathToMtime): void
    {
        $namespaces = ['Contao\CoreBundle\Twig\Loader', 'Twig\Loader'];

        foreach ($namespaces as $namespace) {
            $mock = sprintf(
                <<<'EOPHP'
                    namespace %s;

                    function filemtime(string $filename) {
                        if (null !== ($mtime = unserialize('%s')[\Webmozart\PathUtil\Path::canonicalize($filename)] ?? null)) {
                            return $mtime;
                        }

                        trigger_error("filemtime(): stat failed for $filename", E_USER_WARNING);
                    }
                    EOPHP,
                $namespace,
                serialize($pathToMtime)
            );

            eval($mock);
        }
    }

    private function getContaoFilesystemLoader(AdapterInterface $cacheAdapter = null, TemplateLocator $templateLocator = null): ContaoFilesystemLoader
    {
        return new ContaoFilesystemLoader(
            $cacheAdapter ?? new NullAdapter(),
            $templateLocator ?? $this->createMock(TemplateLocator::class),
            '/',
        );
    }
}
