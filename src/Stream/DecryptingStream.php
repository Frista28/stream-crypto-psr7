<?php

declare(strict_types=1);

namespace Frista28\StreamCryptoPsr7\Stream;

use Frista28\StreamCryptoPsr7\Crypto\MediaCrypto;
use Frista28\StreamCryptoPsr7\Crypto\MediaType;
use Psr\Http\Message\StreamInterface;

/**
 * Read-only decorator that exposes decrypted WhatsApp media payload.
 */
final class DecryptingStream extends AbstractTransformingStream
{
    private readonly MediaCrypto $crypto;

    /**
     * @param StreamInterface $encryptedStream Source encrypted stream (payload + MAC).
     * @param string $mediaKey Raw 32-byte media key.
     */
    public function __construct(
        StreamInterface $encryptedStream,
        private readonly string $mediaKey,
        private readonly MediaType $mediaType,
        ?MediaCrypto $crypto = null,
    ) {
        parent::__construct($encryptedStream);
        $this->crypto = $crypto ?? new MediaCrypto();
    }

    /**
     * @throws \Frista28\StreamCryptoPsr7\Crypto\Exception\CryptoException
     */
    protected function transformStream(StreamInterface $stream): StreamInterface
    {
        return $this->crypto->decryptStream($stream, $this->mediaKey, $this->mediaType);
    }

    protected function readOnlyMessage(): string
    {
        return 'DecryptingStream is read-only';
    }
}
