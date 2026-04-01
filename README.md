# stream-crypto-psr7

PSR-7 stream decorators for WhatsApp-like media encryption.

The package provides:

- `Frista28\StreamCryptoPsr7\Stream\EncryptingStream` for PSR-7 stream encryption
- `Frista28\StreamCryptoPsr7\Stream\DecryptingStream` for PSR-7 stream decryption with MAC validation
- `Frista28\StreamCryptoPsr7\Crypto\MediaCrypto` for lower-level string and stream APIs
- `Frista28\StreamCryptoPsr7\Crypto\MediaType` for media-specific HKDF context selection

The crypto core processes source streams in chunks instead of loading the full payload into a single PHP string. The
decorators materialize the transformed result into a seekable temporary stream on first read, so the exposed PSR-7
stream remains rewindable and seekable after transformation.

## Requirements

- PHP 8.2 or newer
- `ext-openssl`

## Installation

```bash
composer require frista28/stream-crypto-psr7
```

## Usage

### Stream Decorators

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

### Low-Level Crypto API

`MediaCrypto` also exposes direct string and stream APIs when you do not need the decorators:

```php
<?php

use Frista28\StreamCryptoPsr7\Crypto\MediaCrypto;
use Frista28\StreamCryptoPsr7\Crypto\MediaType;
use GuzzleHttp\Psr7\Utils;

$crypto = new MediaCrypto();
$mediaKey = random_bytes(32);

$encrypted = $crypto->encrypt('hello', $mediaKey, MediaType::DOCUMENT);
$decrypted = $crypto->decrypt($encrypted, $mediaKey, MediaType::DOCUMENT);

$encryptedStream = $crypto->encryptStream(
    Utils::streamFor('hello'),
    $mediaKey,
    MediaType::DOCUMENT,
);
```

### Sidecar Generation

For streamable media types (`VIDEO` and `AUDIO`), `MediaCrypto` can generate WhatsApp-compatible sidecar metadata in
the same encryption pass without rereading the plaintext source stream:

```php
<?php

use Frista28\StreamCryptoPsr7\Crypto\MediaCrypto;
use Frista28\StreamCryptoPsr7\Crypto\MediaType;
use GuzzleHttp\Psr7\Utils;

$crypto = new MediaCrypto();
$mediaKey = random_bytes(32);

[
    'encryptedStream' => $encryptedStream,
    'sidecar' => $sidecar,
] = $crypto->encryptStreamWithSidecar(
    Utils::streamFor(fopen('video.mp4', 'rb')),
    $mediaKey,
    MediaType::VIDEO,
);
```

The returned `sidecar` is a binary string that should be stored alongside the encrypted media object and used as
streaming metadata for chunk validation.

The library currently implements full-stream encryption/decryption and sidecar generation. Chunk-level validation and
offset-based partial decryption are intentionally outside the current API surface.

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
