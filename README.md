# SPSOstrov GetOpt

This is a PHP getopt library with these design goals:

* Clean option parsing with compatibility very close to standard posix getopt library.
* Easy option definitions. Each option type may be defined with a single string. The whole configuration is just an array of strings.
* Support for option checks, array processing and other cool staff.

## Usage

Example:

```php
use SPSOstrov\AppConsole\GetOpt\Options;

$options = new Options([
    'r|regular-option               This is a regular option',
    'a|option-with-param:           This is an option with an parameter',
    'o|option-with-optional-param?  This is an option with an optional parameter'
]);

$args = ["-r", "-a", "argument", "-o"];

$parsed = $options->parseArgs($args);
```

## Option definition

Each option string consist of two basic parts: The option definition and the human readable description. The option definition cannot contain spaces (except if quoted), the human readable description
starts with a first space.

The option definition contains these parts:

* list of options
* optional type specification
* optional quantity specification
* optional write rules
* optional parameter checker

### List of option

Each option needs to be specified by a number of options separated by `|`. For example:

```
o|option|simple-option
```

There are also special option names:

* `@` - represents all short options not explicitely defined
* `@@` - represents all long options not explicitely defined
* `@@@` - represents all short and long options not explicitely defined
* if the list starts with `$` the option will be bound to unnamed positional arguments instead of named options

Handling unnamed positional arguments:

```
$command  command
$args*    command's positional arguments
```

This will store the first unnamed positional argument to varable `command` and all other variables in variable `args`.

### Type specification

There are several types of options and arguments. They are specified by a char followed by the option list. The type chars may be `~`, `?`, `:`, `*`.

```
option-without-param    Option without parameter (may still hava a parameter, if quantity is specified)
option-without-param~   Option without parameter (explicitly set)
option-required-param:  Option with required parameter
option-optional-param?  Option with optional parameter
option-array-param*     Option with multiple parameters
$argument               Regular single argument
$optional-argument?     Zero or one single argument
$array-argument*        An array of arguments
$empty-argument~        Literally make no sense, but is available. Empty argument may be used if you want to explicitely disable positional arguments.
```

### Quantity specification

The quantity specifies how many occurences of a single option may occur. It is set automatically depending on a type,
but you may explicitely set it:

```
option{3}    Exactly 3 options
option{1,5}  From 1 to 5 options
option{4,}   From 4 to infinity options
option{,5}   From 0 to 5 options
option{,}    From 0 to infinity options
```

Note that quantity and option type are completely disconnected options. On the other hand if one of the information is not specified,
it is calculated from the other. If both information are missing, first the option type is set to _no parameter_ and then the quantity `{0,1}` is assigned.

Default quantities:

```
option~   Default quantity is {0,1}
option:   Default quantity is {0,1}
option?   Default quantity is {0,1}
option*   Default quantity is {0,}
$arg~     Used for disabling positional arguments, makes no sense otherwise, default quantity is {0,0}
$arg:     Default quantity is {1,1}
$arg?     Default quantity is {0,1} (otherwise arg acts exactly the same as $arg:)
$arg*     Default quantity is {0,}
```

Default type is calculated from the quantity according to these rules:

* If the quantity is not specified, option defaults to `~`.
* If the quantity is not specified, argument defaults to `:`.
* If maximum quantity is greater than 1 (or infinity), option and arguments defaults to `*`.
* All other cases defaults to `~` for options and `:` for arguments.


### Write rules

Options and arguments are received from the command line one by one. Each argument is processed according to some write rules. Write rules specifies, what should happen when a single option
is processed. There is a _variable registry_ (i.e. associative array) available for each write. Custom operations may be defined. For example:

