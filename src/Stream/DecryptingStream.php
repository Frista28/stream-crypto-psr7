<?php

declare(strict_types=1);

namespace Frista28\StreamCryptoPsr7\Stream;

use Frista28\StreamCryptoPsr7\Crypto\MediaCrypto;
use Frista28\StreamCryptoPsr7\Crypto\MediaType;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class DecryptingStream implements StreamInterface
{
    private readonly MediaCrypto $crypto;

    private ?StreamInterface $decryptedStream = null;

    public function __construct(
        private readonly StreamInterface $encryptedStream,
        private readonly string $mediaKey,
        private readonly MediaType $mediaType,
        ?MediaCrypto $crypto = null,
    ) {
        $this->crypto = $crypto ?? new MediaCrypto();
    }

    public function __toString(): string
    {
        try {
            $stream = $this->getDecryptedStream();

            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            return $stream->getContents();
        } catch (\Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        $this->decryptedStream?->close();
        $this->encryptedStream->close();
    }

    public function detach()
    {
        $this->encryptedStream->detach();

        return $this->decryptedStream?->detach();
    }

    public function getSize(): ?int
    {
        return $this->getDecryptedStream()->getSize();
    }

    public function tell(): int
    {
        return $this->getDecryptedStream()->tell();
    }

    public function eof(): bool
    {
        return $this->getDecryptedStream()->eof();
    }

    public function isSeekable(): bool
    {
        return $this->getDecryptedStream()->isSeekable();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->getDecryptedStream()->seek($offset, $whence);
    }

    public function rewind(): void
    {
        $this->getDecryptedStream()->rewind();
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('DecryptingStream is read-only');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        return $this->getDecryptedStream()->read($length);
    }

    public function getContents(): string
    {
        return $this->getDecryptedStream()->getContents();
    }

    public function getMetadata(?string $key = null)
    {
        return $this->getDecryptedStream()->getMetadata($key);
    }

    private function getDecryptedStream(): StreamInterface
    {
        if ($this->decryptedStream !== null) {
            return $this->decryptedStream;
        }

        if ($this->encryptedStream->isSeekable()) {
            $this->encryptedStream->rewind();
        }

        $encryptedPayload = Utils::copyToString($this->encryptedStream);
        $decryptedPayload = $this->crypto->decrypt($encryptedPayload, $this->mediaKey, $this->mediaType);

        $this->decryptedStream = Utils::streamFor($decryptedPayload);

        return $this->decryptedStream;
    }
}
