# stream-crypto-psr7

PSR-7 stream decorators for WhatsApp-like media encryption.

## Status

This repository is currently a scaffold. Tooling, package metadata, and project layout are in place, but the production stream implementation has not been added to `src/` yet.

## Quality Checks

Run the full local quality gate:

```bash
composer check
```

Run individual tools:

```bash
composer stan
composer cs:check
composer cs:fix
composer test
```

## Development

The project targets PHP 8.2 and uses:

- PHPUnit for tests
- PHPStan for static analysis
- PHP CS Fixer for code style

## Next Step

Add the first stream decorator in `src/` together with behavior-focused tests for encryption, decryption, and PSR-7 stream semantics.
