# Contributing to KeyVeil

Thanks for helping! KeyVeil is security software, so a few rules matter more than usual.

## Ground rules

1. **Security invariants win.** The server must never receive or store the plaintext, the link key, the optional password or anything derived from them. See [docs/threat-model.md](docs/threat-model.md) for the full list of deliberate product decisions.
2. **No home-made crypto.** Use WebCrypto and the existing protocol in [docs/crypto-protocol-v1.md](docs/crypto-protocol-v1.md). Protocol changes need a new version (`protocol-v2.ts`), golden vectors and docs.
3. **Minimal dependencies.** Every frontend package runs next to future plaintexts. New runtime dependencies need a strong reason in the PR description; pin exact versions.
4. **CSP stays strict.** No inline scripts, inline styles, `innerHTML` with dynamic content, external assets or `unsafe-*` sources.
5. **No sensitive logging.** Never log request bodies, envelopes, tokens or URLs with fragments.
6. **Never weaken a control to make a test pass.**

Found a vulnerability? Do **not** open an issue – follow [SECURITY.md](SECURITY.md).

## Development setup

See the [Quick start](README.md#quick-start-local). You need PHP 8.3+, PostgreSQL and Node.js 22+.

```bash
createdb keyveil keyveil_testing keyveil_e2e
composer setup
composer run dev
```

## Before you open a pull request

```bash
vendor/bin/pint            # PHP formatting
composer types:check       # PHPStan
php artisan test --compact # Pest (needs PostgreSQL)
npm run check              # format + lint
npm run types:check
npm test                   # Vitest
npm run e2e                # Playwright (Chromium, Firefox, WebKit)
```

- Add or update tests with every change. Crypto changes need tests **before** the implementation.
- Keep pull requests small and focused; describe the security impact, if any.
- UI text is currently German. Translations and i18n support are welcome – open an issue first to agree on the approach.

## Commit messages

Short imperative subject line (max. ~50 characters), body explaining _why_ when it isn't obvious.

## License

By contributing you agree that your contributions are licensed under the [Apache License 2.0](LICENSE).
