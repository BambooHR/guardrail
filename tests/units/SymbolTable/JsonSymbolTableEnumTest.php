<?php

namespace BambooHR\Guardrail\Tests\units\SymbolTable;

use BambooHR\Guardrail\EnumCodeAugmenter;
use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * A developer-declared enum member must survive a symbol table round trip exactly as
 * written.
 *
 * `unserializeClassMembers()` keys members by lowercased name, so two members of the same
 * name collapse into one entry and the later write wins. Anything the augmenter adds is
 * appended after the parsed members, so an augmented member of the same name as a declared
 * one replaces it, taking its modifiers with it.
 *
 * These tests exercise `JsonSymbolTable` directly. The check-level suite runs on
 * `InMemorySymbolTable`, which keeps the parsed node and never round trips, so it cannot
 * observe this.
 */
class JsonSymbolTableEnumTest extends TestCase {
	/**
	 * @param string $code PHP source declaring exactly one enum
	 *
	 * @return Enum_
	 */
	private function roundTrip(string $code): Enum_ {
		$parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
		$traverser = new NodeTraverser();
		$traverser->addVisitor(new NameResolver());
		$ast = $traverser->traverse($parser->parse($code));

		$enum = (new NodeFinder())->findFirstInstanceOf($ast, Enum_::class);
		EnumCodeAugmenter::addEnumPropsAndMethods($enum);

		$table = new JsonSymbolTable(tempnam(sys_get_temp_dir(), "guardrail-test"), sys_get_temp_dir());

		return $table->unserializeClass($table->serializeClass($enum));
	}

	/**
	 * @return void
	 */
	public function testDeclaredStaticValuesStaysStatic() {
		$enum = $this->roundTrip('<?php
			enum SomeBackedEnum: string {
				case Foo = "foo";

				public static function values(): array {
					return array_column(self::cases(), "value");
				}
			}
		');

		$method = $enum->getMethod("values");

		$this->assertNotNull($method, "A declared values() must survive the round trip");
		$this->assertTrue($method->isStatic(), "A declared static values() must stay static");
	}

	/**
	 * The same guarantee in the other direction: an instance method must not come back
	 * static, or a static call to it would be wrongly accepted.
	 *
	 * @return void
	 */
	public function testDeclaredInstanceValuesStaysAnInstanceMethod() {
		$enum = $this->roundTrip('<?php
			enum AnotherBackedEnum: string {
				case Foo = "foo";

				public function values(): array {
					return [];
				}
			}
		');

		$method = $enum->getMethod("values");

		$this->assertNotNull($method, "A declared values() must survive the round trip");
		$this->assertFalse($method->isStatic(), "A declared instance values() must not become static");
	}

	/**
	 * `values()` is not part of the enum language contract, so a backed enum that declares
	 * none must not gain one. Inventing it hides calls that are undefined at runtime.
	 *
	 * @return void
	 */
	public function testBackedEnumGainsNoValuesMethodOfItsOwn() {
		$enum = $this->roundTrip('<?php
			enum PlainBackedEnum: string {
				case Foo = "foo";
			}
		');

		$this->assertNull($enum->getMethod("values"));
	}

	/**
	 * The members PHP really does provide are still present, and static.
	 *
	 * @return void
	 */
	public function testLanguageProvidedMembersSurviveAsStatic() {
		$enum = $this->roundTrip('<?php
			enum LanguageMembersEnum: string {
				case Foo = "foo";
			}
		');

		foreach (["cases", "from", "tryFrom"] as $name) {
			$method = $enum->getMethod($name);
			$this->assertNotNull($method, "$name() must be present on a backed enum");
			$this->assertTrue($method->isStatic(), "$name() must be static");
		}
	}
}
