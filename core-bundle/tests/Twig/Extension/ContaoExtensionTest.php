<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Twig\Extension;

use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\Extension\ContaoExtension;
use Contao\CoreBundle\Twig\Inheritance\DynamicExtendsTokenParser;
use Contao\CoreBundle\Twig\Inheritance\DynamicIncludeTokenParser;
use Contao\CoreBundle\Twig\Inheritance\TemplateHierarchyInterface;
use Contao\CoreBundle\Twig\Interop\ContaoEscaperNodeVisitor;
use Contao\CoreBundle\Twig\Interop\PhpTemplateProxyNodeVisitor;
use Contao\System;
use PHPUnit\Framework\MockObject\MockObject;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\Extension\EscaperExtension;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\TextNode;
use Twig\NodeTraverser;
use Twig\Source;
use Twig\TwigFunction;
use Webmozart\PathUtil\Path;

class ContaoExtensionTest extends TestCase
{
    public function testAddsTheNodeVisitors(): void
    {
        $nodeVisitors = $this->getContaoExtension()->getNodeVisitors();

        $this->assertCount(2, $nodeVisitors);

        $this->assertInstanceOf(ContaoEscaperNodeVisitor::class, $nodeVisitors[0]);
        $this->assertInstanceOf(PhpTemplateProxyNodeVisitor::class, $nodeVisitors[1]);
    }

    public function testAddsTheTokenParsers(): void
    {
        $tokenParsers = $this->getContaoExtension()->getTokenParsers();

        $this->assertCount(2, $tokenParsers);

        $this->assertInstanceOf(DynamicExtendsTokenParser::class, $tokenParsers[0]);
        $this->assertInstanceOf(DynamicIncludeTokenParser::class, $tokenParsers[1]);
    }

    public function testAddsTheFunctions(): void
    {
        $functions = $this->getContaoExtension()->getFunctions();

        $this->assertCount(8, $functions);

        $expectedFunctions = [
            'include' => ['all'],
            'contao_figure' => ['html'],
            'picture_config' => [],
            'insert_tag' => ['html'],
            'add_schema_org' => [],
            'contao_sections' => ['html'],
            'contao_section' => ['html'],
            'render_contao_backend_template' => ['html'],
        ];

        $node = $this->createMock(Node::class);

        foreach ($functions as $function) {
            $this->assertInstanceOf(TwigFunction::class, $function);

            $name = $function->getName();
            $this->assertArrayHasKey($name, $expectedFunctions);
            $this->assertSame($expectedFunctions[$name], $function->getSafe($node), $name);
        }
    }

    public function testIncludeFunctionDelegatesToTwigInclude(): void
    {
        $methodCalledException = new \Exception();

        $environment = $this->createMock(Environment::class);
        $environment
            ->expects($this->once())
            ->method('resolveTemplate')
            ->with('@Contao_Bar/foo.html.twig')
            ->willThrowException($methodCalledException)
        ;

        $hierarchy = $this->createMock(TemplateHierarchyInterface::class);
        $hierarchy
            ->method('getFirst')
            ->with('foo')
            ->willReturn('@Contao_Bar/foo.html.twig')
        ;

        $includeFunction = $this->getContaoExtension($environment, $hierarchy)->getFunctions()[0];
        $args = [$environment, [], '@Contao/foo'];

        $this->expectExceptionObject($methodCalledException);

        ($includeFunction->getCallable())(...$args);
    }

    public function testThrowsIfCoreIncludeFunctionIsNotFound(): void
    {
        $environment = $this->createMock(Environment::class);
        $environment
            ->method('getExtension')
            ->willReturnMap([
                [EscaperExtension::class, new EscaperExtension()],
                [CoreExtension::class, new class() extends AbstractExtension {
                }],
            ])
        ;

        $extension = new ContaoExtension(
            $environment,
            $this->createMock(TemplateHierarchyInterface::class)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The Twig\Extension\CoreExtension class was expected to register the "include" Twig function but did not.');

        $extension->getFunctions();
    }

    public function testAllowsOnTheFlyRegisteringTemplatesForInputEncoding(): void
    {
        $contaoExtension = $this->getContaoExtension();
        $escaperNodeVisitor = $contaoExtension->getNodeVisitors()[0];

        $traverser = new NodeTraverser(
            $this->createMock(Environment::class),
            [$escaperNodeVisitor]
        );

        $node = new ModuleNode(
            new FilterExpression(
                new TextNode('text', 1),
                new ConstantExpression('escape', 1),
                new Node([
                    new ConstantExpression('html', 1),
                    new ConstantExpression(null, 1),
                    new ConstantExpression(true, 1),
                ]),
                1
            ),
            null,
            new Node(),
            new Node(),
            new Node(),
            null,
            new Source('<code>', 'foo.html.twig')
        );

        $original = $node->__toString();

        // Traverse tree first time (no changes expected)
        $traverser->traverse($node);
        $iteration1 = $node->__toString();

        // Add rule that allows the template and traverse tree a second time (change expected)
        $contaoExtension->addContaoEscaperRule('/foo\.html\.twig/');

        // Adding the same rule should be ignored
        $contaoExtension->addContaoEscaperRule('/foo\.html\.twig/');

        $traverser->traverse($node);
        $iteration2 = $node->__toString();

        $this->assertSame($original, $iteration1);
        $this->assertStringNotContainsString("'contao_html'", $iteration1);
        $this->assertStringContainsString("'contao_html'", $iteration2);
    }

    public function testRenderLegacyTemplate(): void
    {
        $extension = $this->getContaoExtension();

        System::setContainer($this->getContainerWithContaoConfiguration(
            Path::canonicalize(__DIR__.'/../../Fixtures/Twig/legacy')
        ));

        $output = $extension->renderLegacyTemplate(
            'foo.html5',
            ['B' => ['overwritten B block']],
            ['foo' => 'bar']
        );

        $this->assertSame("foo: bar\noriginal A block\noverwritten B block", $output);
    }

    /**
     * @param Environment&MockObject $environment
     */
    private function getContaoExtension($environment = null, TemplateHierarchyInterface $hierarchy = null): ContaoExtension
    {
        if (null === $environment) {
            $environment = $this->createMock(Environment::class);
        }

        $environment
            ->method('getExtension')
            ->willReturnMap([
                [EscaperExtension::class, new EscaperExtension()],
                [CoreExtension::class, new CoreExtension()],
            ])
        ;

        return new ContaoExtension(
            $environment,
            $hierarchy ?? $this->createMock(TemplateHierarchyInterface::class)
        );
    }
}
