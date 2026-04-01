<?php

declare(strict_types=1);

namespace Frista28\StreamCryptoPsr7\Crypto;

enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';

    public function isStreamable(): bool
    {
        return match ($this) {
            self::VIDEO, self::AUDIO => true,
            self::IMAGE, self::DOCUMENT => false,
        };
    }

    public function hkdfInfo(): string
    {
        return match ($this) {
            self::IMAGE => 'WhatsApp Image Keys',
            self::VIDEO => 'WhatsApp Video Keys',
            self::AUDIO => 'WhatsApp Audio Keys',
            self::DOCUMENT => 'WhatsApp Document Keys',
        };
    }
}
