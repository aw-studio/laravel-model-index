# Security Policy

## Supported versions

| Version | Supported |
| ------- | --------- |
| 1.x     | ✅        |
| < 1.0   | ❌        |

## Reporting a vulnerability

**Please do not open a public issue for a security problem.**

Report it privately through
[GitHub Security Advisories](https://github.com/aw-studio/laravel-model-index/security/advisories/new),
or by email to security@aw-studio.de.

Please include what the issue allows an attacker to do, the affected version,
and a reproduction if you have one. You can expect an acknowledgement within a
few working days.

## What counts

`aw-studio/laravel-model-index` turns untrusted query parameters into database queries, so the
things worth reporting are usually:

- A way to read a column an index did not declare as filterable, sortable or
  searchable.
- A way to infer the contents of a column that is never returned — ordering or
  filtering by a secret and reading the result set is enough to extract it a
  character at a time.
- A way to inject SQL through a field name, operator or value.
- A way to make an endpoint return an unbounded number of rows.

For context on the third and fourth: the shipped defaults deny filtering,
sorting and searching until an index declares what it exposes, and cap page
size. An endpoint that opts out of those defaults and is then exploited is a
configuration problem rather than a package vulnerability — but report it
anyway if the documentation led you there, because that is a problem worth
fixing too.
