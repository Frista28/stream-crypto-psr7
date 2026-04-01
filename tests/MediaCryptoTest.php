<?php

declare(strict_types=1);

namespace Frista28\StreamCryptoPsr7\Tests;

use Frista28\StreamCryptoPsr7\Crypto\Exception\CryptoOperationFailed;
use Frista28\StreamCryptoPsr7\Crypto\MediaCrypto;
use Frista28\StreamCryptoPsr7\Crypto\MediaType;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MediaCryptoTest extends TestCase
{
    private MediaCrypto $crypto;

    protected function setUp(): void
    {
        $this->crypto = new MediaCrypto();
    }

    public function testItEncryptsAndDecryptsEmptyPayload(): void
    {
        $mediaKey = random_bytes(32);

        $encrypted = $this->crypto->encrypt('', $mediaKey, MediaType::DOCUMENT);
        $decrypted = $this->crypto->decrypt($encrypted, $mediaKey, MediaType::DOCUMENT);

        self::assertSame('', $decrypted);
        self::assertSame(26, strlen($encrypted));
    }

    public function testItEncryptsAndDecryptsPayloadWithExactBlockLength(): void
    {
        $mediaKey = random_bytes(32);
        $payload = str_repeat('A', 16);

        $encrypted = $this->crypto->encrypt($payload, $mediaKey, MediaType::DOCUMENT);
        $decrypted = $this->crypto->decrypt($encrypted, $mediaKey, MediaType::DOCUMENT);

        self::assertSame($payload, $decrypted);
        self::assertSame(42, strlen($encrypted));
    }

    #[DataProvider('chunkSizeProvider')]
    public function testItEncryptsAndDecryptsStreamsWithSmallChunkSizes(int $chunkSize): void
    {
        $mediaKey = random_bytes(32);
        $payload = random_bytes(97);

        $encryptedStream = $this->crypto->encryptStream(
            Utils::streamFor($payload),
            $mediaKey,
            MediaType::VIDEO,
            $chunkSize,
        );

        $encryptedPayload = Utils::copyToString($encryptedStream);

        $decryptedStream = $this->crypto->decryptStream(
            Utils::streamFor($encryptedPayload),
            $mediaKey,
            MediaType::VIDEO,
            $chunkSize,
        );

        self::assertSame($payload, Utils::copyToString($decryptedStream));
    }

    public function testItRejectsNonPositiveChunkSize(): void
    {
        $this->expectException(CryptoOperationFailed::class);
        $this->expectExceptionMessage('Chunk size must be greater than zero');

        $this->crypto->encryptStream(
            Utils::streamFor('payload'),
            random_bytes(32),
            MediaType::IMAGE,
            0,
        );
    }

    public function testItGeneratesExpectedVideoSidecarDuringEncryption(): void
    {
        [
            'encryptedStream' => $encryptedStream,
            'sidecar' => $sidecar,
        ] = $this->crypto->encryptStreamWithSidecar(
            $this->openSampleStream('VIDEO.original'),
            $this->readSampleFile('VIDEO.key'),
            MediaType::VIDEO,
        );

        self::assertSame($this->readSampleFile('VIDEO.encrypted'), Utils::copyToString($encryptedStream));
        self::assertSame($this->readSampleFile('VIDEO.sidecar'), $sidecar);
    }

    #[DataProvider('chunkSizeProvider')]
    public function testSidecarGenerationDoesNotDependOnReadChunkSize(int $chunkSize): void
    {
        [
            'encryptedStream' => $encryptedStream,
            'sidecar' => $sidecar,
        ] = $this->crypto->encryptStreamWithSidecar(
            $this->openSampleStream('VIDEO.original'),
            $this->readSampleFile('VIDEO.key'),
            MediaType::VIDEO,
            $chunkSize,
        );

        self::assertSame($this->readSampleFile('VIDEO.encrypted'), Utils::copyToString($encryptedStream));
        self::assertSame($this->readSampleFile('VIDEO.sidecar'), $sidecar);
    }

    public function testItRejectsSidecarGenerationForNonStreamableMedia(): void
    {
        $mediaKey = random_bytes(32);

        $this->expectException(CryptoOperationFailed::class);
        $this->expectExceptionMessage('Sidecar is supported only for streamable media types');

        $this->crypto->encryptStreamWithSidecar(
            Utils::streamFor('payload'),
            $mediaKey,
            MediaType::IMAGE,
        );
    }

    /**
     * @return iterable<string, array{chunkSize: int}>
     */
    public static function chunkSizeProvider(): iterable
    {
        yield 'one byte' => ['chunkSize' => 1];
        yield 'seven bytes' => ['chunkSize' => 7];
        yield 'one block' => ['chunkSize' => 16];
    }

    private function readSampleFile(string $filename): string
    {
        $contents = file_get_contents(__DIR__ . '/../samples/' . $filename);

        self::assertNotFalse($contents, sprintf('Failed to read sample file "%s"', $filename));

        return $contents;
    }

    private function openSampleStream(string $filename): \Psr\Http\Message\StreamInterface
    {
        $handle = fopen(__DIR__ . '/../samples/' . $filename, 'rb');

        self::assertNotFalse($handle, sprintf('Failed to open sample file "%s"', $filename));

        return Utils::streamFor($handle);
    }
}
