<?php

namespace SPSOstrov\GetOpt;

class WordSplitter
{
    public function __construct(string $strategy)
    {
        $this->strategy = $strategy;
    }

    public function split(string $sentence): array
    {
        return $this->splitNormal($sentence);
    }

    private function splitNormal(string $sentence): array
    {
        $sentence = trim($sentence);
        if ($sentence === '') {
            return [];
        }
        $paragraphs = [];
        foreach (preg_split('/\n\s*\n/', $sentence) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            $finalParagraph = [];
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
