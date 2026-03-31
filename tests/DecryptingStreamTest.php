<?php

declare(strict_types=1);

namespace Frista28\StreamCryptoPsr7\Tests;

use Composer\Autoload\ClassLoader;
use Frista28\StreamCryptoPsr7\Crypto\Exception\InvalidCiphertext;
use Frista28\StreamCryptoPsr7\Crypto\Exception\InvalidMac;
use Frista28\StreamCryptoPsr7\Crypto\Exception\InvalidMediaKey;
use Frista28\StreamCryptoPsr7\Crypto\MediaType;
use Frista28\StreamCryptoPsr7\Stream\DecryptingStream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DecryptingStreamTest extends TestCase
{
    public function testProjectUsesExpectedNamespacePrefix(): void
    {
        $autoload = require __DIR__ . '/../vendor/autoload.php';

        self::assertInstanceOf(ClassLoader::class, $autoload);

        $prefixes = $autoload->getPrefixesPsr4();

        self::assertArrayHasKey('Frista28\\StreamCryptoPsr7\\', $prefixes);
    }

    #[DataProvider('sampleProvider')]
    public function testItDecryptsSampleMediaFiles(MediaType $type, string $samplePrefix): void
    {
        $stream = new DecryptingStream(
            $this->openSampleStream($samplePrefix . '.encrypted'),
            $this->readSampleFile($samplePrefix . '.key'),
            $type,
        );

        self::assertSame(
            $this->readSampleFile($samplePrefix . '.original'),
            (string) $stream,
        );
    }

    public function testItSupportsPartialReadsAndSeekAfterDecryption(): void
    {
        $stream = new DecryptingStream(
            $this->openSampleStream('IMAGE.encrypted'),
            $this->readSampleFile('IMAGE.key'),
            MediaType::IMAGE,
        );

        $firstChunk = $stream->read(128);

        self::assertSame(128, strlen($firstChunk));
        self::assertSame(128, $stream->tell());

        $stream->seek(64);

        self::assertSame(64, $stream->tell());
        self::assertSame(
            substr($this->readSampleFile('IMAGE.original'), 64, 32),
            $stream->read(32),
        );
    }

    public function testItIsReadOnly(): void
    {
        $stream = new DecryptingStream(
            $this->openSampleStream('AUDIO.encrypted'),
            $this->readSampleFile('AUDIO.key'),
            MediaType::AUDIO,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DecryptingStream is read-only');

        $stream->write('nope');
    }

    public function testItThrowsOnInvalidMediaKeyWhenReading(): void
    {
        $stream = new DecryptingStream(
            $this->openSampleStream('IMAGE.encrypted'),
            'short-key',
            MediaType::IMAGE,
        );

        $this->expectException(InvalidMediaKey::class);
        $stream->getContents();
    }

    public function testItThrowsOnInvalidMacWhenReading(): void
    {
        $encryptedPayload = $this->readSampleFile('IMAGE.encrypted');
        $tamperedPayload = substr($encryptedPayload, 0, -1) . ($encryptedPayload[-1] ^ "\x01");

        $stream = new DecryptingStream(
            Utils::streamFor($tamperedPayload),
            $this->readSampleFile('IMAGE.key'),
            MediaType::IMAGE,
        );

        $this->expectException(InvalidMac::class);
        $stream->getContents();
    }

    public function testItThrowsOnTooShortCiphertextWhenReading(): void
    {
        $stream = new DecryptingStream(
            Utils::streamFor('123456789'),
            $this->readSampleFile('IMAGE.key'),
            MediaType::IMAGE,
        );

        $this->expectException(InvalidCiphertext::class);
        $stream->getContents();
    }

    public function testToStringReturnsEmptyStringOnTransformFailure(): void
    {
        $stream = new DecryptingStream(
            $this->openSampleStream('IMAGE.encrypted'),
            'short-key',
            MediaType::IMAGE,
        );

        self::assertSame('', (string) $stream);
    }

    /**
     * @return iterable<string, array{type: MediaType, samplePrefix: string}>
     */
    public static function sampleProvider(): iterable
    {
        yield 'image' => [
            'type' => MediaType::IMAGE,
            'samplePrefix' => 'IMAGE',
        ];

        yield 'audio' => [
            'type' => MediaType::AUDIO,
            'samplePrefix' => 'AUDIO',
        ];

        yield 'video' => [
            'type' => MediaType::VIDEO,
            'samplePrefix' => 'VIDEO',
        ];
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
