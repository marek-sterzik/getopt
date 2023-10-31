<?php

namespace SPSOstrov\GetOpt;

use Exception;

class OptionParser
{
    private $data;
    private $index;
    private $tokens;
    private $optionsLength;
    private $errorPosition;
    private $lastPosition;

    public function parse(string $options): array
    {
        $this->initialize($options);
        while ($this->readToken(false) !== null) {
            $this->skipAllEmpty();
            if ($this->readToken(false) !== null) {
                $this->data[$this->index] = [];
                $this->loadIsArgument();
                $this->merge($this->loadOptionList());
                $this->loadByTypeMultiple([
                    '{' => 'loadQuantity',
                    ':' => 'loadArgType',
                    '?' => 'loadArgType',
                    '*' => 'loadArgType',
                    '~' => 'loadArgType',
                    '=' => 'loadChecker',
                    '[' => 'loadWriteRules',
                ], true);
                $token = $this->readToken(false);
                if ($token !== null) {
                    if ($token[0] === 'space') {
                        $this->loadDescription();
                    } elseif ($token[0] === 'nl') {
                        $this->readToken();
                    }
                }
                $this->index++;
            }
        }

        $token = $this->readToken(false);
        if ($token !== null) {
            $this->unexpectedToken($token);
        }

        return $this->data;
    }

    private function initialize(string $options)
    {
        $this->data = [];
        $this->index = 0;
        $this->tokens = (new OptionTokenizer())->tokenize($options);
        $this->tokens->rewind();
        $this->optionsLength = strlen($options);
        $this->errorPosition = null;
        $this->lastPosition = "1:1";
    }


    private function merge(array $data): void
    {
        $this->data[$this->index] = array_merge($this->data[$this->index], $data);
    }

    private function keyExists(string $key): bool
    {
        return array_key_exists($key, $this->data[$this->index]);
    }

    private function skipAllEmpty(): void
    {
        while ($this->skipEmpty()) {
        }
    }

    private function skipEmpty(): bool
    {
        $token = $this->readToken(false);
        $space = false;
        if ($token !== null && $token[0] === 'space') {
            $token = $this->readNextToken();
            $space = true;
        }
        if ($token !== null && $token[0] !== 'nl') {
            if ($space) {
                $this->unexpectedToken($token);
            } else {
                return false;
            }
        }
        $this->readToken();
        return true;
    }

    private function loadIsArgument(): void
    {
        $token = $this->readToken(false);
        $isArgument = false;
        if ($token !== null && $token[0] === '$') {
            $isArgument = true;
            $this->readToken();
        }
        $this->merge(['isArgument' => $isArgument]);
    }

    private function loadWriteRule(): array
    {
        $optionList = $this->loadOptionList(true);
        $identifiers = [];
        foreach (['short', 'long', 'identifier'] as $type) {
            foreach ($optionList[$type] as $short) {
                $identifiers[] = $short;
            }
        }
        $token = $this->readToken(false);
        $from = '$';
        $type = '$';
        if ($token !== null && $token[0] === '=') {
            $token = $this->readNextToken();
            if ($token === null) {
                $this->unexpectedToken($token);
            }
            if ($token[0] === 'identifier') {
                $from = $token[1];
                $type = 'var';
            } elseif ($token[0] === '@') {
                $from = $token[1];
                $type = '@';
            } elseif ($token[0] === '$') {
                $from = '$';
                $type = '$';
            } elseif ($token[0] === 'quote') {
                $from = $this->unquote($token);
                if ($from === null) {
                    $this->unexpectedToken($token);
                }
                $type = 'const';
            } else {
                $this->unexpectedToken($token);
            }
            $this->readToken();
        }
        return ['from' => $from, 'to' => $identifiers, 'type' => $type];
    }

    private function unquote(?array $token): ?string
    {
        if ($token === null || $token[0] !== 'quote') {
            return null;
        }
        $decoded = @json_decode($token[1], true);
        if (!is_string($decoded)) {
            return null;
        }
        return $decoded;
    }

    private function loadWriteRules(): void
    {
        $rules = [];
        $token = $this->readToken(false);
        if ($token === null || $token[0] !== '[') {
            $this->unexpectedToken($token);
        }
        $token = $this->readNextToken();
        $first = true;
        while ($token === null || $token[0] !== ']') {
            if ($token === null) {
                $this->unexpectedToken($token);
            }
            if ($first) {
                $first = false;
            } else {
                if ($token[0] !== ',') {
                    $this->unexpectedToken($token);
                }
                $token = $this->readNextToken();
            }
            $rules[] = $this->loadWriteRule();
            $token = $this->readToken(false);
            if ($token === null || ($token[0] !== ']' && $token[0] !== ',')) {
                $this->unexpectedToken($token);
            }
        }
        $this->readToken();
        $this->merge(['rules' => $rules]);
    }

