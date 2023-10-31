<?php

namespace SPSOstrov\GetOpt;

use Exception;

class AsciiTable
{
    public const DEFAULT_WIDTH = 120;

    /** @var string */
    private $encoding = "utf8";

    /** @var array */
    private $columns = [];

    /** @var int|null */
    private $width = null;

    /** @var int */
    private $columnWidth = 0;

    /** @var bool */
    private $drawBorder = false;

    public function column($padding = null, string $align = "left", int $min = 1, string $wordSplitter = "normal"): self
    {
        $this->calcLeftSpaces(0, $align);
        $column = [
            "min" => $min,
            "wordSplitter" => $wordSplitter,
            "padding" => $this->parsePadding($padding),
            "align" => $align,
        ];
        $column['pad'] = $column['padding']['left'] + $column['padding']['right'];
        $this->columns[] = $column;
        $this->columnWidth += $column['min'] + $column['pad'];
        $this->checkSizes();
        return $this;
    }

    private function parsePadding($padding): array
    {
        if ($padding === null) {
            return [
                'left' => 0,
                'right' => 0,
            ];
        }
        if (is_int($padding)) {
            return [
                'left' => $padding,
                'right' => $padding,
            ];
        }
        if (is_array($padding)) {
            if ($padding === array_values($padding)) {
                switch (count($padding)) {
                    case 1:
                        return [
                        'left' => $padding[0],
                        'right' => $padding[0],
                    ];
                    case 2:
                        return [
                        'left' => $padding[0],
                        'right' => $padding[1],
                    ];
                }
            } else {
                return [
                    "left" => (int)($padding['left'] ?? 0),
                    "right" => (int)($padding['right'] ?? 0),
                ];
            }
        }
        throw new Exception("Invalid value for padding");
    }

    public function encoding(string $encoding): self
    {
        $this->encoding = $encoding;
        return $this;
    }

    public function width(int $width): self
    {
        $this->width = $width;
        $this->checkSizes();
        return $this;
    }

    public function drawBorder(bool $drawBorder = true): self
    {
        $this->drawBorder = $drawBorder;
        $this->checkSizes();
        return $this;
    }

    private function checkSizes(): void
    {
        if ($this->width !== null && count($this->columns) > 0) {
            $minWidth = 0;
            if ($this->drawBorder) {
                $minWidth += count($this->columns) + 1;
            }
            $minWidth += $this->columnWidth;
            if ($minWidth > $this->width) {
                throw new Exception("Minimal width expected by the columns is bigger than the overall table width.");
            }
        }
    }

    public function render(array $data): string
    {
        if ($this->width === null) {
            throw new Exception("Table width not specified.");
        }

        $data = $this->splitData($data);
        $cellStats = $this->calcCellStats($data);
        $cellSizes = $this->calcCellSizes($cellStats);

        $data = $this->createLines($data, $cellSizes);

        $result = "";
        if ($this->drawBorder) {
            $result .= $this->borderRow($cellSizes);
        }
        foreach ($data as $row) {
            $result .= $this->row($row, $cellSizes);
            if ($this->drawBorder) {
                $result .= $this->borderRow($cellSizes);
            }
        }
        return $result;
    }

    private function row(array $row, array $cellSizes): string
    {
        $output = "";
        $height = 0;
        foreach ($row as $cell) {
            $height = max($height, count($cell));
        }
        for ($i = 0; $i < $height; $i++) {
            if ($this->drawBorder) {
                $output .= "|";
            }
            $spaces = 0;
            foreach ($row as $column => $cell) {
                if ($column > 0) {
                    if ($this->drawBorder) {
                        if ($spaces > 0) {
                            $output .= str_repeat(' ', $spaces);
                            $spaces = 0;
                        }
                        $output .= '|';
                    }
                }
                $spaces += $this->columns[$column]['padding']['left'];
                $line = $cell[$i] ?? '';
                $lineSpaces = $cellSizes[$column] - mb_strlen($line, $this->encoding);
                $leftSpaces = $this->calcLeftSpaces($lineSpaces, $this->columns[$column]['align']);
                $spaces += $leftSpaces;
                if ($line !== '') {
                    if ($spaces > 0) {
                        $output .= str_repeat(' ', $spaces);
                        $spaces = 0;
                    }
                }
                $output .= $line;
                $spaces += $lineSpaces - $leftSpaces;
                $spaces += $this->columns[$column]['padding']['right'];
            }
            if ($this->drawBorder) {
                if ($spaces > 0) {
                    $output .= str_repeat(' ', $spaces);
                    $spaces = 0;
                }
                $output .= "|";
            }
            $output .= "\n";
        }
        return $output;
    }

