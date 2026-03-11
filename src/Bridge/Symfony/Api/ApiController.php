<?php

namespace Cortex\Bridge\Symfony\Api;

use Cortex\Bridge\Symfony\Form\CommandFormType;
use Cortex\Component\Exception\DomainException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiController
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly VersionTransformerCollection $transformers,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $commandClass = $request->attributes->get('_cortex_command');
        $formType = $request->attributes->get('_cortex_form_type');
        $version = $request->attributes->getInt('_cortex_api_version', 1);
        $isDeprecated = $request->attributes->getBoolean('_cortex_deprecated', false);
        $sunset = $request->attributes->get('_cortex_sunset');

        if (!$commandClass || !$formType) {
            return new JsonResponse(['error' => 'Missing action configuration.'], Response::HTTP_BAD_REQUEST);
        }

        // Merge JSON body + route params
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $decoded = json_decode($content, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                return new JsonResponse(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
            }
            $data = $decoded;
        }

        // Add route parameters (uuid etc.)
        foreach ($request->attributes->get('_route_params', []) as $key => $value) {
            if (!str_starts_with($key, '_')) {
                $data[$key] = $value;
            }
        }

        // Apply version transformers on request data
        $data = $this->transformers->transformRequest($commandClass, $data, $version);

        $formOptions = ['csrf_protection' => false];
        if (CommandFormType::class === $formType) {
            $formOptions['command_class'] = $commandClass;
        }

        $form = $this->formFactory->create($formType, null, $formOptions);

        try {
            $form->submit($data);

            if (!$form->isValid()) {
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $field = $error->getOrigin()?->getName() ?? 'global';
                    $errors[$field][] = $error->getMessage();
                }

                return $this->respond([
                    'error' => 'Validation failed.',
                    'violations' => $errors,
                ], Response::HTTP_BAD_REQUEST, $version, $isDeprecated, $sunset);
            }

            $result = $form->getData();

            // Apply version transformers on response
            $result = $this->transformers->transformResponse($commandClass, $result, $version);

            return $this->respond(
                $this->serialize($result),
                Response::HTTP_OK,
                $version,
                $isDeprecated,
                $sunset
            );
        } catch (DomainException $e) {
            return $this->respond([
                'error' => $e->getMessage(),
                'domain' => $e->getDomain(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY, $version, $isDeprecated, $sunset);
        }
    }

    private function respond(mixed $data, int $status, int $version, bool $deprecated, ?string $sunset): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('X-API-Version', (string) $version);

        if ($deprecated) {
            $response->headers->set('Deprecation', 'true');
            if ($sunset) {
                $response->headers->set('Sunset', new \DateTimeImmutable($sunset)->format('D, d M Y H:i:s \G\M\T'));
            }
        }

        return $response;
    }

    private function serialize(mixed $value): mixed
    {
        if (is_object($value)) {
            if ($value instanceof \BackedEnum) {
                return $value->value;
            }

            if ($value instanceof \DateTimeInterface) {
                return $value->format('c');
            }

            if ($value instanceof \Stringable) {
                $vars = get_object_vars($value);
                if (empty($vars)) {
                    return (string) $value;
                }
            }

            $result = [];
            foreach (get_object_vars($value) as $key => $val) {
                $result[$key] = $this->serialize($val);
            }

            return $result;
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->serialize($v), $value);
        }

        return $value;
    }
}
