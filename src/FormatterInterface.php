<?php

namespace SPSOstrov\GetOpt;

interface FormatterInterface
{
    public function formatHelp(string $argv0, ?string $args, ?string $options): string;
    
    /**
     * @param array<mixed> $options
     */
    public function formatOptionsHelp(array $options): ?string;
    
    /**
     * @param array<mixed> $args
     */
    public function formatArgsHelp(array $args): ?string;
}
