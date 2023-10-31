<?php

namespace SPSOstrov\GetOpt;

final class Option
{
    public const ARG_NONE = "none";
    public const ARG_REQUIRED = "required";
    public const ARG_OPTIONAL = "optional";
    public const ARG_ARRAY = "array";

    /** @var bool */
    private $isArgument;

    /** @var string[] */
    private $short;

    /** @var string[] */
    private $long;

    /** @var string */
    private $argType;

    /** @var int|null */
    private $min;

    /** @var int|null */
    private $max;

    /** @var string|null */
    private $checker;

    /** @var array */
    private $rules;

    /** @var array */
    private $help;

    public function __construct(array $parsedOption)
    {
        $this->isArgument = $parsedOption['isArgument'];
        $this->short = $parsedOption['short'];
        $this->long = $parsedOption['long'];
        $this->argType = $parsedOption['argType'] ?? null;
        $this->min = $parsedOption['min'] ?? null;
        $this->max = $parsedOption['max'] ?? null;
        $this->checker = $parsedOption['checker'] ?? null;
        $this->rules = $parsedOption['rules'] ?? [['from' => '$', 'to' => ['@@@'], 'type' => '$']];

        if ($this->isArgument) {
            if (count($this->short) + count($this->long) > 1) {
                throw new ParserException("Argument is allowed to have only one single name");
            }
            if (in_array('@', $this->short) || in_array('@@', $this->long)) {
                throw new ParserException("Wildcard names are not allowed for arguments");
            }
        }

        if ($this->argType === null) {
            if ($this->min === null && $this->max === null) {
                if ($this->isArgument()) {
                    $this->argType = self::ARG_REQUIRED;
                } else {
                    $this->argType = self::ARG_NONE;
                }
            } elseif ($this->max === null || $this->max > 1) {
                $this->argType = self::ARG_ARRAY;
            } elseif ($this->isArgument()) {
                $this->argType = self::ARG_REQUIRED;
            } else {
                $this->argType = self::ARG_NONE;
            }
        }

        if ($this->min === null) {
            if ($this->isArgument()) {
                if ($this->argType === self::ARG_ARRAY) {
                    $this->min = 0;
                    $this->max = null;
                } elseif ($this->argType === self::ARG_OPTIONAL) {
                    $this->min = 0;
                    $this->max = 1;
                } elseif ($this->argType === self::ARG_REQUIRED) {
                    $this->min = 1;
                    $this->max = 1;
                } else {
                    $this->min = 0;
                    $this->max = 0;
                }
            } else {
                $this->min = 0;
                if ($this->argType === self::ARG_ARRAY) {
                    $this->max = null;
                } else {
                    $this->max = 1;
                }
            }
        }

        $this->help = $this->decodeHelp($parsedOption['help'] ?? ['default' => null, 'byOptions' => []]);
    }

    public function hasArgument(): bool
    {
        return in_array($this->argType, [self::ARG_ARRAY, self::ARG_REQUIRED, self::ARG_OPTIONAL]);
    }

    public function isArgument(): bool
    {
        return $this->isArgument;
    }

    /**
     * @return string[]
     */
    public function getShort(bool $includeWildcard = true): array
    {
        return $this->processWildcard($this->short, $includeWildcard);
    }

    /**
     * @return string[]
     */
    public function getLong(bool $includeWildcard = true): array
    {
        return $this->processWildcard($this->long, $includeWildcard);
    }

    /**
     * @return string[]
     */
    private function processWildcard(array $data, bool $includeWildcard): array
    {
        if ($includeWildcard) {
            return $data;
        }
        return array_filter($data, function ($item) {
            if ($item === '@' || $item === '@@') {
                return false;
            }
            return true;
        });
    }

    /**
     * @return string[]
     */
    public function getAll(bool $includeWildcard = true): array
    {
        return array_merge($this->getShort($includeWildcard), $this->getLong($includeWildcard));
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public function getMax(): ?int
    {
        return $this->max;
    }

    public function getArgType(): string
    {
        return $this->argType;
    }

    public function removeOption(string $option): void
    {
        $remover = function ($item) use ($option) {
            return $item !== $option;
        };
        $this->short = array_filter($this->short, $remover);
        $this->long = array_filter($this->long, $remover);
    }

    public function getChecker(): ?string
    {
        return $this->checker;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function useArrayWrite(): bool
    {
        return in_array($this->argType, [self::ARG_ARRAY]);
    }

    public function getRepresentative(bool $decorate = false): ?string
    {
        if (!empty($this->long)) {
            $representative = $this->long[0];
        } elseif (!empty($this->short)) {
            $representative = $this->short[0];
        } else {
            return null;
        }
        if ($decorate) {
            $representative = $this->decorateOption($representative);
        }
        return $representative;
    }

    public function getRepresentatives(bool $decorate = false): ?string
    {
        if (!empty($this->long)) {
            $representatives = $this->long;
        } elseif (!empty($this->short)) {
            $representatives = $this->short;
        } else {
            return null;
        }
        if ($decorate) {
            $representatives = array_map(function ($name) {
                return $this->decorateOption($name);
            }, $representatives);
        }
        return implode(", ", $representatives);
    }

    public function getHelp(): array
    {
        return $this->help;
    }

    private function decorateOption(string $name): string
    {
        if ($this->isArgument()) {
            return '<' . $name . '>';
        } else {
            if (strlen($name) <= 1) {
                return '-' . $name;
            } else {
                return '--' . $name;
            }
        }
    }

    private function decodeHelp(array $helpDescriptor): array
    {
        $help = [[
            "short" => [],
            "long" => [],
            "argName" => null,
            "description" => $helpDescriptor['default']
        ]];
        $short = array_fill_keys($this->getShort(false), true);
        $long = array_fill_keys($this->getLong(false), true);
        $shortWildcardIndex = 0;
        $longWildcardIndex = 0;
        foreach ($helpDescriptor['byOptions'] as $descriptor) {
            $isShortWildcard = in_array('@', $descriptor['short']);
            $isLongWildcard = in_array('@@', $descriptor['long']);
            $helpRecord = [
                'short' => $this->pickOpts($short, $descriptor['short']),
                'long' => $this->pickOpts($long, $descriptor['long']),
                'argName' => $descriptor['argName'],
                'description' => $descriptor['description'],
            ];
            if ($isShortWildcard && $shortWildcardIndex === 0) {
                $shortWildcardIndex = count($help);
            }
            if ($isLongWildcard && $longWildcardIndex === 0) {
                $longWildcardIndex = count($help);
            }
            $help[] = $helpRecord;
        }
        $help[$shortWildcardIndex]["short"] = array_merge($help[$shortWildcardIndex]['short'], array_keys($short));
        $help[$longWildcardIndex]["long"] = array_merge($help[$longWildcardIndex]['long'], array_keys($long));
        $help = array_filter($help, function ($helpRecord) {
            return count($helpRecord['short']) + count($helpRecord['long']) > 0;
        });
        $help = array_map(function ($data) {
            if ($this->isArgument()) {
                $data['argName'] = $data['argName'] ?? $data['long'][0] ?? $data['short'][0] ?? null;
                unset($data['long']);
                unset($data['short']);
            }
            $data['argType'] = $this->argType;
            return $data;
        }, $help);
        return $help;
    }

    private function pickOpts(array &$opts, array $template): array
    {
        $template = array_fill_keys($template, true);
        $pickedOpts = [];
        foreach (array_keys($opts) as $opt) {
            if (isset($template[$opt])) {
                $pickedOpts[] = $opt;
                unset($opts[$opt]);
            }
        }
        return $pickedOpts;
    }
}
