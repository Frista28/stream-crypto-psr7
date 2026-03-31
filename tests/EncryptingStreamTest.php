<?php

declare(strict_types=1);

namespace Frista28\StreamCryptoPsr7\Tests;

use Frista28\StreamCryptoPsr7\Crypto\MediaType;
use Frista28\StreamCryptoPsr7\Stream\EncryptingStream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EncryptingStreamTest extends TestCase
{
    #[DataProvider('sampleProvider')]
    public function testItEncryptsSampleMediaFiles(MediaType $type, string $samplePrefix): void
    {
        $stream = new EncryptingStream(
            $this->openSampleStream($samplePrefix . '.original'),
            $this->readSampleFile($samplePrefix . '.key'),
            $type,
        );

        self::assertSame(
            $this->readSampleFile($samplePrefix . '.encrypted'),
            (string) $stream,
        );
    }

    public function testItSupportsPartialReadsAndSeekAfterEncryption(): void
    {
        $stream = new EncryptingStream(
            $this->openSampleStream('IMAGE.original'),
            $this->readSampleFile('IMAGE.key'),
            MediaType::IMAGE,
        );

        $firstChunk = $stream->read(128);

        self::assertSame(128, strlen($firstChunk));
        self::assertSame(128, $stream->tell());

        $stream->seek(64);

        self::assertSame(64, $stream->tell());
        self::assertSame(
            substr($this->readSampleFile('IMAGE.encrypted'), 64, 32),
            $stream->read(32),
        );
    }

    public function testItIsReadOnly(): void
    {
        $stream = new EncryptingStream(
            $this->openSampleStream('AUDIO.original'),
            $this->readSampleFile('AUDIO.key'),
            MediaType::AUDIO,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('EncryptingStream is read-only');

        $stream->write('nope');
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
