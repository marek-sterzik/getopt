<?php

namespace SPSOstrov\GetOpt;

use Exception;

class Options
{
    public const ARG_NONE = Option::ARG_NONE;
    public const ARG_REQUIRED = Option::ARG_REQUIRED;
    public const ARG_OPTIONAL = Option::ARG_OPTIONAL;
    public const ARG_ARRAY = Option::ARG_ARRAY;

    private const DEFAULT_ARG_DEF = '$args*[__args__]';

    /** @var array<string,Option> */
    private $map = [];

    /** @var Option[] */
    private $argMap = [];

    /** @var Option[] */
    private $options = [];

    /** @var Option|null */
    private $defaultArgOption = null;

    /** @var bool */
    private $strictMode = true;

    /** @var bool */
    private $gnuMode = false;

    /** @var bool */
    private $standaloneOptionalArgAllowed = false;

    /** @var array<string,int>|null */
    private $argCache = null;

    /** @var string|null */
    private $argv0 = null;

    /**
     * @param string|Option|null|array<string|Option|null> $options
     */
    public function __construct($options, bool $strictMode = true, bool $standaloneOptionalArgAllowed = false)
    {
        $this->strictMode = $strictMode;
        $this->gnuMode = false;
        $this->standaloneOptionalArgAllowed = $standaloneOptionalArgAllowed;
        $this->registerOptions($options);
    }

    /**
     * @param string|Option|null|array<string|Option|null> $options
     */
    public function registerOptions($options, bool $strict = true): self
    {
        if (is_array($options)) {
            foreach ($options as $option) {
                $this->registerOptions($option, $strict);
            }
        } elseif ($options === null) {
            // do nothing
        } elseif ($options instanceof Option) {
            $this->registerOptionReal(clone $options, $strict);
        } elseif (is_string($options)) {
            foreach ((new OptionParser())->parse($options) as $option) {
                if (!empty($option)) {
                    $this->registerOptionReal(new Option($option), $strict);
                }
            }
        } else {
            throw new ParserException("Invalid options format");
        }

        return $this;
    }

    private function registerOptionReal(Option $option, bool $strict = true): self
    {
        if ($option->isArgument()) {
            $this->argMap[] = $option;
        } else {
            foreach ($option->getAll() as $name) {
                if (isset($this->map[$name])) {
                    if ($strict) {
                        if ($name === '@@') {
                            $name = "<default-long>";
                        } elseif ($name === '@') {
                            $name = "<default-short>";
                        } else {
                            if (strlen($name) == 1) {
                                $name = "-" . $name;
                            } else {
                                $name = "--" . $name;
                            }
                        }
                        $message = sprintf("Option %s must be defined only once in Options", $name);
                        throw new ParserException($message);
                    } else {
                        $option->removeOption($name);
                    }
                }
                $this->map[$name] = $option;
            }
        }
        $this->options[] = $option;

        return $this;
    }

    public function setArgv0(?string $argv0): self
    {
        $this->argv0 = $argv0;
        return $this;
    }

    public function getArgv0(): string
    {
        return $this->argv0 ?? (isset($_SERVER['argv'][0]) ? $this->getRealArgv0($_SERVER['argv'][0]) : 'command');
    }

    public function getHelpFormatted(?FormatterInterface $formatter = null): ?string
    {
        $argv0 = $this->getArgv0();
        $args = $this->getArgsHelpFormatted($formatter);
        $options = $this->getOptionsHelpFormatted($formatter);
        return Formatter::instance($formatter)->formatHelp($argv0, $args, $options);
    }

    private function getRealArgv0(string $argv0): string
    {
        if (strpos($argv0, '/') === false) {
            return $argv0;
        }
        $dir = dirname($argv0);
        $path = getenv("PATH");
        if (!is_string($path)) {
            return $argv0;
        }
        foreach (explode(":", $path) as $pathDir) {
            if ($pathDir === $dir) {
                return basename($argv0);
            }
        }
        return $argv0;
    }

    public function getOptionsHelpFormatted(?FormatterInterface $formatter = null): ?string
    {
        return Formatter::instance($formatter)->formatOptionsHelp($this->getOptionsHelp());
    }

    public function getArgsHelpFormatted(?FormatterInterface $formatter = null): ?string
    {
        return Formatter::instance($formatter)->formatArgsHelp($this->getArgsHelp());
    }

