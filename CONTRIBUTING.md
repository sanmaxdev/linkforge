# Contributing to LinkForge

Thanks for your interest in improving LinkForge! Contributions of all kinds are welcome — bug fixes,
features, docs, and translations.

## Ground rules

- By contributing, you agree your work is licensed under the project's [MIT License](LICENSE).
- Be respectful — this project follows a [Code of Conduct](CODE_OF_CONDUCT.md).
- For anything non-trivial, open an issue first to discuss the approach before you start.

## Development setup

```bash
git clone https://github.com/sanmaxdev/linkforge.git
cd linkforge
composer install
npm install
cp .env.example .env
php artisan key:generate
# point DB_* in .env at a local MySQL/MariaDB database, then:
php artisan migrate --seed
npm run dev        # Vite dev server
php artisan serve
```

The test suite runs on an in-memory SQLite database, so you don't need MySQL just to run tests.

## Before you open a pull request

Run the same checks CI runs — all must pass:

```bash
vendor/bin/pint          # auto-format to the project code style
php artisan test         # full test suite
npm run build            # assets must compile
```

- **Code style:** we use [Laravel Pint](https://laravel.com/docs/pint). Run `vendor/bin/pint` before
  committing (CI runs `pint --test` and will fail on un-formatted code).
- **Tests:** add or update tests for any behavior change. Keep the suite green.
- **Commits:** keep them focused; write clear messages. Reference the issue you're addressing.
- **Scope:** one logical change per PR — it's easier to review and merge.

## Pull request process

1. Fork the repo and create a branch from `main` (e.g. `fix/redirect-loop` or `feat/qr-templates`).
2. Make your change, with tests and formatting.
3. Open a PR against `main`. Fill in the PR template.
4. CI (tests + lint + assets build) must pass, and a maintainer must approve before merge. `main` is
   protected — changes land only through reviewed PRs.

## Reporting bugs / requesting features

Use the [issue templates](https://github.com/sanmaxdev/linkforge/issues/new/choose). For **security**
vulnerabilities, do **not** open a public issue — see [SECURITY.md](SECURITY.md).

Questions or ideas? Start a [Discussion](https://github.com/sanmaxdev/linkforge/discussions).
