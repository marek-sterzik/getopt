<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use SPSOstrov\GetOpt\OptionParser;
use SPSOstrov\GetOpt\Option;
use SPSOstrov\GetOpt\ParserException;


#[CoversClass(OptionParser::class)]
final class OptionParserTest extends TestCase
{

    public static function getInvalidCases(): array
    {
        $data = [
            'f|fx::', 'f|fx:?', 'f|fx{1,2,3}', 'f|fx{~}', 'f|fx{{}', 'f|fx{3',
            'fa.x', 'fa_x', '-x', 'x--y', 'x=a-b', 'x=a..b', 'x=abc=cde',
            'f[==]', 'f[a,,c]', 'f[$=a]'
        ];
        return array_map(function ($x) {
            return [$x];
        }, $data);
    }

    public static function getOptionParserCases(): array
    {
        return [
            ['f-x',  [false, "", "f-x"]],
            ['f|fx',  [false, "f", "fx"]],
            ['f|g',   [false, "f|g", ""]],
            ['fx|gx', [false, "", "fx|gx"]],
            ['$f|fx', [true,  "f", "fx"]],
            ['f|fx~', [false, "f", "fx", Option::ARG_NONE]],
            ['f|fx:', [false, "f", "fx", Option::ARG_REQUIRED]],
            ['f|fx?', [false, "f", "fx", Option::ARG_OPTIONAL]],
            ['f|fx*', [false, "f", "fx", Option::ARG_ARRAY]],
            ['f|fx{1,2}', [false, "f", "fx", null, 1, 2]],
            ['f|fx{1,}', [false, "f", "fx", null, 1, null]],
            ['f|fx{,5}', [false, "f", "fx", null, 0, 5]],
            ['f|fx{1,3}?', [false, "f", "fx", Option::ARG_OPTIONAL, 1, 3]],
            ['f|fx:{1,3}', [false, "f", "fx", Option::ARG_REQUIRED, 1, 3]],
            ['f|fx{,}', [false, "f", "fx", null, 0, null]],
            ['f|fx:{10}', [false, "f", "fx", Option::ARG_REQUIRED, 10, 10]],
            ['f|fx:=abc.def', [false, "f", "fx", Option::ARG_REQUIRED, null, null, 'abc.def']],
            ['f|fx=abc.def:', [false, "f", "fx", Option::ARG_REQUIRED, null, null, 'abc.def']],
            ['f|fx=abc.def:{1}', [false, "f", "fx", Option::ARG_REQUIRED, 1, 1, 'abc.def']],
            ['f|fx=abc.def{1}:', [false, "f", "fx", Option::ARG_REQUIRED, 1, 1, 'abc.def']],
            ['f|fx{1}=abc.def:', [false, "f", "fx", Option::ARG_REQUIRED, 1, 1, 'abc.def']],
            ['@', [false, "@", ""]],
            ['@@', [false, "", "@@"]],
            ['@@@', [false, "@", "@@"]],
            ['a|aa|@', [false, "a|@", "aa"]],
            ['a|aa|@@', [false, "a", "aa|@@"]],
            ['a|aa|@@@', [false, "a|@", "aa|@@"]],
            ['a|@|@', [false, "a|@", ""]],
            ['a help text', [false, "a", "", null, null, null, null, null, 'help text']],
            ['a: help text', [false, "a", "", Option::ARG_REQUIRED, null, null, null, null, 'help text']],
            ['a:  help text', [false, "a", "", Option::ARG_REQUIRED, null, null, null, null, 'help text']],
            ["a\thelp text", [false, "a", "", null, null, null, null, null, 'help text']],
            ['a[x]', [false, "a", "", null, null, null, null, [['$', 'x', '$']]]],
            ['a[x=$]', [false, "a", "", null, null, null, null, [['$', 'x', '$']]]],
            ['a[x=@]', [false, "a", "", null, null, null, null, [['@', 'x', '@']]]],
            ['a[x=@@]', [false, "a", "", null, null, null, null, [['@@', 'x', '@']]]],
            ['a[x=@@@]', [false, "a", "", null, null, null, null, [['@@@', 'x', '@']]]],
            ['a[x|y=$]', [false, "a", "", null, null, null, null, [['$', 'x|y', '$']]]],
            ['a[x|y|@=$]', [false, "a", "", null, null, null, null, [['$', 'x|y|@', '$']]]],
            ['a[x|y|@@=$]', [false, "a", "", null, null, null, null, [['$', 'x|y|@@', '$']]]],
            ['a[x|y|@@@=$]', [false, "a", "", null, null, null, null, [['$', 'x|y|@|@@', '$']]]],
            ['a[x,y]', [false, "a", "", null, null, null, null, [['$', 'x', '$'], ['$', 'y', '$']]]],
            ['a[x="A"]', [false, "a", "", null, null, null, null, [['A', 'x', 'const']]]],
            ['a[x=y]', [false, "a", "", null, null, null, null, [['y', 'x', 'var']]]],
            ['a[x]{4}*', [false, "a", "", Option::ARG_ARRAY, 4, 4, null, [['$', 'x', '$']]]],
            ['a[x]*{4}', [false, "a", "", Option::ARG_ARRAY, 4, 4, null, [['$', 'x', '$']]]],
            ['a*[x]{4}', [false, "a", "", Option::ARG_ARRAY, 4, 4, null, [['$', 'x', '$']]]],
            ['a*{4}[x]', [false, "a", "", Option::ARG_ARRAY, 4, 4, null, [['$', 'x', '$']]]],
            ['a{4}*[x]', [false, "a", "", Option::ARG_ARRAY, 4, 4, null, [['$', 'x', '$']]]],
            ['a{4}[x]*', [false, "a", "", Option::ARG_ARRAY, 4, 4, null, [['$', 'x', '$']]]],
            ['a[]', [false, "a", "", null, null, null, null, []]],
        ];
    }

