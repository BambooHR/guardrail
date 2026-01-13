setup:
	composer install

test: units lint index analyze

units:
	./vendor/bin/phpunit --configuration tests/phpunit.xml tests

lint:
	./vendor/bin/phpcs --standard=./tests/ruleset.xml src/

index:
	php src/bin/guardrail.php -i -j self.json

analyze:
	php src/bin/guardrail.php -a -j self.json