```
option:[opt]                 Write the value into the variable `opt` instead of variable `option`.
option:[option,op,x]         Three custom rules. First write the value to variable `option` then write to `op` and last write to `x`
option[option="abc"]         Write the string `abc` into variable `option` when --option is specified.
option[option=op]            Write the value of the variable `op` into variable `option` when --option is specified.
option:[opt=$]               Write the value of the option parameter into the variable `opt`. Same as `option[opt]`
o|option:[@=$]               Write the value of the option parameter into all variables named as short options (i.e. write it into variable `o`).
o|option:[@@=$]              Write the value of the option parameter into all variables named as long options (i.e. write it into variable `option`).
o|option:[@@@=$]             Write the value of the option parameter into all variables (i.e. write it into `o` and `option). This is the default rule if no other is specified.
option[opt=@]                Write the name of the really used option into the variable `opt`.
option[opt=@@]               Same as above.
option[opt=@@@]              Write the list of all options (separated by `|`) into the variable `opt`.
yes|no[enabled=@]            Easy boolean implementation with possibility to keep default state.
yes|no{1}[enabled=@]         Easy boolean implementation with possibility where explicitely setting one option is required.
low|medium|high{1}[speed=@]  Easy tristate switch.
```


### Argument checker

You may also specify an argument checker for each option. It is not implemented in the current version, but the syntax already supports that feature:

```
option:=int   # Check the option as `int`.
```

Each checker needs to be defined as string. But the current implementation does not specify how checkers are assigned to a particular string.


### Disabling positional unnamed arguments

By default positional unnamed arguments are allowed even in case, there is no option definition for them. If no rule for positional unnamed arguments is given, the default rule is used:

```
$args*[__args__]
```

meaning that an arbitrary number of unnamed positional arguments is accepted and stored in the variable `__args__`. If you want to disable this default behavior, you may just add
a rule with an _empty argument_. For example:

```
$args~
```

which will cause that the defult rule to disappear and no other unnamed positional arguments will be used. The _empty argument_ rule may be on one hand combined with other rules for unnamed
positional arguments, but make no sense on the other. There is no reason why to combine an _empty argument_ with other types of arguments since the _empty argument_ will actually do nothing.
The only usage for the _empty argument_ is therefore to disable the default behavior of accepting unnamed positional arguments even if not defined.

### Providing help

After any white space a descriptor of help information occurs. Usually it is sufficient to just pass a help description here. For example:
```
c|cool-option: This is a very cool option
```
But in some cases a bit more tunable aproach would be required. You may split the options from the group into smaller groups. For example:
```
o|c|option|cool-option [o|option] This option is not cool.[c|cool-option] This option is very cool.
```
This means that the group of four options `o`, `c`, `option`, `cool-option` (they may be grouped into one single group for example because of quantity calculation)
will be splitted into two "subgroups":

1. the group containing just `o` and `option` having the description `This option is not cool.`
2. the group containing just `c` and `cool-option` having the description `This option is very cool.`

The splitting into subgroups is used only for generating help. Otherwise all the options act as a single group.

Wildcard options may be used:
```
s|S|long-option|long [@] Short options. [@@] Long options.
```
which will create also two "help" groups:

1. group of `s` and `S` having the description `Short options.`
2. group of `long-option` and `long` having the description `Long Options.`

You may also specify a name of the parameter of the option if the option has an parameter. For example:

```
parametric-option: [=param] Parametric option.
```
which will cause to render this option help as:
```
    --parametric-option=<param>  Parametric option.
```

Specifying the parameter name and also grouping may be combined:

```
by-id|by-name:{1} [by-id=id] Select entity by id. [by-name=name] Select entity by name.
```
which will render:
```
    --by-id=<id>      Select entity by id.
    --by-name=<name>  Select entity by name.
```

### Multiple options per one string

It is also possible to specify multiple options per one string. In such a case newlines are used to separate options.
Help text may be also split into multiple lines provided by the help starts with a space on each line:

```
$options = <<<'EOPT'
h|help  Print help
l|long-option
    This option has a long help
    so the help text may be splitted into
    multiple lines.

EOPT;
```

## Tuning the Options object.

Onece an instance of the `SPSOstrov\GetOpt\Options` class is created, you may fine-tune some settings:

```php
use SPSOstrov\GetOpt\Options;

$options = new Options($optionDefinitionList);

// additionally register other option. If registered in non-strict mode,
// it is allowed to share option names with existing options. In such a case
// such a name will be just ignored.
$options->registerOption($optionDefinition, $strict);

// set the argv[0] argument (i.e. command name). This is used when generating help.
// if no argv[0] argument is set, the really used command name is used or it is just
// set to `"command"` not invoked from CLI.
$options->setArgv0("some-command");

// Enable or disable the strict mode of option parsing. The difference in both modes
// (strict and non-strict) is what happens if some invalid option name is recognized.
// In strict mode an exception is thrown in non-strict mode invalid option is recognized
// as the first positional unnamed argument.
// Default mode is strict.
$options->setStrictMode(false);

// Tune how the option with an optional parameter will be parsed. Lets assume we have
// such option with optional argument -o. And the command arguments are "-o optional".
// If standalone optional arguments are allowed, this will be understood as option
// "-o" having the argument "optional".
// If standalone optional arguments are not allowed, this will be understood as option
// "-o" without any parameter succeeded by a positional unnamed argument with value
// "optional".
$option->setStandaloneOptionalArgAllowed(true);
```

## Generating help

### Direct formatted help
```php
use SPSOstrov\GetOpt\Options;

$options = new Options($optionDefinitionList);

// Easiest way:
echo $options->getHelpFormatted();

// Print just help for options:
echo $options->getOptionsHelpFormatted();

