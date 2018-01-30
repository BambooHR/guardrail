# Guardrail - A PHP Static Analysis tool
Copyright (c) 2017 Jon Gardiner and BambooHR

[![Build Status](https://travis-ci.org/BambooHR/guardrail.svg?branch=master)](https://travis-ci.org/BambooHR/guardrail) [![Latest Stable Version](https://poser.pugx.org/bamboohr/guardrail/v/stable.png)](https://packagist.org/packages/bamboohr/guardrail) [![Total Downloads](https://poser.pugx.org/bamboohr/guardrail/downloads)](https://packagist.org/packages/bamboohr/guardrail) [![Latest Unstable Version](https://poser.pugx.org/bamboohr/guardrail/v/unstable)](https://packagist.org/packages/bamboohr/guardrail) [![License](https://poser.pugx.org/bamboohr/guardrail/license)](https://packagist.org/packages/bamboohr/guardrail) [![composer.lock](https://poser.pugx.org/bamboohr/guardrail/composerlock)](https://packagist.org/packages/bamboohr/guardrail)  

## Introduction

Guardrail is a static analysis engine for PHP 5 - 7.  Guardrail will index your code base, learn
every symbol, and then confirm that every file in the system uses those symbols in a way that
makes sense.  For example, if you have a function call to an undefined function, it will be
found by Guardrail.

Guardrail is not proof that your code is perfect or even semantically valid.  You should never 
use Guardrail as an excuse not to write unit tests.  Rather, it is a final layer of protection to 
give confidence that preventable mistakes, syntax errors, or typos do not occur.  You can think 
of Guardrail like the guardrails along a highway, you never want to hit one, but you're glad to 
know they are there.

At BambooHR we are big believers in continuous integration.  We use Guardrail inside our open 
sourced CI tool, Rapid. (See https://github.com/BambooHR/rapid)  This is done in addition to a
healthy set of unit and integration tests that we also run against all layers of our stack.

Guardrail uses Nikita Popov's excellent PHP parser library. (See https://github.com/nikic/PHP-Parser)

## The need for a tool like Guardrail in PHP

According to W3Techs (https://w3techs.com/technologies/overview/programming_language/all) in 2017 PHP 
is running on 82% of all the sites whose server-side language they can determine.  Other documentation
confirms that a vast majority of dynamic content on the Internet is served from PHP.  PHP powers
massive sites such as Facebook and Wikipedia.

Often these sites start from a small home grown code base, a Wordpress install, or a few customizations 
on top of a framework.  These are great options that play to the strengths of PHP.  You can quickly 
prop up a website and prove the business model before you spend a lot of time and money worrying 
about enterprise scale.  The PHP language performs reasonably well and is very quick to develop 
with.  The language is very forgiving, has a very mature library ecosystem with Composer, several robust frameworks, 
and broad hosting availability.

For a small website PHP works exceedingly well.  If you are lucky enough to have a formerly small website that 
has grown up, you will start to run into difficulties dealing with large code base in PHP.  Many of these 
complications are due to the fact that PHP is a weakly typed language.  The lack of enforcements of 
contracts in the language makes it difficult to know what to expect about any given variable.  On a small
team and code base this is no problem.  On a large team or large code base, this becomes unmanageable.
Also, as your start to use more strongly typed improvements to PHP, you discover that those errrors are
not reported until run time.  It would be far better to know prior to release that errors existed in your
application.

Guardrail is a tool that allows you to find some subset of the errors in your application.  If you make
heavy use of type hinting, you'll find that Guardrail enables you to actually be quite rigorous.  It can be
applied to any PHP 5 - PHP 7 code base.  

   
## Supported checks

Guardrail classifies checks by name.  Here is the standard list of errors.  Note that all Guardrail errors
 start with the word "Standard."  Custom plugins, should begin with a different string.  (Ideally, an 
 organization name for the organization creating the plugin.)

Name | Description
--- | ---
Standard.Access.Violation | Accessing a protected/private variable in a context where you are not allowed to access them.
Standard.Autoload.Unsafe | Code that executes any statements other than a class declaration.
Standard.ConditionalAssignment | Assigning a variable in conditional expression of an if() statement.
Standard.Constructor.MissingCall | Overriding a constructor without calling the parent constructor
Standard.Debug | Typical debug statements such as var_dump() or print_r()
Standard.Deprecated.Internal | Call to an internal PHP function that is deprecated
Standard.Deprecated.User | Call to a user function that has @deprecated in the docblock.
Standard.Exception.Base | Catching the base \Exception class instead of something more specific.
Standard.Incorrect.Static | Static reference to a dynamic variable/method
Standard.Incorrect.Dynamic | Dynamic reference to a static variable/method
Standard.Inheritance.Unimplemented | Class implementing an interface fails to implement on of it's methods.
Standard.Function.InsideFunction | Declaring a function inside of another function.  (Closures/lambdas are still allowed.)
Standard.Global.Expression | Referencing $GLOBALS[ $expr ]
Standard.Global.String | Referencing a global with either global $var or $GLOBALS['var']
Standard.Goto | Any instance of a "goto" statement
Standard.Metrics.Complexity | Any method/function with a cyclomatic complexity of 10 or greater.
Standard.Param.Count | Failure to pass all the declared parameters to a function.
Standard.Param.Count.Excess | Passing too many variables to a function (ignores variadic functions)
Standard.Param.Type | Type mismatch on a parameter to a function
Standard.Parse.Error | A parse error
Standard.Psr4 | The namespace of the class must match in the final parts of the path with a ".php" on the end.
Standard.Return.Type | Type mismatch on a return from a function
Standard.Scope | Usage of parent:: or self:: when in a context where they are not available.
Standard.Security.Eval | Code that runs eval() or create_function()
Standard.Security.Shell | Code that runs a shell (exec, passthru, system, etc)
Standard.Security.Backtick | The backtick operator
Standard.Switch.Break | A switch case: statement that falls through (generally these are unintentional)
Standard.Switch.BreakMultiple | A "continue #;" or "break #;" statement (where # is an integer)
Standard.Unknown.Callable | A callable that can't be resolved into a class method or function.
Standard.Unknown.Class | Reference to an undefined class
Standard.Unknown.Class.Constant | Reference to an undefined constant inside of a class
Standard.Unknown.Class.Method | Reference to an unknown class method
Standard.Unknown.Class.MethodString | Occurrences of Foo::class."@bar" where Foo::bar doesn't exist.
Standard.Unknown.Function | Reference to an unknown function
Standard.Unknown.Global.Constant | Reference to an undefined global constant (define or const)
Standard.Unknown.Property | Reference to a property that has not previously been declared
Standard.Unknown.Variable | Reference to a variable that has not previously been assigned
Standard.Unsafe.Timezone | Functions, such as date() that use a server setting for timezone instead of explicitly passing the timezone.
Standard.Unused.Variable | A local variable is assigned but never read from.
Standard.Unreachable | Code inside a block after a return, break, continue, etc.
Standard.VariableFunctionCall | Call a method $foo() when $foo is a string.  (Still ok if $foo is a callable)
Standard.VariableVariable | Referencing a variable with $$var

  
 Guardrail has support for advanced PHP features, such as traits, interfaces, anonymous functions & classes, etc.
 
 Additionally, a simple plugin system exists that allows you to register node visitors for 
 the abstract syntax tree for to enable additional checks. At BambooHR, we use this plugin mechanism
 to run some additional checks that are only relevant to our stack.
 
 ## Limitations
 
 - Guardrail assumes that all classes and functions are available in all locations.  It does 
 not check your autoloader or require statements to confirm that you have actually loaded a source 
 file in any particular context.
 - PHP allows you to declare a function inside of another function.  This nested function
  actually has global scope, but is only visible after the outer function has executed.  Guardrail 
  does not support this use pattern.
 - Guardrail does not conditionally process functions.  If the function is defined either at 
 the top level or nested in a function, then it will be indexed and considered as globally available.
 - Guardrail relies upon reflection to determine availability of internal PHP methods and functions.
 You will want to run Guardrail in the same environment that your code is expected to run in.  Note that it
 is common for command line installs of PHP to use a different config file (and, therefore, different extensions) 
 than the fastcgi/modphp config.  If you are testing a website, make sure your CLI config loads the
 same extensions as your server config.
 - Guardrail is capable of doing simple type inference.  If your variable is certain
  to only contain one type of data then checks will be enforced on that variable.  If the variable 
  could contain multiple different values then Guardrail will have to assume you are using the 
  variable correctly.
  
  ## Requirements
  
 - Requires PHP 5.5, Sqlite extension, Gzip extension, and Composer.  
 - The more memory the better.  Moderately large code bases can use up to 500MB.  
 - Runs significantly faster in PHP 7.  
 
 ## Installation
 
 Guardrail is available as a composer packaged BambooHR/Guardrail.
 
 It will install itself in vendor/bin/guardrail.php.
 
 You can also package Guardrail as a .phar file by running Build.sh
   which is found in vendor/bamboohr/guardrail/src/bin.
 
## Usage
 
There are two phases of execution in Guardrail: indexing and analysis.

### Indexing
The indexing phase can only be run in a single process.  A moderately large
code base including all vendor libraries can take a few minutes to index.

### Analysis
One the index is produced, the analysis can be run.  Analysis is heavily CPU bound.  
It can be run across multiple processes or even multiple machines.  When 
run across multiple machines, you will need to gather the output from all of
them to review the results.  (BambooHR uses Rapid to automate this.)

### Configuration

Guardrail configuration consists of 7 sections:
  options, index, ignore, test, test-ignore, emit, and plugins.
  
The *options* section is "optional".  Currently it allows you to 
enable type inference based on DocBlocks.  Often codebases will have
a lot of DocBlocks that actually reference types that don't exist 
or aren't namespaced correctly.  By default DocBlocks will not
be used in type inference.  If you enable DocBlocks then Guardrail can be
much more exhaustive in what it checks.  See the options section
below for the options that can be defined.
  
The *index* section is a list of subdirectories to index.
 The *ignore* section is a list of file paths to ignore from indexing.  The 
 ignore section can use globbing patterns include double asterisks
 to indicate any number of directories.    
 
 These two sections work together to determine what files will be indexed.  
 Any file listed under an index directory, but not excluded by an *ignore* block
 will be indexed.  It is important to index as much of your code base as possible 
 because otherwise it will not be possible to resolve includes.
 
 The *test* section is a list of directories to run the analysis phase on.  
 The *test-ignore* is a list of file paths to ignore from analysis.  This section
 can also use globbing patterns to ignore multiple files at once.
 
 The *emit* section is used to control which errors are reported.  Most
 code bases will not pass with all of the standard checks emitted.  We
 recommend adding a single check at a time and incrementally improving
 your codebase until all tests pass.  If an emit string ends with ".*" then any
 rule matching everything before the final ".*" in the pattern is considered a match and
 will be output.  Example: `emit: ["Standard.Security.*"]` to emit all security warnings.
 
 The *plugins* section is a lot of plugins to use in the analysis.
 Plugins allow you to extend Guardrail with your own checks.  
 

Sample config file:

```json
{
	"options": {
		"DocBlockReturns" : true,
		"DocBlockParams" :  true,
		"DocBlockInlineVars" : true,
		"DocBlockProperties": true
	},
    "index": [
        "app",
        "vendor",
        "/usr/share/php"
    ],
    "ignore": [
             "**/vendor/**/tests/**/*",
             "**/vendor/**/Tests/**/*"
    ],
    "test": [
         "app/html",
         "app/includes"
     ],
    "test-ignore": [ 
        "**/vendor/**/*" 
    ],
    "emit":
    [
        "Standard.Unknown.Class",
        "Standard.Unknown.Class.Constant",
        "Standard.Unknown.Function",
        "Standard.Unknown.Variable",

        "Standard.Inheritance.Unimplemented",
 
        "Standard.Scope",
        "Standard.Param.Count",
        "Standard.Param.Type",

        "Standard.Switch.Break",
        "Standard.Parse.Error",
        
        {
        	"emit":"Standard.Security.Shell",
        	"glob":"**/System/**/*",
        	"glob-igore": "**/System/Shell/**/*"
        },
        
        {
        	"emit":"Standard.Unknown.Class.Method",
        	"when": "new"
        },
        
        "BambooHR.Impossible.Inject"
    ], 
    "plugins": [
        "plugins/guardrail/ImpossibleInjectionCheck.php"
    ]
}
```

The simplest version of an emit entry is a simple string that identifies the type of error to always emit.
A longer form is a nested JSON object.  It may contain a single glob string that the filename must match and,
 optionally, a glob-ignore string to ignore.  You may define multiple globbing rules per type of error.  
 If the error passes any one section it will be emitted.

You can also disable an error for the duration of a function by adding `@guardrail-ignore [type1],[type2]`
in your function's docblock.  (Where [type#] is the name of the check to disable.)   Any check you disable will not
 be emitted during the analysis of that particular function.

### Command line

Note: Command line usage will probably change significantly in the v1.0 release. 

<pre>
Usage: php -d memory_limit=500M vendor/bin/guardrail.php [-a] [-i] [-n #] [-o output_file_name] [-p #/#] config_file

where: -p #/#                 = Define the number of partitions and the current partition.
                                Use for multiple hosts. Example: -p 1/4

       -n #                   = number of child process to run.
                                Use for multiple processes on a single host.  A good rule of thumb is 1 process per CPU core.

       -a                     = run the "analyze" operation

       -i                     = run the "index" operation.
                                Defaults to yes if using in memory index.
                                
       --diff patch_file      = Allows you to limit results to only those errors occuring on
                                lines in a particular patch set.  Requires unified diff format taken
                                from the root directory of the project.  Must set emit { "when": "new" }
                                for each error that you want to emit in this fashion.                                                                     
                                
       --format format        = Select choose between "xunit", "text", or "counts"                                 

       -s                     = prefer sqlite index

       -m                     = prefer in memory index (only available when -n=1 and -p=1/1)

       -o output_file_name    = Output results in junit format to the specified filename
       
       

       -v                     = Increase verbosity level.  Can be used once or twice.

       -h  or --help          = Ignore all other options and show this page.


</pre>

To index all according to the config.json file, storing the index
in sqlite database, use the following command line.

`php vendor/bin/guardrail.php -i -s config.json`

To run the analysis 

`php vendor/bin/guardrail.php -a -s config.json`

If you want to see progress during either the index or analysis phase
use -v to enable verbose output.

By default, a report is output in Xunit format to standard out.  If you
would prefer to output to a file use -o to specify an output filename.

### Incremental scanning

If you use the `--diff patch_file` option to Guardrail then you can filter your 
results based on just the lines identified as changed in the patch set.  This
is a helpful feature for incrementally improving your codebase.  You can, for example,

The patch file must be in Unified diff format, taken from the root directory of your
project.  (The same directory that holds your Guardrail config file.)

set:
<pre> 
{
	"emit" : "Standard.VariableVariable",
	"when" : "new"
}
</pre>

to emit a "Standard.VariableVariable" error only when the error occurs in the
patchset that you are testing.  At BambooHR, we have wired this in to our RapidCI
setup so that every new commit is tested to a higher standard than we can enforce 
on the legacy code.  Using this approach you can raise the quality of your
codebase over time. 

### Object casting

Languages like Java or C# support casting an object reference from one type 
 to another.  This allows you to convert an object that supports multiple 
 interfaces from one interface to another.  That nature of the object hasn't changed, 
 just the way the compiler understands it.
 
 In PHP this type of conversion is unnecessary.  If an object has a method 
 with the correct name then it can be invoked. 
 
 For purposes of static analysis it is important that you only invoke documented 
 methods of an interface.  If you are passing an object that implements multiple
 interfaces, you need to "cast" that object to access one of the interfaces.  
 Guardrail will honor the result of a simple if() statement containing only a 
 variable and an "instanceof" operation.  This is usually a benign change to make because
 you would never want to call an interface method if the object didn't implement that 
 interface.
 
 <pre>
 if($var instanceof Foo) { 
 	$var->fooInterfaceMethod(); // $var assumed to be a "Foo" inside this clause.
 }
 </pre>
 
 
 If you have an instance of a variable that is ALWAYS a subtype, then you 
 can use either of these cast techniques as well:
 
 <pre>
 assert($var instanceof Foo); // PHP 7 asserts. 
 </pre>
 
 or 
 
 <pre>
 /** var Foo $var  Typical doc block cast. */
 </pre>
 
 



# Links

[Plugin architecture](docs/plugins.md)
 
