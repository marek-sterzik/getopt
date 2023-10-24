<?php

namespace SPSOstrov\GetOpt;

interface FormatterInterface
{
    public function formatHelp(string $argv0, ?string $args, ?string $options): string;
    public function formatOptionsHelp(array $options): ?string;
    public function formatArgsHelp(array $args): ?string;
}
