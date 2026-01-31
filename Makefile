.PHONY: setup test units lint index analyze coverage

setup:
	composer install

test: units lint index analyze

units:
	cd tests
	./vendor/bin/phpunit --configuration ./tests/phpunit.xml
	cd ..

lint:
	./vendor/bin/phpcs --standard=./.phpcs.xml src/ -s

index:
	php src/bin/guardrail.php -i -j self.json

analyze:
	php src/bin/guardrail.php -a -j self.json

coverage:
	./vendor/bin/phpunit --configuration ./tests/phpunit.xml --coverage-html ./coverage