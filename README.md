# stream-crypto-psr7

PSR-7 stream decorators for WhatsApp-like media encryption.

## Status

The repository contains production crypto primitives and PSR-7 stream decorators in `src/`:

- `Frista28\StreamCryptoPsr7\Crypto\MediaCrypto` for WhatsApp-style media encryption and decryption
- `Frista28\StreamCryptoPsr7\Crypto\MediaType` for media-specific HKDF context selection
- `Frista28\StreamCryptoPsr7\Stream\EncryptingStream` for on-read encryption backed by chunk-oriented crypto processing
- `Frista28\StreamCryptoPsr7\Stream\DecryptingStream` for on-read decryption with MAC validation backed by chunk-oriented crypto processing

The crypto core processes source streams in chunks instead of loading the full payload into a single PHP string before
encrypting or decrypting it. The decorators materialize the transformed result into a seekable temporary stream on first
read, so the public PSR-7 stream remains rewindable and seekable after transformation.

## Usage

```php
<?php

use Frista28\StreamCryptoPsr7\Crypto\MediaType;
use Frista28\StreamCryptoPsr7\Stream\DecryptingStream;
use Frista28\StreamCryptoPsr7\Stream\EncryptingStream;
use GuzzleHttp\Psr7\Utils;

$mediaKey = random_bytes(32);
$plainStream = Utils::streamFor('hello');

$encryptingStream = new EncryptingStream($plainStream, $mediaKey, MediaType::DOCUMENT);
$encryptedPayload = (string) $encryptingStream;

$decryptingStream = new DecryptingStream(
    Utils::streamFor($encryptedPayload),
    $mediaKey,
    MediaType::DOCUMENT,
);

$decryptedPayload = (string) $decryptingStream; // hello
```

If you need lower-level access, `MediaCrypto` also exposes `encryptStream()` and `decryptStream()` for transforming an
existing PSR-7 stream directly.

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
