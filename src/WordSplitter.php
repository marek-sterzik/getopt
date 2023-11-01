<?php

namespace SPSOstrov\GetOpt;

use Exception;

class WordSplitter
{
    /** @var string */
    private $strategy;

    public function __construct(string $strategy)
    {
        $this->strategy = $strategy;
    }

    /**
     * @return array<string[]>
     */
    public function split(string $sentence): array
    {
        if ($this->strategy === "normal") {
            return $this->splitNormal($sentence);
        } else {
            throw new Exception(sprintf("Invalid word splitting strategy: %s", $this->strategy));
        }
    }

    /**
     * @return array<string[]>
     */
    private function splitNormal(string $sentence): array
    {
        $sentence = trim($sentence);
        if ($sentence === '') {
            return [];
        }
        $paragraphs = [];
        /** @phpstan-ignore-next-line */
        foreach (preg_split('/\n\s*\n/', $sentence) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            $finalParagraph = [];
            /** @phpstan-ignore-next-line */
            foreach (preg_split('/\s+/', $paragraph) as $word) {
                $word = trim($word);
                if ($word === '') {
                    continue;
                }
                $finalParagraph[] = $word;
            }
            $paragraphs[] = $finalParagraph;
        }
        return $paragraphs;
    }
}
