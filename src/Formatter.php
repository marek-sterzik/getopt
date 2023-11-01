<?php

namespace SPSOstrov\GetOpt;

abstract class Formatter implements FormatterInterface
{
    /** @var FormatterInterface|null */
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

    public static function setDefault(?FormatterInterface $formatter = null): ?FormatterInterface
    {
        $oldFormatter = self::$formatter;
        self::$formatter = $formatter ?? (new DefaultFormatter());
        return $oldFormatter;
    }

    abstract public function formatHelp(string $argv0, ?string $args, ?string $options): string;
    abstract public function formatOptionsHelp(array $options): ?string;
    abstract public function formatArgsHelp(array $args): ?string;
}
