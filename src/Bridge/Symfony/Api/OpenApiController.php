<?php

namespace Cortex\Bridge\Symfony\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

class OpenApiController
{
    public function __construct(
        private readonly OpenApiGenerator $generator,
        private readonly Environment $twig,
        private readonly string $projectDir,
        private readonly bool $debug,
        private readonly array $activeVersions = [1],
    ) {
    }

    /**
     * Serves the Scalar API documentation page.
     */
    public function docs(Request $request): Response
    {
        $latestVersion = max($this->activeVersions);
        $currentVersion = $request->query->getInt('version', $latestVersion);

        if (!in_array($currentVersion, $this->activeVersions, true)) {
            throw new NotFoundHttpException(sprintf('API version %d does not exist.', $currentVersion));
        }

        // In dev, spec is generated at runtime; in prod, served as static file
        $specUrl = $this->debug
            ? sprintf('/docs/api/v%d/openapi.yaml', $currentVersion)
            : sprintf('/openapi-v%d.yaml', $currentVersion);

        return new Response(
            $this->twig->render('@CortexBridge/theme/api/docs.html.twig', [
                'spec_url' => $specUrl,
                'versions' => $this->activeVersions,
                'current_version' => $currentVersion,
            ]),
            Response::HTTP_OK,
            ['Content-Type' => 'text/html']
        );
    }

    /**
     * Serves the OpenAPI YAML spec at runtime (dev only).
     */
    public function spec(int $version): Response
    {
        if (!$this->debug) {
            throw new NotFoundHttpException();
        }

        if (!in_array($version, $this->activeVersions, true)) {
            throw new NotFoundHttpException(sprintf('API version %d does not exist.', $version));
        }

        $spec = $this->generator->generate($version);

        return new Response(
            Yaml::dump($spec, 10, 2),
            Response::HTTP_OK,
            ['Content-Type' => 'application/x-yaml']
        );
    }
}
