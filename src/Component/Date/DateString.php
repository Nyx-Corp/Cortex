<?php

namespace Cortex\Component\Date;

final class DateString implements \Stringable
{
    private const string DATE_FORMAT = 'Y-m-d H:i:s';

    private readonly \DateTimeInterface $date;

    public function __construct(
        string|\DateTimeInterface $date,
        string $format = self::DATE_FORMAT,
    ) {
        if (is_string($date)) {
            if (empty($date)) {
                throw new \InvalidArgumentException('Date as string cannot be empty');
            }

            $parsedDate = \DateTimeImmutable::createFromFormat($format, $date);
            if (false === $parsedDate) {
                $errors = \DateTimeImmutable::getLastErrors();
                throw new \InvalidArgumentException(sprintf('Error while parsing date "%s" from format "%s" : ', $date, $format, implode(', ', $errors['errors'] ?? [])));
            }

            $date = $parsedDate;
        }

        $this->date = new DateTimeImmutable($date->format($format));
    }

    public function parse(): \DateTimeInterface
    {
        return $this->date;
    }

    public function format(string $format = self::DATE_FORMAT): string
    {
        return $this->date->format($format);
    }

    public function __toString(): string
    {
        return $this->format(self::DATE_FORMAT);
    }
}
