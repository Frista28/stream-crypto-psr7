<?php

declare(strict_types=1);

namespace Frista28\StreamCryptoPsr7\Stream;

use Frista28\StreamCryptoPsr7\Crypto\MediaCrypto;
use Frista28\StreamCryptoPsr7\Crypto\MediaType;
use Psr\Http\Message\StreamInterface;

/**
 * Read-only decorator that exposes encrypted WhatsApp media payload.
 */
final class EncryptingStream extends AbstractTransformingStream
{
    private readonly MediaCrypto $crypto;

    /**
     * @param StreamInterface $plainStream Source plaintext stream.
     * @param string $mediaKey Raw 32-byte media key.
     */
    public function __construct(
        StreamInterface $plainStream,
        private readonly string $mediaKey,
        private readonly MediaType $mediaType,
        ?MediaCrypto $crypto = null,
    ) {
        parent::__construct($plainStream);
        $this->crypto = $crypto ?? new MediaCrypto();
    }

    /**
     * @throws \Frista28\StreamCryptoPsr7\Crypto\Exception\CryptoException
     */
    protected function transformPayload(string $payload): string
    {
        return $this->crypto->encrypt($payload, $this->mediaKey, $this->mediaType);
    }

    protected function readOnlyMessage(): string
    {
        return 'EncryptingStream is read-only';
    }
}