    private function calcLeftSpaces(int $spaces, string $align)
    {
        switch ($align) {
            case 'left':
                return 0;
            case 'right':
                return $spaces;
            case 'center':
                return intdiv($spaces, 2);
            default:
                throw new Exception(sprintf("Invalid align: %s", $align));
        }
    }

    private function borderRow(array $cellSizes)
    {
        $row = "+";
        foreach ($cellSizes as $index => $size) {
            $row .= str_repeat('-', $this->columns[$index]['pad'] + $size) . "+";
        }
        return $row . "\n";
    }

    private function createLines(array $data, array $cellSizes): array
    {
        $finalRows = [];
        foreach ($data as $row) {
            $finalRow = [];
            foreach ($row as $i => $cell) {
                $finalRow[$i] = $this->createLinesForCell($cell ?? [], $cellSizes[$i]);
            }
            $finalRows[] = $finalRow;
        }
        return $finalRows;
    }

    private function createLinesForCell(array $paragraphs, int $cellSize): array
    {
        $lines = [];
        $acc = "";
        $accLen = 0;
        foreach ($paragraphs as $paragraph) {
            foreach ($paragraph as $word) {
                $wordLen = mb_strlen($word, $this->encoding);
                if ($accLen > 0 && $wordLen + 1 > $cellSize - $accLen) {
                    $lines[] = $acc;
                    $acc = "";
                    $accLen = 0;
                }
                while ($cellSize < $wordLen) {
                    $lines[] = mb_substr($word, 0, $cellSize, $this->encoding);
                    $word = mb_substr($word, $cellSize, null, $this->encoding);
                    $wordLen = mb_strlen($word, $this->encoding);
                }
                if ($accLen > 0) {
                    $acc .= " ";
                    $accLen++;
                }
                $acc .= $word;
                $accLen += $wordLen;
            }
            if ($accLen > 0) {
                $lines[] = $acc;
            }
        }
        return $lines;
    }

    private function splitData(array $data): array
    {
        $finalRows = [];
        foreach ($data as $row) {
            $finalRow = [];
            foreach ($this->columns as $i => $column) {
                $cellContent = $row[$i] ?? null;
                if ($cellContent !== null) {
                    $cellContent = (new WordSplitter($column['wordSplitter']))->split($cellContent);
                }
                $finalRow[$i] = $cellContent;
            }
            $finalRows[] = $finalRow;
        }
        return $finalRows;
    }

    private function calcCellStats(array $data): array
    {
        $stats = [];
        foreach (array_keys($this->columns) as $i) {
            $stats[$i] = [
                "maxWord" => 0,
                "maxParagraph" => 0,
            ];
        }
        foreach ($data as $row) {
            foreach ($row as $i => $cell) {
                if ($cell !== null) {
                    foreach ($cell as $paragraph) {
                        $paragraphLen = 0;
                        foreach ($paragraph as $wi => $word) {
                            $wordLen = mb_strlen($word, $this->encoding);
                            $stats[$i]['maxWord'] = max($stats[$i]['maxWord'], $wordLen);
                            $paragraphLen += (($wi !== 0) ? 1 : 0) + $wordLen;
                        }
                        $stats[$i]['maxParagraph'] = max($stats[$i]['maxParagraph'], $paragraphLen);
                    }
                }
            }
        }
        return $stats;
    }

