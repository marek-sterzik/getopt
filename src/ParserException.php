<?php

namespace SPSOstrov\GetOpt;

use Exception;

class ParserException extends Exception
{
    /** @var string */
    private $parserErrorMessage;

    /** @var string|null */
    private $parserErrorPosition;

    public function __construct(string $parserErrorMessage, ?string $parserErrorPosition = null)
    {
        if ($parserErrorPosition !== null) {
            $message = sprintf("Parser error at char [%s]: %s", $parserErrorPosition, $parserErrorMessage);
        } else {
            $message = sprintf("Parser error: %s", $parserErrorMessage);
        }
        parent::__construct($message);
        $this->parserErrorMessage = $parserErrorMessage;
        $this->parserErrorPosition = $parserErrorPosition;
    }

    public function getParserErrorMessage(): string
    {
        return $this->parserErrorMessage;
    }

    public function getParserErrorPosition(): string
    {
        return $this->parserErrorPosition;
    }
}
