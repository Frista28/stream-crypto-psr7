<?php

declare(strict_types=1);

namespace Frista28\StreamCryptoPsr7\Crypto;

use Frista28\StreamCryptoPsr7\Crypto\Exception\CryptoOperationFailed;
use Frista28\StreamCryptoPsr7\Crypto\Exception\InvalidCiphertext;
use Frista28\StreamCryptoPsr7\Crypto\Exception\InvalidMac;
use Frista28\StreamCryptoPsr7\Crypto\Exception\InvalidMediaKey;
use GuzzleHttp\Psr7\Utils;
use HashContext;
use Psr\Http\Message\StreamInterface;

final class MediaCrypto
{
    private const BLOCK_SIZE = 16;
    private const IV_LENGTH = 16;
    private const CIPHER_KEY_LENGTH = 32;
    private const MAC_KEY_LENGTH = 32;
    private const REF_KEY_LENGTH = 32;
    private const MAC_LENGTH = 10;
    private const STREAM_CHUNK_SIZE = 65536;
    private const HKDF_LENGTH = self::IV_LENGTH
        + self::CIPHER_KEY_LENGTH
        + self::MAC_KEY_LENGTH
        + self::REF_KEY_LENGTH;

    /**
     * Encrypts a raw binary media payload using the WhatsApp media algorithm.
     *
     * @param string $data Raw binary payload.
     * @param string $mediaKey Raw 32-byte media key.
     *
     * @return string Encrypted binary payload with MAC suffix.
     */
    public function encrypt(string $data, string $mediaKey, MediaType $type): string
    {
        $stream = $this->encryptStream(Utils::streamFor($data), $mediaKey, $type);

        return Utils::copyToString($stream);
    }

    /**
     * Decrypts a raw binary media payload using the WhatsApp media algorithm.
     *
     * @param string $data Encrypted binary payload with MAC suffix.
     * @param string $mediaKey Raw 32-byte media key.
     *
     * @return string Decrypted raw binary payload.
     */
    public function decrypt(string $data, string $mediaKey, MediaType $type): string
    {
        $stream = $this->decryptStream(Utils::streamFor($data), $mediaKey, $type);

        return Utils::copyToString($stream);
    }

    /**
     * Encrypts source stream in chunks and returns encrypted stream with MAC suffix.
     *
     * @param string $mediaKey Raw 32-byte media key.
     * @param int $chunkSize Source read chunk size in bytes.
     *
     * @return StreamInterface Seekable encrypted stream positioned at the beginning.
     */
    public function encryptStream(
        StreamInterface $plainStream,
        string $mediaKey,
        MediaType $type,
        int $chunkSize = self::STREAM_CHUNK_SIZE,
    ): StreamInterface {
        $this->assertChunkSize($chunkSize);
        [
            'keys' => $keys,
            'outputStream' => $encryptedStream,
            'macContext' => $macContext,
            'iv' => $iv,
            'buffer' => $buffer,
        ] = $this->initializeStreamContext($mediaKey, $type);

        try {
            while (true) {
                $chunk = $plainStream->read($chunkSize);

                if ($chunk === '') {
                    if ($plainStream->eof()) {
                        break;
                    }

                    throw new CryptoOperationFailed('Source stream returned an empty chunk before EOF');
                }

                $buffer .= $chunk;
                $processableLength = strlen($buffer) - (strlen($buffer) % self::BLOCK_SIZE);

                if ($processableLength === 0) {
                    continue;
                }

                $plainChunk = substr($buffer, 0, $processableLength);
                $buffer = substr($buffer, $processableLength);

                $encryptedChunk = $this->encryptBlocksWithoutPadding($plainChunk, $keys['cipherKey'], $iv);
                hash_update($macContext, $encryptedChunk);
                $this->writeAll($encryptedStream, $encryptedChunk);
                $iv = substr($encryptedChunk, -self::BLOCK_SIZE);
            }

            $padLength = self::BLOCK_SIZE - (strlen($buffer) % self::BLOCK_SIZE);
            $finalPlainChunk = $buffer . str_repeat(chr($padLength), $padLength);
            $finalEncryptedChunk = $this->encryptBlocksWithoutPadding($finalPlainChunk, $keys['cipherKey'], $iv);
            hash_update($macContext, $finalEncryptedChunk);
            $this->writeAll($encryptedStream, $finalEncryptedChunk);

            $mac = substr(hash_final($macContext, true), 0, self::MAC_LENGTH);
            $this->writeAll($encryptedStream, $mac);

            $encryptedStream->rewind();

            return $encryptedStream;
        } catch (\Throwable $exception) {
            $encryptedStream->close();

            throw $exception;
        }
    }

