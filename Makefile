# Begin Cleaning Targets
clean.dev:
	rm -rf vendor/
	rm -rf node_modules/

# End Cleaning Targets

# Begin Compile Targets
build.package: dist/
	rm -rf package/press-sync/
	yarn archive
	touch package/press-sync/

composer.build.dev: composer.lock
	composer install

composer.build.prod: composer.lock
	composer install --no-dev --optimize-autoloader

build.dev.watch: composer.build.dev
	-rm -rf package/
	-rm -rf dist/
	-yarn run start

build.prod: composer.build.prod
	-rm -rf package/
	-rm -rf dist/
	yarn build

yarn.build.prod: yarn.lock
	yarn install

yarn.lock: package.json

composer.lock: composer.json
	composer update
# End Compile Targets

# Begin Test Targets
# End Test Targets