    private function calcCellSizes(array $cellStats)
    {
        $width = $this->width;
        if ($this->drawBorder) {
            $width -= count($this->columns) + 2;
        }
        $data = [
            "cells" => [],
            "passiveCells" => [],
            "totalSize" => 0,
            "width" => $width,
            'reserve' => 0,
        ];
        foreach ($cellStats as $i => $stats) {
            $cell = [
                'index' => $i,
                'min' => $this->columns[$i]['min'] + $this->columns[$i]['pad'],
                'pad' => $this->columns[$i]['pad'],
                'maxWord' => $stats['maxWord'],
                'maxParagraph' => $stats['maxParagraph'] + $this->columns[$i]['pad'],
            ];
            $cell['size'] = max($cell['maxParagraph'], $cell['min']);
            $cell['reserve'] = $cell['size'] - max($cell['min'], $cell['maxWord']);
            if ($cell['reserve'] > 0) {
                $data['cells'][] = $cell;
            } else {
                $data['passiveCells'][] = $cell;
            }
            $data['totalSize'] += $cell['maxParagraph'];
            $data['reserve'] += $cell['reserve'];
        }
        $this->shrinkByReserve($data);
        if ($data['totalSize'] > $data['width']) {
            $data['cells'] = array_merge($data['cells'], $data['passiveCells']);
            $data['passiveCells'] = [];
            $data['reserve'] = 0;
            foreach ($data['cells'] as &$c) {
                $reserve = $c['size'] - $c['min'];
                $c['reserve'] = $reserve;
                $data['reserve'] += $reserve;
            }
            $this->shrinkByReserve($data);
        }

        $cellSizes = [];
        foreach (array_merge($data['cells'], $data['passiveCells']) as $cell) {
            $cellSizes[$cell['index']] = $cell['size'] - $cell['pad'];
        }

        ksort($cellSizes);

        return $cellSizes;
    }

    private function shrinkByReserve(array &$data): void
    {
        while ($data['totalSize'] > $data['width'] && $data['reserve'] > 0) {
            $this->sortBySize($data);
            $currentSize = $data['cells'][0]['size'];
            $nextSize = 0;
            $n = count($data['cells']);
            foreach ($data['cells'] as $i => $cell) {
                if ($cell['size'] < $currentSize) {
                    $nextSize = $cell['size'];
                    $n = $i;
                    break;
                }
            }
            $remove = min($data['totalSize'] - $data['width'], ($currentSize - $nextSize) * $n);
            $removeSingle = intdiv($remove, $n);
            $removeMore = $remove % $n;
            for ($i = 0; $i < $n; $i++) {
                $tryRemove = $removeSingle + ($i < $removeMore) ? 1 : 0;
                $tryRemove = min($tryRemove, $data['cells'][$i]['reserve']);
                $data['cells'][$i]['size'] -= $tryRemove;
                $data['cells'][$i]['reserve'] -= $tryRemove;
                $data['totalSize'] -= $tryRemove;
                $data['reserve'] -= $tryRemove;
            }
            $j = 0;
            $nCells = count($data['cells']);
            for ($i = 0; $i < $nCells; $i++) {
                if ($data['cells'][$i]['reserve'] === 0) {
                    $data['passiveCells'][] = $data['cells'][$i];
                    unset($data['cells'][$i]);
                } elseif ($i !== $j) {
                    $data['cells'][$j] = $data['cells'][$i];
                    unset($data['cells'][$i]);
                    $j++;
                } elseif ($i >= $n) {
                    break;
                } else {
                    $j++;
                }
            }
        }
    }

    private function sortBySize(array &$data): void
    {
        usort($data['cells'], function ($a, $b) {
            $result = ($b['size'] <=> $a['size']);
            if ($result == 0) {
                $result = ($b['index'] <=> $a['index']);
            }
            return $result;
        });
    }
}
