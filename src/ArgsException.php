<?php

namespace SPSOstrov\GetOpt;

use Exception;

class ArgsException extends Exception
{
    /** @var string */
    private $argsErrorMessage;
    
    /** @var int|null */
    private $argsErrorPosition;

    public function __construct(string $argsErrorMessage, ?int $argsErrorPosition = null)
    {
        $message = sprintf("Argument error: %s ", $argsErrorMessage);
        parent::__construct($message);
        $this->argsErrorMessage = $argsErrorMessage;
        $this->argsErrorPosition = $argsErrorPosition;
    }

    public function getArgsErrorMessage(): string
    {
        return $this->argsErrorMessage;
    }

    public function getArgsErrorPosition(): int
    {
        return $this->argsErrorPosition;
    }
}

