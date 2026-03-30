# stream-crypto-psr7

PSR-7 stream decorators for WhatsApp-like media encryption.

## Status

The repository now contains the first production crypto primitives in `src/`:

- `Frista28\StreamCryptoPsr7\Crypto\MediaCrypto` for WhatsApp-style media encryption and decryption
- `Frista28\StreamCryptoPsr7\Crypto\MediaType` for media-specific HKDF context selection

The PSR-7 stream decorators described by the package goal have not been added yet.

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

Add the first PSR-7 stream decorator on top of the crypto core together with behavior-focused tests for encryption, decryption, and stream semantics.
