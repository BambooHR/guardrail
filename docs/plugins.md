# Guardrail Plugins
Copyright (c) 2017 Jonathan Gardiner and BambooHR

## Theory of operation
A plugin to Guardrail is responsible for inspecting the abstract syntax tree (AST) and possibly 
the symbol table in order to perform some type of validation of the code.  The AST the tree
from Nikita Popov's nikic/php-parser package.

A plugin registers itself to be notified when one or more types of AST nodes are encountered.  The
static analysis portion of Guardrail will use a node visitor to walk the entire AST and at each node
it will run any plugins that are relevant to that type of node.

## Installing a plugin

To install a plugin simply add it's filename to your "plugins" list in the configuration json.  A 
relative path will be resolved relative to the directory containing your configuration file.

The last statement of your plugin file should be the following:

```php
return function($index, $output) {
        return new MyPlugin($index, $output);
};
```

This line declares an anonymous function and returns that function.  Guardrail will call
that function to create your plugin.  By having the plugin responsible for instantiating 
itself, we avoid any issues with namespaces or autoloading.  If you need to autoload 
additional classes, add an autoloader for them in your plugin.  Most plugins will only 
need to be a single class and you can just implement and instantiate the plugin in the same file.

## The symbol table
The symbol table is responsible for retrieving various types of objects by name.  It can either 
retrieve an abstract representation of the symbol (it's name, methods, member constants, etc).  Or, 
it can return the actual AST for that symbol.  The AST will already have been adjusted to use
full namespace names and to import any traits into class as appropriate.

It is prefereable to use the abstract symbol objects.  Those objects wrap PHP's reflection 
mechanisms and make it possible to inspect built-in symbols just as easily as symbols from the AST. 


## Registering for AST nodes

Each plugin should implement getCheckNodesTypes().  This method simply returns a list of
full qualified calass names.  We recommend you use the ::class notation to avoid errors.


## Emitting errors
Each plugin received an instance of an object implementing the OutputInterface.  The most important 
method in that interface is emitError().  This method is what you should use when you detect an
error.  Because emitError() is so common, a a helper method is implemented in the BaseCheck that will
 call the method for you with your class name.  The helper method will also automatically figure out the line
 number of the node you are using as the location of the error.  The signature for the helper method is:
    
```php 
function emitError($fileName, $node, $type, $message="");
```

## Appendix - A real world check for catching an unknown object type.

```php
class CatchCheck extends BaseCheck
{
	function getCheckNodeTypes() {
		return [\PhpParser\Node\Stmt\Catch_::class];
	}
 
	function run($fileName, $node, ClassLike $inside=null, Scope $scope=null) {
		$name = $node->type->toString();
		if ($this->symbolTable->ignoreType($name)) {
			return;
		}
		$this->incTests();
		if (!$this->symbolTable->getAbstractedClass($name)) {
			$this->emitError($fileName,$node,self::TYPE_UNKNOWN_CLASS, "Attempt to catch unknown type: $name");
		}
	}
}
```

