<?php

namespace SPSOstrov\GetOpt;

class OptionTokenizer
{
    const STANDALONE_CHARS = "[]|:?*={},$~";
    public function tokenize(string $optionDescriptor): iterable
    {
        $n = strlen($optionDescriptor);
        $acc = "";
        $accType = null;
        $accIndex = null;
        $quoteChar = null;
        $i = 0;
        $read = true;
        $state = "normal";
        while ($i < $n) {
            $char = substr($optionDescriptor, $i, 1);
            if ($accType === null && in_array($state, ['normal', 'helpoptions'])) {
                if (ctype_space($char)) {
                    $state = "help";
                    $read = false;
                } elseif (preg_match('/[a-zA-Z0-9_.-]/', $char)) {
                    $acc = $char;
                    $accType = "identifier";
                    $accIndex = $i;
                } elseif ($char === "'" || $char === '"') {
                    $acc = $char;
                    $accType = "quote";
                    $quoteChar = $char;
                    $accIndex = $i;
                } elseif ($char === "@") {
                    $accType = '@';
                    $acc = $char;
                    $accIndex = $i;
                } elseif (strpos(self::STANDALONE_CHARS, $char) !== false) {
                    if ($char === '[' && $state === 'helpoptions') {
                        yield ['error', $char, $i];
                    } else {
                        yield [$char, $char, $i];
                        if ($char === ']' && $state === 'helpoptions') {
                            $state = 'help';
                        }
                    }
                } else {
                    yield ["error", $char, $i];
                }
            } elseif ($state === 'help') {
                if ($accType !== 'escape' && $char === '[') {
                    if ($accType !== null) {
                        yield [$accType, $acc, $accIndex];
                        $accType = null;
                        $acc = "";
                    }
                    yield [$char, $char, $i];
                    $state = 'helpoptions';
                } elseif ($accType !== 'escape' && $char === ']') {
                    yield ["error", $char, $i];
                } elseif (($accType === null || $accType === 'space') && ctype_space($char)) {
                    if ($accType === null) {
                        $accType = "space";
                        $acc = '';
                        $accIndex = $i;
                    }
                    $acc .= $char;
                } else {
                    if ($accType !== null && $accType !== 'help' && $accType !== 'escape') {
                        yield [$accType, $acc, $accIndex];
                        $accType = null;
                        $acc = "";
                    }
                    if ($accType === null) {
                        $accType = "help";
                        $accIndex = $i;
                    }
                    if ($accType === 'help' && $char === '\\') {
                        $accType = "escape";
                    } elseif ($accType === "escape") {
                        if ($char !== '[' && $char !== ']' && $char !== '\\') {
                            $acc .= '\\' . $char;
                        } else {
                            $acc .= $char;
                        }
                        $accType = "help";
                    } else {
                        $acc .= $char;
                    }
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
