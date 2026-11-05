# Contributing

Use a runtime listed in `.github/workflows/ci.yml`. Install the development tools, then run:

```sh
composer update
composer validate --strict
composer lint
composer format:check
composer analyse
composer test
```

The test suite checks the README examples and conformance fixtures without credentials.
For live tests, set `THUNDER_API_KEY` or `THUNDER_API_KEY_FILE` to your own key, and optionally
`THUNDER_V1_BASE_URL` and `THUNDER_V2_BASE_URL` to the API endpoints. The other variables are
listed at the top of `tests/Integration/LiveTest.php`:

```sh
composer test:integration
```

Live tests may use quota. Never commit keys or slip data.
Keep pull requests focused, add regression tests and update both READMEs when the API changes.
Run the checks above before submitting. Use Conventional Commits for commit messages and PR titles.
Report vulnerabilities through [SECURITY.md](SECURITY.md).
Maintainers follow [RELEASING.md](RELEASING.md).