    /**
     * Decrypts source stream in chunks after validating MAC.
     *
     * @param string $mediaKey Raw 32-byte media key.
     * @param int $chunkSize Source read chunk size in bytes.
     *
     * @return StreamInterface Seekable decrypted stream positioned at the beginning.
     */
    public function decryptStream(
        StreamInterface $encryptedStream,
        string $mediaKey,
        MediaType $type,
        int $chunkSize = self::STREAM_CHUNK_SIZE,
    ): StreamInterface {
        $this->assertChunkSize($chunkSize);
        [
            'keys' => $keys,
            'outputStream' => $decryptedStream,
            'macContext' => $macContext,
            'iv' => $iv,
            'buffer' => $buffer,
        ] = $this->initializeStreamContext($mediaKey, $type);

        try {
            while (true) {
                $chunk = $encryptedStream->read($chunkSize);

                if ($chunk === '') {
                    if ($encryptedStream->eof()) {
                        break;
                    }

                    throw new CryptoOperationFailed('Encrypted stream returned an empty chunk before EOF');
                }

                $buffer .= $chunk;
                $processableLength = strlen($buffer) - self::MAC_LENGTH - self::BLOCK_SIZE;

                if ($processableLength <= 0) {
                    continue;
                }

                $processableLength -= $processableLength % self::BLOCK_SIZE;
                if ($processableLength === 0) {
                    continue;
                }

                $encryptedChunk = substr($buffer, 0, $processableLength);
                $buffer = substr($buffer, $processableLength);

                hash_update($macContext, $encryptedChunk);
                $decryptedChunk = $this->decryptBlocksWithoutPadding($encryptedChunk, $keys['cipherKey'], $iv);
                $this->writeAll($decryptedStream, $decryptedChunk);
                $iv = substr($encryptedChunk, -self::BLOCK_SIZE);
            }

            if (strlen($buffer) < self::MAC_LENGTH + self::BLOCK_SIZE) {
                throw new InvalidCiphertext('Encrypted payload must contain at least one block and MAC');
            }

            $mac = substr($buffer, -self::MAC_LENGTH);
            $finalEncryptedChunk = substr($buffer, 0, -self::MAC_LENGTH);

            if ($finalEncryptedChunk === '' || strlen($finalEncryptedChunk) % self::BLOCK_SIZE !== 0) {
                throw new InvalidCiphertext('Encrypted payload has invalid block alignment');
            }

            hash_update($macContext, $finalEncryptedChunk);
            $expectedMac = substr(hash_final($macContext, true), 0, self::MAC_LENGTH);

            if (!hash_equals($expectedMac, $mac)) {
                throw new InvalidMac('MAC validation failed');
            }

            $finalDecryptedChunk = $this->decryptBlocksWithoutPadding($finalEncryptedChunk, $keys['cipherKey'], $iv);
            $unpaddedFinalChunk = $this->pkcs7Unpad($finalDecryptedChunk);
            $this->writeAll($decryptedStream, $unpaddedFinalChunk);

            $decryptedStream->rewind();

            return $decryptedStream;
        } catch (\Throwable $exception) {
            $decryptedStream->close();

            throw $exception;
        }
    }

