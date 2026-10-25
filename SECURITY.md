# Security Policy

KeyVeil überträgt kurze Secrets mit Ende-zu-Ende-Verschlüsselung im Browser. Der Server speichert ausschließlich einen verschlüsselten Envelope und Lifecycle-Metadaten; Schlüssel (URL-Fragment), Klartext und optionales Passwort verlassen den Browser nie.

## Sicherheitslücken melden

Bitte **keine** öffentlichen Issues für Sicherheitslücken. Melde sie vertraulich an die Maintainer (Kontaktadresse vor Public Release hier eintragen). Wir bestätigen den Eingang und koordinieren Fix und Veröffentlichung.

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
