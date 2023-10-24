<?php

namespace SPSOstrov\GetOpt;

abstract class Formatter implements FormatterInterface
{
    private static $formatter = null;

    public static function instance(?FormatterInterface $formatter = null): FormatterInterface
    {
        if ($formatter !== null) {
            return $formatter;
        }
        if (self::$formatter === null) {
            self::setDefault(null);
        }
        return self::$formatter;
    }

    public static function setDefault(?FormatterInterface $formatter = null): void
    {
        self::$formatter = $formatter ?? (new DefaultFormatter());
    }

    abstract public function formatHelp(string $argv0, ?string $args, ?string $options): string;
    abstract public function formatOptionsHelp(array $options): ?string;
    abstract public function formatArgsHelp(array $args): ?string;
}
