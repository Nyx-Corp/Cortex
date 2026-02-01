<?php

namespace Cortex\Component\Json;

final class JsonString implements \Stringable, \JsonSerializable
{
    private string $json;

    private array|\JsonSerializable|null $data = null;

    public function __construct(array|string|\JsonSerializable|null $payload)
    {
        if (empty($payload)) {
            $this->json = is_array($payload) ? '[]' : '';
            $this->data = is_array($payload) ? [] : null;

            return;
        }

        if (!is_string($payload)) {
            $this->json = @json_encode(
                value: $payload,
                flags: JSON_THROW_ON_ERROR
            );
            $this->data = $payload;
        } else {
            if (!json_validate($payload)) {
                throw new \InvalidArgumentException(json_last_error_msg());
            }

            $this->json = $payload;
        }
    }

    public function decode(): array|\JsonSerializable
    {
        if (!is_null($this->data)) {
            return $this->data;
        }

        $this->data = @json_decode(
            json: $this->json,
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );

        return $this->data;
    }

    public function __toString(): string
    {
        return $this->json;
    }

    public function jsonSerialize(): string
    {
        return $this->json;
    }
}
