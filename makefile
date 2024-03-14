
# Install dependencies after fresh project clone, or after a pull
# of master branch when you have an existing composer.lock file and
# you get errors during build or project run.
bootstrap:
	-@rm composer.lock & rm -rf vendor & composer install -o

# Generate guardrail.phar in the root project
# directory. This phar can be copied to any other
# project for local run.
build-local:
	./src/bin/Build.sh