    /**
     * @return array<mixed>
     */
    public function getOptionsHelp(): array
    {
        $help = [];
        foreach ($this->options as $option) {
            if (!$option->isArgument()) {
                $help = array_merge($help, $option->getHelp());
            }
        }
        return $help;
    }

    /**
     * @return array<mixed>
     */
    public function getArgsHelp(): array
    {
        $help = [];
        foreach ($this->options as $option) {
            if ($option->isArgument()) {
                $help = array_merge($help, $option->getHelp());
            }
        }
        return $help;
    }

    public function getStrictMode(): bool
    {
        return $this->strictMode;
    }

    public function setStrictMode(bool $strictMode = true): self
    {
        $this->strictMode = $strictMode;
        return $this;
    }

    public function getGnuMode(): bool
    {
        return $this->gnuMode;
    }

    public function setGnuMode(bool $gnuMode = true): self
    {
        $this->gnuMode = $gnuMode;
        return $this;
    }

    public function isStandaloneOptionalArgAllowed(): bool
    {
        return $this->standaloneOptionalArgAllowed;
    }

    public function setStandaloneOptionalArgAllowed(bool $standaloneOptionalArgAllowed): self
    {
        $this->standaloneOptionalArgAllowed = $standaloneOptionalArgAllowed;
        return $this;
    }

    public function getArgTypeFor(string $option): ?string
    {
        $option = $this->getOptionFor($option);
        if ($option === null) {
            return null;
        }
        return $option->getArgType();
    }

    public function getOptionForArg(int $argNumber): ?Option
    {
        if ($argNumber < 0) {
            return null;
        }
        if (empty($this->argMap)) {
            if ($this->defaultArgOption === null) {
                $parsed = (new OptionParser())->parse(self::DEFAULT_ARG_DEF);
                if (count($parsed) !== 1) {
                    throw new Exception("Bug: invalid constant DEFAULT_ARG_DEF");
                }
                $this->defaultArgOption = new Option($parsed[0]);
            }
            $max = $this->defaultArgOption->getMax();
            if ($max !== null && $max < $argNumber) {
                return null;
            }
            return $this->defaultArgOption;
        }
        $initCache = [
            "n" => 0,
            "oi" => 0,
        ];
        if ($this->argCache === null) {
            $this->argCache = $initCache;
        }
        while ($this->argCache["n"] > $argNumber && $this->argCache['oi'] > 0) {
            $this->argCache['oi']--;
            $option = $this->argMap[$this->argCache['oi']] ?? null;
            if ($option === null) {
                $this->argCache = $initCache;
                break;
            }
            $max = $option->getMax();
            if ($max === null || $max > $this->argCache['n']) {
                $this->argCache = $initCache;
                break;
            }
            $this->argCache['n'] -= $max;
        }
        if (($this->argCache['n'] == 0) !== ($this->argCache['oi'] == 0)) {
            $this->argCache = $initCache;
        }
        while ($this->argCache['oi'] < count($this->argMap)) {
            $option = $this->argMap[$this->argCache['oi']] ?? null;
            if ($option === null) {
                return null;
            }
            $max = $option->getMax();
            if ($max === null || $max > $argNumber - $this->argCache['n']) {
                return $option;
            }
            $this->argCache['oi']++;
            $this->argCache['n'] += $max;
        }
        return null;
    }

    public function getOptionFor(string $option): ?Option
    {
        if ($option === '') {
            return null;
        }
        if (isset($this->map[$option])) {
            return $this->map[$option];
        }
        $option = (strlen($option) == 1) ? '@' : '@@';
        return $this->map[$option] ?? null;
    }

    /**
     * @param array<string>|null $args
     * @return array<mixed,mixed>
     */
    public function parseArgs(?array $args = null): array
    {
        if ($args === null) {
            $args = $_SERVER['argv'] ?? ["x"];
            array_shift($args);
        }
        $parser = new ArgsParser($this);
        return $parser->parse($args);
    }

    /**
     * @return array<Option>
     */
    public function getAll(): array
    {
        $options = $this->options;
        if (empty($this->argMap)) {
            $option = $this->getOptionForArg(0);
            if ($option !== null) {
                $options[] = $option;
            }
        }
        return $options;
    }
}
