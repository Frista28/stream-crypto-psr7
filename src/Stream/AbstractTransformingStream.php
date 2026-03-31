<?php

declare(strict_types=1);

namespace Frista28\StreamCryptoPsr7\Stream;

use Frista28\StreamCryptoPsr7\Crypto\Exception\CryptoException;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Base read-only decorator for stream-to-stream payload transformations.
 *
 * Transformation is deferred until the first read operation and is performed
 * as a full-buffer operation (the remaining source payload is loaded into memory).
 */
abstract class AbstractTransformingStream implements StreamInterface
{
    private ?StreamInterface $transformedStream = null;

    public function __construct(
        private readonly StreamInterface $sourceStream,
    ) {}

    public function __toString(): string
    {
        try {
            $stream = $this->getTransformedStream();

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
        $this->transformedStream?->close();
        $this->sourceStream->close();
    }

    /**
     * Detaches both source and transformed streams (when materialized).
     *
     * The returned detached resource belongs to the transformed stream, matching
     * the read-side view exposed by this decorator.
     *
     * @return resource|null
     */
    public function detach()
    {
        $this->sourceStream->detach();

        return $this->transformedStream?->detach();
    }

    public function getSize(): ?int
    {
        return $this->getTransformedStream()->getSize();
    }

    public function tell(): int
    {
        return $this->getTransformedStream()->tell();
    }

    public function eof(): bool
    {
        return $this->getTransformedStream()->eof();
    }

    public function isSeekable(): bool
    {
        return $this->getTransformedStream()->isSeekable();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->getTransformedStream()->seek($offset, $whence);
    }

    public function rewind(): void
    {
        $this->getTransformedStream()->rewind();
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException($this->readOnlyMessage());
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        return $this->getTransformedStream()->read($length);
    }

    public function getContents(): string
    {
        return $this->getTransformedStream()->getContents();
    }

    public function getMetadata(?string $key = null)
    {
        return $this->getTransformedStream()->getMetadata($key);
    }

    /**
     * Applies the concrete payload transformation.
     *
     * @throws CryptoException
     */
    abstract protected function transformPayload(string $payload): string;

    abstract protected function readOnlyMessage(): string;

    /**
     * Materializes and caches transformed payload as a seekable stream.
     *
     * @throws CryptoException
     */
    private function getTransformedStream(): StreamInterface
    {
        if ($this->transformedStream !== null) {
            return $this->transformedStream;
        }

        if ($this->sourceStream->isSeekable()) {
            $this->sourceStream->rewind();
        }

        $sourcePayload = Utils::copyToString($this->sourceStream);
        $transformedPayload = $this->transformPayload($sourcePayload);

        $this->transformedStream = Utils::streamFor($transformedPayload);

        return $this->transformedStream;
    }
}
