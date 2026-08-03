<?php

namespace BambooHR\Guardrail\Tests\SymbolTable;

use BambooHR\Guardrail\EnumCodeAugmenter;
use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * The augmented enum members must survive a symbol table round trip with their modifiers
 * intact.
 *
 * `unserializeClassMembers()` keys members by lowercased name, so a synthesized member and
 * a developer-declared member of the same name collapse into one entry and the later write
 * wins. That makes the modifiers on a synthesized member load-bearing: if they disagree
 * with the real declaration, the real one is replaced by a wrong description of itself.
 *
 * Note that these tests exercise `JsonSymbolTable` specifically. The check-level test
 * suite runs on `InMemorySymbolTable`, which keeps the parsed node and never round trips,
 * so it cannot observe this class of problem.
 */
class JsonSymbolTableEnumTest extends TestCase {

	/**
	 * @param string $code PHP source declaring exactly one enum
	 *
	 * @return Enum_
	 */
	private function augmentedEnum(string $code): Enum_ {
		$parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
		$traverser = new NodeTraverser();
		$traverser->addVisitor(new NameResolver());
		$ast = $traverser->traverse($parser->parse($code));

		$enum = (new NodeFinder())->findFirstInstanceOf($ast, Enum_::class);
		EnumCodeAugmenter::addEnumPropsAndMethods($enum);

		return $enum;
	}

	/**
	 * @param Enum_ $enum The enum to round trip
	 *
	 * @return Enum_
	 */
	private function roundTrip(Enum_ $enum): Enum_ {
		$table = new JsonSymbolTable(tempnam(sys_get_temp_dir(), "guardrail-test"), sys_get_temp_dir());

		return $table->unserializeClass($table->serializeClass($enum));
	}

	/**
	 * A backed enum may declare its own `values()` helper. `values()` is not part of the
	 * enum language contract, so the name is not reserved and the declaration stands.
	 *
	 * @return void
	 */
	public function testDeclaredStaticValuesSurvivesTheRoundTripAsStatic() {
		$enum = $this->augmentedEnum('<?php
			enum SomeBackedEnum: string {
				case Foo = "foo";
				case Bar = "bar";

				public static function values(): array {
					return array_column(self::cases(), "value");
				}
			}
		');

		$method = $this->roundTrip($enum)->getMethod("values");

		$this->assertNotNull($method, "values() must exist after the round trip");
		$this->assertTrue(
			$method->isStatic(),
			"A declared `public static function values()` must not come back non-static"
		);
	}

	/**
	 * The same guarantee for a backed enum that declares nothing of its own: the
	 * synthesized `values()` is a static method, matching cases(), from() and tryFrom().
	 *
	 * @return void
	 */
	public function testSynthesizedValuesIsStatic() {
		$enum = $this->augmentedEnum('<?php
			enum PlainBackedEnum: string {
				case Foo = "foo";
			}
		');

		$this->assertTrue(
			$this->roundTrip($enum)->getMethod("values")->isStatic(),
			"The synthesized values() must be static, as its siblings are"
		);
	}

	/**
	 * Guards the collapse itself: whichever entry survives, exactly one `values()` must be
	 * present so the surviving modifiers are unambiguous.
	 *
	 * @return void
	 */
	public function testOnlyOneValuesEntrySurvivesTheRoundTrip() {
		$enum = $this->augmentedEnum('<?php
			enum AnotherBackedEnum: string {
				case Foo = "foo";

				public static function values(): array {
					return [];
				}
			}
		');

		$surviving = array_filter(
			$this->roundTrip($enum)->stmts,
			fn($stmt) => $stmt instanceof ClassMethod && $stmt->name->toString() === "values"
		);

		$this->assertCount(1, $surviving);
	}
}