    /**
     * Expands a raw media key into the WhatsApp media crypto key set.
     *
     * @param string $mediaKey Raw 32-byte media key.
     *
     * @return array{iv: string, cipherKey: string, macKey: string}
     */
    private function expandKeys(string $mediaKey, MediaType $type): array
    {
        if (strlen($mediaKey) !== self::CIPHER_KEY_LENGTH) {
            throw new InvalidMediaKey('Media key must be exactly 32 raw bytes');
        }

        $info = $type->hkdfInfo();

        $expanded = hash_hkdf(
            'sha256',
            $mediaKey,
            self::HKDF_LENGTH,
            $info,
            ''
        );

        if (strlen($expanded) !== self::HKDF_LENGTH) {
            throw new CryptoOperationFailed('HKDF expansion failed');
        }

        return [
            'iv' => substr($expanded, 0, self::IV_LENGTH),
            'cipherKey' => substr($expanded, self::IV_LENGTH, self::CIPHER_KEY_LENGTH),
            'macKey' => substr(
                $expanded,
                self::IV_LENGTH + self::CIPHER_KEY_LENGTH,
                self::MAC_KEY_LENGTH
            ),
        ];
    }

    private function encryptBlocksWithoutPadding(string $plainChunk, string $cipherKey, string $iv): string
    {
        if ($plainChunk === '' || strlen($plainChunk) % self::BLOCK_SIZE !== 0) {
            throw new CryptoOperationFailed('Plain chunk must be block-aligned');
        }

        $encrypted = openssl_encrypt(
            $plainChunk,
            'aes-256-cbc',
            $cipherKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );

        if ($encrypted === false) {
            throw new CryptoOperationFailed('AES encryption failed');
        }

        return $encrypted;
    }

    private function decryptBlocksWithoutPadding(string $encryptedChunk, string $cipherKey, string $iv): string
    {
        if ($encryptedChunk === '' || strlen($encryptedChunk) % self::BLOCK_SIZE !== 0) {
            throw new InvalidCiphertext('Encrypted chunk must be block-aligned');
        }

        $decrypted = openssl_decrypt(
            $encryptedChunk,
            'aes-256-cbc',
            $cipherKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );

        if ($decrypted === false) {
            throw new CryptoOperationFailed('AES decryption failed');
        }

        return $decrypted;
    }

    /**
     * Removes and validates PKCS#7 padding from the final decrypted block sequence.
     */
    private function pkcs7Unpad(string $payload): string
    {
        if ($payload === '' || strlen($payload) % self::BLOCK_SIZE !== 0) {
            throw new InvalidCiphertext('Decrypted payload has invalid block alignment');
        }

        $paddingLength = ord($payload[-1]);

        if ($paddingLength < 1 || $paddingLength > self::BLOCK_SIZE) {
            throw new InvalidCiphertext('Invalid PKCS#7 padding length');
        }

        $padding = substr($payload, -$paddingLength);
        if ($padding !== str_repeat(chr($paddingLength), $paddingLength)) {
            throw new InvalidCiphertext('Invalid PKCS#7 padding bytes');
        }

        return substr($payload, 0, -$paddingLength);
    }

    /**
     * Creates an in-memory temporary stream used to materialize transformed output.
     */
    private function createTempStream(): StreamInterface
    {
        $resource = fopen('php://temp', 'r+b');
        if ($resource === false) {
            throw new CryptoOperationFailed('Failed to create temporary stream');
        }

        return Utils::streamFor($resource);
    }

    /**
     * Writes the full payload chunk to the destination stream.
     */
    private function writeAll(StreamInterface $stream, string $payload): void
    {
        if ($payload === '') {
            return;
        }

        $written = $stream->write($payload);
        if ($written !== strlen($payload)) {
            throw new CryptoOperationFailed('Failed to write full payload chunk');
        }
    }

    /**
     * Guards public stream APIs against invalid chunk sizes.
     */
    private function assertChunkSize(int $chunkSize): void
    {
        if ($chunkSize <= 0) {
            throw new CryptoOperationFailed('Chunk size must be greater than zero');
        }
    }

    /**
     * @return array{
     *     keys: array{iv: string, cipherKey: string, macKey: string},
     *     outputStream: StreamInterface,
     *     macContext: HashContext,
     *     iv: string,
     *     buffer: string
     * }
     */
    private function initializeStreamContext(string $mediaKey, MediaType $type): array
    {
        $keys = $this->expandKeys($mediaKey, $type);
        $macContext = hash_init('sha256', HASH_HMAC, $keys['macKey']);
        hash_update($macContext, $keys['iv']);

        return [
            'keys' => $keys,
            'outputStream' => $this->createTempStream(),
            'macContext' => $macContext,
            'iv' => $keys['iv'],
            'buffer' => '',
        ];
    }
}
