<?php

namespace Cortex\Bridge\Symfony\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ApiTokenProviderInterface $tokenProvider,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization', '');
        $rawToken = substr($authHeader, 7); // Strip "Bearer "

        if ('' === $rawToken) {
            throw new CustomUserMessageAuthenticationException('Missing API token.');
        }

        return new SelfValidatingPassport(
            new UserBadge($rawToken, function (string $token) {
                $user = $this->tokenProvider->findUserByToken($token);

                if (null === $user) {
                    throw new CustomUserMessageAuthenticationException('Invalid API token.');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Stateless — let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