    #[DataProvider('getOptionParserCases')]
    public function testSingleLineParsing(string $option, array $expectedResult): void
    {
        $optionParser = new OptionParser();

        $data = $optionParser->parse($option);

        $this->assertCount(1, $data);
        $this->assertArrayHasKey(0, $data);

        $data = $data[0];

        while (count($expectedResult) < 9) {
            $expectedResult[] = null;
        }
        list($isArgument, $short, $long, $argType, $min, $max, $checker, $rules, $help) = $expectedResult;
        $short = $this->parseOpts($short);
        $long = $this->parseOpts($long);
        $rules = $this->parseRules($rules);

        $dataHelp = $data['help'] ?? null;
        if ($dataHelp !== null) {
            $this->assertNotNull($dataHelp['default']);
            $dataHelp = $dataHelp['default']; 
        }

        $this->assertSame($isArgument, $data['isArgument'] ?? null);
        $this->assertSame($short, $data['short'] ?? null);
        $this->assertSame($long, $data['long'] ?? null);
        $this->assertSame($argType, $data['argType'] ?? null);
        $this->assertSame($min, $data['min'] ?? null);
        $this->assertSame($max, $data['max'] ?? null);
        $this->assertSame($checker, $data['checker'] ?? null);
        $this->assertSame($rules, $data['rules'] ?? null);
        $this->assertSame($help, $dataHelp);
    }

    #[DataProvider('getInvalidCases')]
    public function testInvalidParsing(string $option): void
    {
        $optionParser = new OptionParser();

        $this->expectException(ParserException::class);
        $optionParser->parse($option);
    }

    private function parseRules(?array $rules): ?array
    {
        if ($rules === null) {
            return null;
        }
        $finalRules = [];
        foreach ($rules as list($from, $to, $type)) {
            $finalRules[] = [
                'from' => $from,
                'to' => $this->parseOpts($to),
                'type' => $type,
            ];
        }
        return $finalRules;
    }

    private function parseOpts(?string $opts): ?array
    {
        if ($opts === null) {
            return null;
        }
        if ($opts === "") {
            return [];
        }
        return explode("|", $opts);
    }
}
