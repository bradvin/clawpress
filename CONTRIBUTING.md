# Contributing to ClawPress

Thanks for contributing.

This document is a lightweight contributor guide for day-to-day development on this plugin.

## Prerequisites

- PHP 8.1+
- WordPress 6.9+
- Composer
- Node.js 18+ and npm

## Local Setup

From the plugin root:

```bash
composer install
npm install
npm run build
```

Then activate the plugin in your local WordPress install.

## Development Workflow

- Run panel/admin asset watchers:

```bash
npm run start
```

- Rebuild production assets and translations:

```bash
npm run build
```

## Code Quality

Run linters before opening a PR:

```bash
npm run lint
```

Useful targeted commands:

```bash
npm run lint:js
npm run lint:css
npm run lint:php
npm run lint:php:fix
```

## Tests

Run PHP unit tests:

```bash
composer test
```

Optional coverage run:

```bash
composer test:coverage
```

## Pull Requests

- Keep PRs focused on a single change.
- Include a clear description of behavior changes.
- Add or update tests when behavior changes.
- If UI behavior changes, include screenshots or short screen recordings.
- Ensure lint and tests pass before requesting review.

## Packaging / Release Build

To create a distributable plugin ZIP:

```bash
npm run deploy
```

This runs lint/build/composer cleanup and writes the ZIP to `dist/`.

## Security

Do not open public issues for security vulnerabilities.
Report security problems privately to the maintainers.
