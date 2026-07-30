<?php

declare(strict_types=1);

require __DIR__ . '/contact-vault-backup-stream-lib.php';

if (PHP_SAPI !== 'cli' || ! isset($argv[1])) {
    fwrite(STDERR, "Usage: php contact-vault-encrypt-backup.php KEY_FILE\n");
    exit(2);
}

try {
    $masterKey = AlanFullbeard_Contact_Vault_Backup_Stream::keyFromConfiguredFile(
        $argv[1]
    );
    $hash = AlanFullbeard_Contact_Vault_Backup_Stream::encrypt(
        STDIN,
        STDOUT,
        $masterKey
    );
    fwrite(STDERR, "plaintext_sha256={$hash}\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Backup encryption failed: {$error->getMessage()}\n");
    exit(1);
}
