.PHONY: bootstrap test lint lint-changed

bootstrap:
	bin/bootstrap

test:
	composer test

lint:
	npm run lint

lint-changed:
	npm run lint:changed
