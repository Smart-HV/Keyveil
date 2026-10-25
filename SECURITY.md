# Security policy

KeyVeil shares short secrets with end-to-end encryption in the browser. The server stores only an encrypted envelope and lifecycle metadata; the key (URL fragment), the plaintext and the optional password never leave the browser.

## Reporting a vulnerability

Please **do not** open public issues for security vulnerabilities. Report them privately through [GitHub private vulnerability reporting](https://github.com/Smart-HV/Keyveil/security/advisories/new). We will acknowledge your report and coordinate the fix and disclosure with you.

Helpful details: affected version or commit, steps to reproduce, impact, and a suggested fix if you have one. Never include real secrets or share links.

## Supported versions

Security fixes are made on the `main` branch. Until a first stable release is tagged, please always run the latest `main`.

## Documentation

- [Threat model](docs/threat-model.md) — what KeyVeil protects against, and what it does not
- [Crypto protocol v1](docs/crypto-protocol-v1.md) — normative specification including test vectors
- [Privacy and data flow](docs/privacy-data-flow.md)
- [Operations](docs/operations.md) — reverse proxy, backups, deployment, release checklist

## Known limitations

- A compromised server can deliver manipulated JavaScript. This cannot be fully prevented in a web application.
- Anyone who has the full link can open a secret that is not password protected.
- Deleted secrets can still exist (encrypted) in older backups.
- JavaScript cannot guarantee that memory is securely wiped.
- KeyVeil has not yet had an independent security audit.
