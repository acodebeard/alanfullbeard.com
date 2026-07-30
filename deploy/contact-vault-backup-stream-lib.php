<?php

declare(strict_types=1);

final class AlanFullbeard_Contact_Vault_Backup_Stream
{
    private const MAGIC = "AFBDBV1\n";
    private const CONTEXT = 'alanfullbeard-contact-vault:database-backup:v1';
    private const CHUNK_BYTES = 65536;
    private const KEY_BYTES = 32;

    /**
     * @param resource $input
     * @param resource $output
     */
    public static function encrypt($input, $output, string $masterKey): string
    {
        self::assertRuntime($input, $output, $masterKey);

        $key = self::derivedKey($masterKey);
        [$state, $header] =
            sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $hash = hash_init('sha256');

        self::writeAll($output, self::MAGIC);
        self::writeAll($output, $header);

        do {
            $chunk = fread($input, self::CHUNK_BYTES);

            if (false === $chunk) {
                throw new RuntimeException('Could not read the database stream.');
            }

            hash_update($hash, $chunk);
            $isFinal = feof($input);
            $tag = $isFinal
                ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            $ciphertext =
                sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state,
                    $chunk,
                    '',
                    $tag
                );

            self::writeAll($output, pack('N', strlen($ciphertext)));
            self::writeAll($output, $ciphertext);
        } while (! $isFinal);

        sodium_memzero($key);
        sodium_memzero($masterKey);

        return hash_final($hash);
    }

    /**
     * @param resource $input
     * @param resource $output
     */
    public static function decrypt($input, $output, string $masterKey): string
    {
        self::assertRuntime($input, $output, $masterKey);

        $magic = self::readExact($input, strlen(self::MAGIC));

        if ($magic !== self::MAGIC) {
            throw new RuntimeException('Unknown encrypted database-backup format.');
        }

        $key = self::derivedKey($masterKey);
        $header = self::readExact(
            $input,
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES
        );
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(
            $header,
            $key
        );
        $hash = hash_init('sha256');
        $sawFinal = false;

        while (! feof($input)) {
            $frameHeader = fread($input, 4);

            if (false === $frameHeader) {
                throw new RuntimeException('Could not read a backup frame.');
            }

            if ($frameHeader === '') {
                break;
            }

            if (strlen($frameHeader) !== 4) {
                throw new RuntimeException('The encrypted backup is truncated.');
            }

            $lengthData = unpack('Nlength', $frameHeader);
            $frameBytes = is_array($lengthData)
                ? (int) ($lengthData['length'] ?? 0)
                : 0;
            $maximumFrameBytes = self::CHUNK_BYTES
                + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

            if ($frameBytes < 1 || $frameBytes > $maximumFrameBytes) {
                throw new RuntimeException(
                    'The encrypted backup has an invalid frame length.'
                );
            }

            $ciphertext = self::readExact($input, $frameBytes);
            $result = sodium_crypto_secretstream_xchacha20poly1305_pull(
                $state,
                $ciphertext
            );

            if (false === $result) {
                throw new RuntimeException(
                    'The encrypted backup failed authentication.'
                );
            }

            [$plaintext, $tag] = $result;
            hash_update($hash, $plaintext);
            self::writeAll($output, $plaintext);

            if (
                $tag
                === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
            ) {
                $sawFinal = true;

                if (fread($input, 1) !== '') {
                    throw new RuntimeException(
                        'The encrypted backup contains data after its final frame.'
                    );
                }

                break;
            }
        }

        sodium_memzero($key);
        sodium_memzero($masterKey);

        if (! $sawFinal) {
            throw new RuntimeException(
                'The encrypted backup has no authenticated final frame.'
            );
        }

        return hash_final($hash);
    }

    public static function keyFromConfiguredFile(string $keyFile): string
    {
        if (! is_readable($keyFile)) {
            throw new RuntimeException('The contact-vault key file is unreadable.');
        }

        require $keyFile;

        if (! defined('ALANFULLBEARD_CONTACT_VAULT_KEY')) {
            throw new RuntimeException(
                'The contact-vault key constant is not configured.'
            );
        }

        $encoded = constant('ALANFULLBEARD_CONTACT_VAULT_KEY');
        $decoded = is_string($encoded)
            ? base64_decode($encoded, true)
            : false;

        if (! is_string($decoded) || strlen($decoded) !== self::KEY_BYTES) {
            throw new RuntimeException(
                'The contact-vault key must be 32 base64-encoded bytes.'
            );
        }

        return $decoded;
    }

    /**
     * @param resource $input
     * @param resource $output
     */
    private static function assertRuntime(
        $input,
        $output,
        string $masterKey
    ): void {
        if (
            ! is_resource($input)
            || ! is_resource($output)
            || strlen($masterKey) !== self::KEY_BYTES
            || ! function_exists(
                'sodium_crypto_secretstream_xchacha20poly1305_init_push'
            )
        ) {
            throw new RuntimeException(
                'Native Sodium secretstream and a 256-bit key are required.'
            );
        }
    }

    private static function derivedKey(string $masterKey): string
    {
        return sodium_crypto_generichash(
            self::CONTEXT,
            $masterKey,
            self::KEY_BYTES
        );
    }

    /**
     * @param resource $stream
     */
    private static function writeAll($stream, string $bytes): void
    {
        $offset = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $written = fwrite($stream, substr($bytes, $offset));

            if (false === $written || $written < 1) {
                throw new RuntimeException(
                    'Could not write the encrypted backup stream.'
                );
            }

            $offset += $written;
        }
    }

    /**
     * @param resource $stream
     */
    private static function readExact($stream, int $bytes): string
    {
        $result = '';

        while (strlen($result) < $bytes) {
            $chunk = fread($stream, $bytes - strlen($result));

            if (false === $chunk || $chunk === '') {
                throw new RuntimeException('The encrypted backup is truncated.');
            }

            $result .= $chunk;
        }

        return $result;
    }
}