    private function loadChecker(): void
    {
        $token = $this->readToken(false);
        if ($token === null || $token[1] !== "=") {
            $this->unexpectedToken($token);
        }
        $token = $this->readNextToken();
        if ($token === null || $token[0] !== 'identifier' || !$this->isCheckerIdentifier($token[1])) {
            $this->unexpectedToken($token);
        }
        $this->readToken();
        $this->merge(["checker" => $token[1]]);
    }

    private function isCheckerIdentifier(string $identifier): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/', $identifier);
    }

    private function readHelp(): ?string
    {
        $token = $this->readToken(false);
        if ($token === null || !in_array($token[0], ['help', 'nl', 'space'])) {
            return null;
        }
        $helpText = "";
        $nl = "";
        $space = "";
        while ($token !== null && in_array($token[0], ["space", "nl", "help"])) {
            if ($token[0] === 'space') {
                $space = " ";
            } elseif ($token[0] === 'nl') {
                $nl .= $token[1];
            } else {
                $delim = ($nl !== "") ? $nl : $space;
                if ($helpText === '') {
                    $delim = '';
                }
                $nl = "";
                $space = "";
                $helpText .= $delim . rtrim($token[1]);
            }
            $token = $this->readNextToken();
        }
        return $helpText;
    }

    private function loadDescription(): void
    {
        $descriptionDescriptor = ["default" => null, "byOptions" => []];

        $help = $this->readHelp();
        if ($help !== null) {
            $descriptionDescriptor['default'] = $help;
            $token = $this->readToken(false);
        }

        $token = $this->readToken(false);

        while ($token !== null && $token[0] === '[') {
            $token = $this->readNextToken();
            if ($token === null || $token[0] !== '=') {
                $optionList = $this->loadOptionList();
                $token = $this->readToken(false);
            } else {
                $optionList = ['short' => ['@'], 'long' => ['@@']];
            }
            if ($token !== null && $token[0] === '=') {
                $token = $this->readNextToken();
                if ($token === null || $token[0] !== 'identifier') {
                    $this->unexpectedToken($token);
                }
                $optionList['argName'] = $token[1];
                $token = $this->readNextToken();
            } else {
                $optionList['argName'] = null;
            }
            if ($token === null || $token[0] !== ']') {
                $this->unexpectedToken($token);
            }
            $this->readToken();
            $optionList['description'] = $this->readHelp();
            $token = $this->readToken(false);
            $descriptionDescriptor['byOptions'][] = $optionList;
        }

        $this->merge(["help" => $descriptionDescriptor]);
    }

    private function putQuantity(int $min, ?int $max): void
    {
        if ($this->keyExists('min') || $this->keyExists('max')) {
            $this->error("Quantity can be specified only once");
        }
        if ($max !== null && $max < $min) {
            $this->error("Minimum quantity must be greater than maximum quantity");
        }
        $this->merge(["min" => $min, "max" => $max]);
    }

    private function loadArgType()
    {
        $argTypes = [
            "~" => Option::ARG_NONE,
            ":" => Option::ARG_REQUIRED,
            "?" => Option::ARG_OPTIONAL,
            "*" => Option::ARG_ARRAY,
        ];
        $this->setErrorPosition();
        $token = $this->readToken(false);
        if ($token === null || !isset($argTypes[$token[0]])) {
            $this->error("Invalid quantity specification");
        }
        $this->readToken();
        $argType = $argTypes[$token[0]];
        if ($this->keyExists('argType')) {
            $this->error("Option argument type can be specified only once");
        }
        $this->merge(["argType" => $argType]);
    }

    private function loadQuantity(): void
    {
        $this->setErrorPosition();
        $token = $this->readToken(false);
        if ($token === null || $token[0] !== '{') {
            $this->unexpectedToken($token);
        }
        $token = $this->readNextToken();
        $min = $this->tokenToNum($token);
        if ($min === null && $token[0] !== ',') {
            $this->unexpectedToken($token);
        }

        if ($min !== null || ($token[0] !== ',')) {
            $token = $this->readNextToken();
        }
        if ($min === null) {
            $min = 0;
        }

        if ($token === null || $token[0] !== '}') {
            if ($token === null || ($token[0] !== '}' && $token[0] !== ',')) {
                $this->unexpectedToken($token);
            }

            $max = null;

            if ($token[0] === ',') {
                $token = $this->readNextToken();
                $max = $this->tokenToNum($token);
                if ($max === null) {
                    if ($token === null || $token[0] !== '}') {
                        $this->unexpectedToken($token);
                    }
                } else {
                    $token = $this->readNextToken();
                }
                if ($token === null || $token[0] !== '}') {
                    $this->unexpectedToken($token);
                }
            }
        } else {
            $max = $min;
        }

        $this->readToken();
        $this->putQuantity($min, $max);
    }

    private function tokenToNum(?array $token): ?int
    {
        if ($token === null) {
            return null;
        }
        if ($token[0] !== 'identifier') {
            return null;
        }
        if (!preg_match('/^[0-9]+$/', $token[1])) {
            return null;
        }
        return (int)$token[1];
    }

    private function readToken(bool $moveToNext = true): ?array
    {
        $val = $this->tokens->current();
        if ($moveToNext) {
            $this->tokens->next();
        }
        if ($val) {
            $this->lastPosition = $val[2];
            if ($val[0] === 'error') {
                $this->unexpectedToken($val);
            }
        }
        return $val ? $val : null;
    }

    private function readNextToken(): ?array
    {
        $this->readToken();
        return $this->readToken(false);
    }

    private function loadOptionList(bool $allowIdentifiers = false): array
    {
        $this->setErrorPosition();
        $options = [
            'short' => [],
            'long' => [],
            'identifier' => [],
            'special' => [],
        ];
        $token = $this->readToken(false);
        while ($token !== null) {
            $type = $this->determineOptionType($token);
            if ($type !== null) {
                $options[$type][] = $token[1];
                $token = $this->readNextToken();
                if ($token !== null && $token[0] === '|') {
                    $token = $this->readNextToken();
                    $expectingNext = true;
                } else {
                    break;
                }
            } else {
                $this->unexpectedToken($token);
            }
        }
        if (!$allowIdentifiers) {
            if (!empty($options['identifier'])) {
                $this->error(sprintf("Invalid option name: %s", $options['identifier'][0]));
            }
            unset($options['identifier']);
        }

        foreach ($options['special'] as $opt) {
            if ($opt === '@' || $opt === '@@@') {
                $options['short'][] = '@';
            }
            if ($opt === '@@' || $opt === '@@@') {
                $options['long'][] = '@@';
            }
        }
        unset($options['special']);
        foreach (["long", "short"] as $type) {
            $arr = $options[$type];
            $options[$type] = [];
            foreach ($arr as $val) {
                if (!in_array($val, $options[$type])) {
                    $options[$type][] = $val;
                }
            }
        }
        return $options;
    }

    private function loadByTypeMultiple(array $types, bool $eachOnlyOnce): void
    {
        $load = true;
        while ($load) {
            $type = $this->loadByTypeSingle($types);
            if ($type !== null) {
                if ($eachOnlyOnce) {
                    unset($types[$type]);
                }
            } else {
                $load = false;
            }
        }
    }

    private function loadByTypeSingle(array $types): ?string
    {
        $token = $this->readToken(false);
        if ($token === null || !isset($types[$token[0]])) {
            return null;
        }
        $method = $types[$token[0]];
        if (is_string($method) && is_callable([$this, $method])) {
            $this->$method();
        } elseif (is_callable($method)) {
            $method();
        } else {
            throw Exception("Parser bug: invalid callback for loadByTypeSingle()");
        }
        return $token[0];
    }

    private function determineOptionType(array $token): ?string
    {
        if ($token[0] === 'identifier') {
            $option = $this->identifierToOption($token[1]);
            if ($option !== null) {
                if (strlen($option) === 1) {
                    return "short";
                } else {
                    return "long";
                }
            }
            return 'identifier';
        } elseif ($token[0] === '@') {
            return 'special';
        } else {
            return null;
        }
    }

    private function identifierToOption(string $identifier): ?string
    {
        if (preg_match('/^' . ArgsTokenizer::LONGOPT_REGEXP . '$/', $identifier)) {
            return $identifier;
        }
        return null;
    }


    private function setErrorPosition(): void
    {
        $token = $this->readToken(false);
        if ($token === null) {
            $this->errorPosition = $this->lastPosition;
        } else {
            $this->errorPosition = $token[2];
        }
    }

    private function error(string $message, ?array $token = null, bool $nullTokenIsEof = false)
    {
        if ($token !== null) {
            throw new ParserException($message, $token[2]);
        } else {
            throw new ParserException($message, $nullTokenIsEof ? $this->lastPosition : $this->errorPosition);
        }
    }

    private function unexpectedToken(?array $token)
    {
        if ($token !== null) {
            $message = sprintf("Unexpected token: %s", json_encode($token[1]));
        } else {
            $message = "Unexpected end of statement";
        }
        $this->error($message, $token, true);
    }
}
