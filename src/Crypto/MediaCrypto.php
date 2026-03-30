<?php

declare(strict_types=1);

namespace Frista28\StreamCryptoPsr7\Crypto;

use Frista28\StreamCryptoPsr7\Crypto\Exception\CryptoOperationFailed;
use Frista28\StreamCryptoPsr7\Crypto\Exception\InvalidCiphertext;
use Frista28\StreamCryptoPsr7\Crypto\Exception\InvalidMac;
use Frista28\StreamCryptoPsr7\Crypto\Exception\InvalidMediaKey;

final class MediaCrypto
{
    private const IV_LENGTH = 16;
    private const CIPHER_KEY_LENGTH = 32;
    private const MAC_KEY_LENGTH = 32;
    private const REF_KEY_LENGTH = 32;
    private const MAC_LENGTH = 10;
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
        $keys = $this->expandKeys($mediaKey, $type);

        $encrypted = openssl_encrypt(
            $data,
            'aes-256-cbc',
            $keys['cipherKey'],
            OPENSSL_RAW_DATA,
            $keys['iv']
        );

        if ($encrypted === false) {
            throw new CryptoOperationFailed('AES encryption failed');
        }

        $mac = substr(
            hash_hmac('sha256', $keys['iv'] . $encrypted, $keys['macKey'], true),
            0,
            self::MAC_LENGTH
        );

        return $encrypted . $mac;
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
        if (strlen($data) < self::MAC_LENGTH) {
            throw new InvalidCiphertext('Encrypted payload must contain at least a MAC');
        }

        $keys = $this->expandKeys($mediaKey, $type);

        $payload = substr($data, 0, -self::MAC_LENGTH);
        $mac = substr($data, -self::MAC_LENGTH);

        $expectedMac = substr(
            hash_hmac('sha256', $keys['iv'] . $payload, $keys['macKey'], true),
            0,
            self::MAC_LENGTH
        );

        if (!hash_equals($expectedMac, $mac)) {
            throw new InvalidMac('MAC validation failed');
        }

        $decrypted = openssl_decrypt(
            $payload,
            'aes-256-cbc',
            $keys['cipherKey'],
            OPENSSL_RAW_DATA,
            $keys['iv']
        );

        if ($decrypted === false) {
            throw new CryptoOperationFailed('AES decryption failed');
        }

        return $decrypted;
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
}
