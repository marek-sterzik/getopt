<?php

namespace SPSOstrov\GetOpt;

class ArgsLimitsException extends ArgsException
{
    /** @var array<mixed> */
    private $parsedArgs;

    /**
     * @param array<mixed> $parsedArgs
     */
    public function __construct(
        string $argsErrorMessage,
        array $parsedArgs
    ) {
        parent::__construct($argsErrorMessage, null);
        $this->parsedArgs = $parsedArgs;
    }

    /**
     * @return array<mixed>
     */
    public function getParsedArgs(): array
    {
        return $this->parsedArgs;
    }
}