// Print just help for positional unnamed arguments:
echo $options->getArgsHelpFormatted();

```

### Getting structured data useful for help

If you want to build help by your own, there are also available parsed structured data for that:

```php
use SPSOstrov\GetOpt\Options;

$options = new Options($optionDefinitionList);

// Get list of options. The result is an array of associative arrays, each containing these keys:
// * short       - array of short options
// * long        - array of long options
// * argName     - name of the argument (parameter) of the option if the option has one. May be null
//                 even if the option has an argument (parameter). In such a case a default like "arg"
//                 should be picked
// * argType     - type of the argument (see constants ARG_* in Options class)
// * description - the description of the options
$optionsHelp = $options->getOptionsHelp();

// Get list of positional unnamed arguments. The result is an array of associative arrays, each
// containing these keys:
// * argName     - name of the argument
// * argType     - type of the argument (see constants ARG_* in Options class)
// * description - the description of the argument
$argsHelp = $options->getArgsHelp();

```

### Formatting help using a custom formatter

If you want to use your own help format, you can either build your own help renderer based on the methods
`getOptionsHelp()`/`getArgsHelp()` or you may create a custom renderer, which may be then used by the core
of GetOpt. You just need to implement the interface `SPSOstrov\GetOpt\FormatterInterface` having these
methods:

```php
// Formats the whole option list as a single string.
// (return null, if the option list is considered as empty)
public function formatOptionsHelp(array $options): ?string;

// Formats the whole argument list as a single string.
// (return null, if the argument list is considered as empty)
public function formatArgsHelp(array $args): ?string;

// Format the whole help, where the results from formatOptionsHelp() and
// formatArgsHelp() are available. Also the $argv0 parameter is available.
// It is expected to be always a string result.
public function formatHelp(string $argv0, ?string $args, ?string $options): string;
```

Once you have implemented this interface and you have an instance of such a formatter, you may use it in
two ways:

1. Make it a system-default formatter by calling `SPSOstrov\GetOpt\Formatter::setDefault($formatter);`
2. Pass it to the help generating methods, like: `$help = $options->getHelpFormatted($formatter);`


## Miscelaneous functions

### Table formatting

There is an universal table formatting functionality available for Ascii output. You may also use it in your
own applications because it solves the hard part of text formatting into columns. Only a subset of implemeted
table api is documented. **Please use only the documented functions since the undocumented functions still
should be considered unstable.**

Usage example:

```php
use SPSOstrov\GetOpt\AsciiTable;

// Setup a table with output encoding utf8, width of 120 chars and two columns.
// First column has left and right padding set to 1 space
// second column has left padding 0 zpaces and right padding set to 1 space
// Don't use any other arguments of the column, they are considered as unstable.
$table = (new AsciiTable())->encoding("utf8")->width(120)->column(1)->column([0, 1]);

$data = [
    ["--option", "This option is not cool."],
    ["--cool-option", "This is a very cool option"],
];

$output = $table->render($data);

echo $output;
```

### The block formatter

The default formatter (class `SPSOstrov\GetOpt\DefaultFormatter`) is used as default if no other formatter
was specified. This formatter uses multiple blocks. If you want to add your own custom blocks into the help,
you may use the function of the default formatter. You need to obtain an instance of the formatter.

There are two ways how to do it:

```php
use SPSOstrov\GetOpt\Formatter;
use SPSOstrov\GetOpt\DefaultFormatter;

// 1st way:
// Get the default instance. If nothing is set it defaults to an instance of DefaultFormatter.
$instance = Formatter::instance()

// 2nd way:
// Create a completely new formatter:
$instance = new DefaultFormatter();
```

This default formatter has some special methods:

1. `$formatter->setWidth(150)` - set the width being used by the formatter.
2. `$formatter->getWidth($indent)` - get the widh either whole ($indent == false) or without indentation of the subblock ($indent == true)
3. `$formatter->formatBlock($blockCaption, $blockText)` - output a format of a block with a given caption. If $blockText is null, the whole block will not be rendered. If `$blockCaption` is null,
   only the caption will not be rendered. $blockText will be indented.


### Creating a block with wrapped text

If you want to create a well indented text with regular wrapped text, you need to combine even the AsciiTable and also the default formatter:

```php

use SPSOstrov\GetOpt\AsciiTable;
use SPSOstrov\GetOpt\Formatter;

function formatDescription(?string $description): string
{
    // We rely on the fact that the system-wide default formatter is an instance of DefaultFormatter:
    $formatter = Formatter::instance();

    if ($description !== null) {
        $table = (new AsciiTable())->encoding('utf8')->width($formatter->getWidth(true))->column();
        return $ormatter->formatBlock("Description:", $table->render([[$description]]));
    } else {
        return '';
    }
}
```
