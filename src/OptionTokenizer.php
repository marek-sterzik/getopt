<?php

namespace SPSOstrov\GetOpt;

class OptionTokenizer
{
    private const STANDALONE_CHARS = "[]|:?*={},$~";

    /**
     * @return \Generator<array<mixed>>
     */
    public function tokenize(string $optionDescriptor): iterable
    {
        $n = strlen($optionDescriptor);
        $acc = "";
        $accType = null;
        $accIndex = null;
        $quoteChar = null;
        $i = 0;
        $posIndex = 1;
        $posLine = 1;
        $read = true;
        $state = "normal";
        while ($i < $n) {
            $position = sprintf("%d:%d", $posLine, $posIndex);
            $char = substr($optionDescriptor, $i, 1);
            if ($accType === null && in_array($state, ['normal', 'helpoptions'])) {
                if (ctype_space($char) || $char === "\n" || $char === "\r") {
                    $state = "help";
                    $read = false;
                } elseif (preg_match('/[a-zA-Z0-9_.-]/', $char)) {
                    $acc = $char;
                    $accType = "identifier";
                    $accIndex = $position;
                } elseif ($char === "'" || $char === '"') {
                    $acc = $char;
                    $accType = "quote";
                    $quoteChar = $char;
                    $accIndex = $position;
                } elseif ($char === "@") {
                    $accType = '@';
                    $acc = $char;
                    $accIndex = $position;
                } elseif ($char === "\n" || $char === "\r") {
                    $accType = 'nl';
                    $acc = $char;
                    $accIndex = $position;
                } elseif (strpos(self::STANDALONE_CHARS, $char) !== false) {
                    if ($char === '[' && $state === 'helpoptions') {
                        yield ['error', $char, $position];
                    } else {
                        yield [$char, $char, $position];
                        if ($char === ']' && $state === 'helpoptions') {
                            $state = 'help';
                        }
                    }
                } else {
                    yield ["error", $char, $position];
                }
            } elseif ($accType === null && $state === 'help') {
                if ($char === "\n" || $char === "\r") {
                    $acc = $char;
                    $accIndex = $position;
                    $accType = "nl";
                } elseif (ctype_space($char)) {
                    $accType = "space";
                    $accIndex = $position;
                    $acc = $char;
                } elseif ($char === '[') {
                    yield [$char, $char, $position];
                    $state = 'helpoptions';
                } elseif ($char === ']') {
                    yield ['error', $char, $position];
                } elseif ($char === '\\') {
                    $acc = $char;
                    $accIndex = $position;
                    $accType = "escape";
                } else {
                    $acc = $char;
                    $accType = "help";
                    $accIndex = $position;
                }
            } elseif ($accType === "help") {
                if (in_array($char, ["\n", "\r", "\\", "[", "]"])) {
                    yield [$accType, $acc, $accIndex];
                    $accType = null;
                    $acc = '';
                    $accIndex = null;
                    $read = false;
                } else {
                    $acc .= $char;
                }
            } elseif ($accType === "escape") {
                if ($char === "\n" || $char === "\r") {
                    yield ["help", $acc, $accIndex];
                    $read = false;
                } else {
                    yield ["help", $char, $position];
                }
                $acc = '';
                $accType = null;
                $accIndex = null;
            } elseif ($accType === "nl") {
                if (($char === "\n" || $char === "\r") && $char !== $acc) {
                    $acc .= $char;
                } else {
                    $read = false;
                }
                yield [$accType, $acc, $accIndex];
                $accType = null;
                $acc = '';
                $accIndex = null;
                $state = "normal";
            } elseif ($accType === "space") {
                if ($char !== "\n" && $char !== "\r" && ctype_space($char)) {
                    $acc .= $char;
                } else {
                    yield [$accType, $acc, $accIndex];
                    $accType = null;
                    $acc = '';
                    $accIndex = null;
                    $read = false;
                }
            } elseif ($accType === "quote") {
                $acc .= $char;
                if ($char === $quoteChar) {
                    yield ["quote", $acc, $accIndex];
                    $acc = "";
                    $accType = null;
                    $accIndex = null;
                } elseif ($char === '\\') {
                    $accType = 'quote-escape';
                }
            } elseif ($accType === "quote-escape") {
                $acc .= $char;
                $accType = 'quote';
            } elseif ($accType === "identifier") {
                if (preg_match('/[a-zA-Z0-9_.-]/', $char)) {
                    $acc .= $char;
                } else {
                    yield [$accType, $acc, $accIndex];
                    $accType = null;
                    $acc = "";
                    $accIndex = null;
                    $read = false;
                }
            } elseif ($accType === "@") {
                if ($char === '@') {
                    $acc .= $char;
                    if (strlen($acc) >= 3) {
                        yield ['@', $acc, $accIndex];
                        $acc = "";
                        $accType = null;
                        $accIndex = null;
                    }
                } else {
                    yield ['@', $acc, $accIndex];
                    $acc = "";
                    $accType = null;
                    $accIndex = null;
                    $read = false;
                }
            }
            if ($read) {
                $i++;
                $posIndex++;
            } else {
                $read = true;
            }
        }
        if ($accType !== null) {
            if ($accType === 'escape') {
                $accType = 'help';
            }
            if ($accType === "quote" || $accType === "quote-escape") {
                yield ["error", $acc, $accIndex];
            } else {
                yield [$accType, $acc, $accIndex];
            }
        }
    }
}
