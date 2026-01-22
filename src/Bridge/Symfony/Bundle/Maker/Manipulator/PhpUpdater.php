<?php

namespace Cortex\Bridge\Symfony\Bundle\Maker\Manipulator;

use Cortex\Bridge\Symfony\Bundle\Maker\Helper\PhpMethod;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserAbstract;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

final class PhpUpdater extends NodeVisitorAbstract
{
    /** @var Node[] */
    private array $phpCode;

    /** @var PhpMethod[] */
    private array $methods = [];

    private PrettyPrinter $printer {
        get => $this->printer ??= new PrettyPrinter\Standard();
    }

    private ParserAbstract $phpParser {
        get => $this->phpParser ??= new ParserFactory()
            ->createForVersion(PhpVersion::getHostVersion());
    }

    private BuilderFactory $builderFactory {
        get => $this->builderFactory ??= new BuilderFactory();
    }

    private ?NodeTraverser $traverser = null {
        get {
            if ($this->traverser) {
                return $this->traverser;
            }

            $this->traverser = new NodeTraverser();
            $this->traverser->addVisitor($this);

            return $this->traverser;
        }
    }

    public function __construct(
        private SplFileInfo $phpFile,
        private Filesystem $filesystem,
    ) {
        if (!$this->phpFile->isReadable()) {
            throw new \InvalidArgumentException(sprintf('The provided config "%s" is not readable.', $this->phpFile->getPathname()));
        }

        $this->phpCode = $this->phpParser->parse(
            $this->phpFile->getContents()
        );
    }

    public function addMethod(PhpMethod $phpMethod): self
    {
        $this->methods[] = $phpMethod;

        return $this;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            foreach ($this->methods as $method) {
                foreach ($method->getUses() as $fqcn) {
                    if (!array_any(
                        $node->stmts,
                        fn (Node $stmt) => $stmt instanceof Node\Stmt\Use_ && $stmt->uses[0]->name->toString() === $fqcn
                    )) {
                        array_unshift(
                            $node->stmts,
                            $this->builderFactory->use($fqcn)->getNode()
                        );
                    }
                }
            }

            return $node;
        }

        if (!$node instanceof Node\Stmt\Class_) {
            return null;
        }

        foreach ($this->methods as $method) {
            $result = $method->addToNode(
                $node,
                fn (string $code) => $this->phpParser->parse('<?php '.$code.' ?>'),
                $this->builderFactory
            );

            if ($result instanceof Node) {
                $node->stmts[] = $result;
            }
        }

        return $node;
    }

    public function save(): SplFileInfo
    {
        $this->phpCode = $this->traverser->traverse($this->phpCode);

        $this->filesystem->dumpFile(
            $this->phpFile->getPathname(),
            $this->printer->prettyPrintFile($this->phpCode)
        );

        return $this->phpFile;
    }
}
