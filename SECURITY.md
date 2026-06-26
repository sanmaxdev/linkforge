# Security Policy

## Reporting a vulnerability

**Please do not report security vulnerabilities through public GitHub issues, discussions, or pull
requests.**

Instead, use GitHub's **private vulnerability reporting**:

1. Go to the [Security tab](https://github.com/sanmaxdev/linkforge/security) of the repository.
2. Click **"Report a vulnerability"** and describe the issue.

Please include:

- A description of the vulnerability and its impact.
- Steps to reproduce (a proof of concept if possible).
- Affected version/commit and any relevant configuration.

We aim to acknowledge reports within a few days and will keep you updated on a fix and disclosure
timeline. Please give us reasonable time to release a patch before any public disclosure.

## Supported versions

LinkForge is developed on the `main` branch; security fixes target the latest release. Please make sure
you're on a recent version before reporting.

## Operator hardening notes

LinkForge is fail-open and ships sane defaults, but as the operator you are responsible for the security
of your deployment. A few essentials:

- Keep `APP_DEBUG=false` and `APP_ENV=production` in production.
- Generate a unique `APP_KEY` (`php artisan key:generate`) and never commit your `.env`.
- Serve over HTTPS and keep PHP, the database, and dependencies up to date.
- Configure a payment gateway before charging, and review the third-party keys you enable.
