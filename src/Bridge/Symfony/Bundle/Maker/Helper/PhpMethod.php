<?php

namespace Cortex\Bridge\Symfony\Bundle\Maker\Helper;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;

final class PhpMethod
{
    /** @var PhpVar[] */
    private array $parameters;

    public function __construct(
        private string $name,
        private string $body = '',    // Contenu brut (corps PHP)
        array $parameters = [],
        private ?PhpVar $returnType = null,
        private string $doc = '',      // Commentaire PHPDoc complet
    ) {
        $this->parameters = array_map(
            fn (PhpVar $var) => $var,
            $parameters
        );
    }

    public function getUses(): \Generator
    {
        if ($this->returnType && !$this->returnType->isScalar()) {
            yield $this->returnType->fqcn;
        }

        foreach ($this->parameters as $var) {
            if (!$var->isScalar()) {
                yield $var->fqcn;
            }
        }
    }

    public function toPhpParserExpr(mixed $value): Expr
    {
        return match (true) {
            is_string($value) => new Scalar\String_($value),
            is_int($value) => new Scalar\LNumber($value),
            is_float($value) => new Scalar\DNumber($value),
            is_bool($value) => new Expr\ConstFetch(new Name($value ? 'true' : 'false')),
            null === $value => new Expr\ConstFetch(new Name('null')),
            is_array($value) => new Expr\Array_([]), // simpliste, tu peux l'améliorer
            default => throw new \InvalidArgumentException('Unsupported default value type'),
        };
    }

    public function addToNode(Stmt\Class_ $node, $parser, $factory): ?Stmt\ClassMethod
    {
        // Don't generate duplicates
        foreach ($node->getMethods() as $method) {
            if ($method->name->toString() === $this->name) {
                return null;
            }
        }

        $method = $factory->method($this->name)
            ->makePublic()
            ->addStmts($parser($this->body))
        ;

        if (!empty($this->parameters) && $this->doc) {
            $this->doc .= "\n *";
        }

        foreach ($this->parameters as $var) {
            $param = $factory->param($var->name);

            $type = $var->type;

            if ($var->hasDefault) {
                if (null === $var->default) {
                    $type = new NullableType(
                        $var->isScalar()
                            ? new Name($var->type)
                            : new Identifier($var->type)
                    );
                }

                $param->setDefault($this->toPhpParserExpr($var->default));
            }

            $param->setType($type);

            $method->addParam($param);

            $this->doc .= sprintf(
                "\n * @param %s $%s %s",
                $var->type,
                $var->name,
                $var->doc
            );
        }

        if ($this->returnType) {
            $method->setReturnType($this->returnType->type);
            $this->doc .= sprintf(
                '%s @return %s %s',
                $this->doc ? "\n *\n *" : '',
                $this->returnType->type,
                $this->returnType->doc
            );
        }

        if ($this->doc) {
            $method->setDocComment(trim(<<<PHPDOC
                    /**
                     * $this->doc
                     */
                PHPDOC));
        }

        return $method->getNode();
    }
}
