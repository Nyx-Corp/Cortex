<?php

declare(strict_types=1);

namespace Cortex\Bridge\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Interdit les noms de classes fourre-tout qui ne décrivent pas une responsabilité.
 *
 * @implements Rule<Class_>
 */
final class ForbidCatchAllClassNamesRule implements Rule
{
    private const FORBIDDEN_SUFFIXES = [
        'Service',
        'Manager',
        'Helper',
        'Util',
        'Utils',
        'Tool',
    ];

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (null === $node->name) {
            return [];
        }

        $className = $node->name->toString();

        foreach (self::FORBIDDEN_SUFFIXES as $suffix) {
            if (str_ends_with($className, $suffix)) {
                return [
                    RuleErrorBuilder::message(
                        \sprintf(
                            'La classe "%s" utilise le suffixe fourre-tout "%s". '
                            .'Trouve un nom qui décrit la responsabilité : '
                            .'MailSender, ScoreCalculator, PairingEngine...',
                            $className,
                            $suffix,
                        ),
                    )->identifier('cortex.forbidCatchAllClassName')->build(),
                ];
            }
        }

        return [];
    }
}
