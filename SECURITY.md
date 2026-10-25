# Security Policy

KeyVeil überträgt kurze Secrets mit Ende-zu-Ende-Verschlüsselung im Browser. Der Server speichert ausschließlich einen verschlüsselten Envelope und Lifecycle-Metadaten; Schlüssel (URL-Fragment), Klartext und optionales Passwort verlassen den Browser nie.

## Sicherheitslücken melden

Bitte **keine** öffentlichen Issues für Sicherheitslücken. Melde sie vertraulich über [GitHub Private Vulnerability Reporting](https://github.com/Smart-HV/Keyveil/security/advisories/new). Wir bestätigen den Eingang und koordinieren Fix und Veröffentlichung.

_Please report vulnerabilities privately via [GitHub security advisories](https://github.com/Smart-HV/Keyveil/security/advisories/new), never in a public issue._

## Dokumentation

- [Threat Model](docs/threat-model.md) — wogegen KeyVeil schützt und wogegen nicht
- [Crypto-Protokoll v1](docs/crypto-protocol-v1.md) — normative Spezifikation inkl. Test-Vektoren
- [Privacy & Datenfluss](docs/privacy-data-flow.md)
- [Betrieb](docs/operations.md) — Proxy, Backups, Deployment, Release-TODOs

## Grenzen

- Ein kompromittierter Server kann manipuliertes JavaScript ausliefern. Das ist bei Web-Apps grundsätzlich nicht vollständig vermeidbar.
- Wer den vollständigen Link besitzt, kann ein nicht passwortgeschütztes Secret öffnen.
- Gelöschte Secrets können (verschlüsselt) in historischen Backups existieren.
- JavaScript garantiert kein sicheres Überschreiben von Speicher.
