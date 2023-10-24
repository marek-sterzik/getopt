<?php

namespace SPSOstrov\GetOpt;

class DefaultFormatter extends Formatter
{
    const INDENT_LEVEL = 2;
    const INDENT_CHAR = " ";

    private $width = 120;

    public function formatHelp(string $argv0, ?string $commandDescription, ?string $args, ?string $options): string
    {
        $usage = $this->formatUsage($argv0, isset($args), isset($options));
        $description = null;
        return $this->formatBlocks([
            ["Usage:", $usage],
            ["Arguments:", $args],
            ["Options:", $options],
            ["Description:", $description],
        ]);
    }

    public function formatOptionsHelp(array $options): ?string
    {
        $rows = $this->getOptionRows($options);
        if (empty($rows)) {
            return null;
        }
        $table = (new AsciiTable())->column([0, 1])->column([0, 2])->column()->width($this->width - self::INDENT_LEVEL);
        return $table->render($rows);
    }

    private function getOptionRows(array $options): array
    {
        $rows = [];
        $argTypesLong = [
            Option::ARG_NONE => "--%s",
            Option::ARG_REQUIRED => "--%s=<%s>",
            Option::ARG_OPTIONAL => "--%s[=<%s>]",
            Option::ARG_ARRAY => "[--%s=<%s>] ...",
        ];
        $argTypesShort = [
            Option::ARG_NONE => "-%s",
            Option::ARG_REQUIRED => "-%s <%s>",
            Option::ARG_OPTIONAL => "-%s[<%s>]",
            Option::ARG_ARRAY => "[-%s <%s>] ...",
        ];
        foreach ($options as $option) {
            $n = max(count($option['short']), count($option['long']));
            for ($i = 0; $i < $n; $i++) {
                $short = $option['short'][$i] ?? null;
                $long = $option['long'][$i] ?? null;
                $argName = $option['argName'] ?? 'arg';
                $shortArgType = $option['argType'];
                $longArgType = $option['argType'];
                if ($long !== null) {
                    $shortArgType = Option::ARG_NONE;
                }

                if ($short !== null) {
                    $short = sprintf($argTypesShort[$shortArgType], $short, $argName);
                }
                if ($long !== null) {
                    $long = sprintf($argTypesLong[$longArgType], $long, $argName);
                }

                if ($i === 0) {
                    $description = $option['description'];
                } elseif ($i + 1 == $n) {
                    $description = isset($option['description']) ? '^ see above' : null;
                } else {
                    $description = isset($option['description']) ? '.' : null;
                }

                $rows[] = [
                    $short,
                    $long,
                    $description,
                ];
            }
        }
        return $rows;
    }

    public function formatArgsHelp(array $args): ?string
    {
        $rows = [];
        $argTypes = [
            Option::ARG_NONE => null,
            Option::ARG_REQUIRED => "<%s>",
            Option::ARG_OPTIONAL => "[%s]",
            Option::ARG_ARRAY => "[%s] ...",
        ];
        foreach ($args as $arg) {
            $tpl = $argTypes[$arg['argType']] ?? null;
            if ($tpl === null) {
                continue;
            }
            $rows[] = [sprintf($tpl, $arg['argName']), $arg['description']];
        }
        if (empty($rows)) {
            return null;
        }
        $table = (new AsciiTable())->column([0, 2])->column()->width($this->width - self::INDENT_LEVEL);
        return $table->render($rows);
    }

    private function formatUsage(string $argv0, bool $hasArgs, bool $hasOptions): string
    {
        $usage = $argv0;
        if ($hasOptions) {
            $usage .= " [options]";
        }
        if ($hasArgs) {
            $usage .= " [args]";
        }
        $usage .= "\n";
        return $usage;
    }


    private function formatBlocks(array $blocks): string
    {
        return $this->formatBlocksRaw(array_map(function ($block) {
            return [isset($block[0]) ? ($block[0] . "\n") : null, isset($block[1]) ? $this->indent($block[1]) : null];
        }, $blocks), "\n");
    }

    private function formatBlocksRaw(array $blocks, string $delim): string
    {
        $output = "";
        $first = true;
        foreach ($blocks as list($blockCaption, $blockDescription)) {
            if ($blockDescription !== null) {
                $output .= ($first ? '' : $delim) . $blockCaption . $blockDescription;
                $first = false;
            }
        }
        return $output;
    }

    private function indent(string $string, int $level = 1): string
    {
        $indent = $this->getIndentation($level);
        return $indent . preg_replace_callback('/\n(.)/', function($matches) use ($indent) {
            return "\n" . $indent . $matches[1];
        }, $string);
    }

    private function getIndentation(int $level = 1): string
    {
        return str_repeat(self::INDENT_CHAR, self::INDENT_LEVEL * $level);
    }

}