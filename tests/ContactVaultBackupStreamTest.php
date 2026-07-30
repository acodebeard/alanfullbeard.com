<?php

declare(strict_types=1);

require dirname(__DIR__) . '/deploy/contact-vault-backup-stream-lib.php';

function contact_vault_backup_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$masterKey = random_bytes(32);
$plaintext = str_repeat("CREATE TABLE test (id INT);\n", 6000)
    . "INSERT INTO test VALUES (1);\n";
$input = fopen('php://temp', 'w+b');
$encrypted = fopen('php://temp', 'w+b');
$decrypted = fopen('php://temp', 'w+b');

contact_vault_backup_assert(
    is_resource($input)
        && is_resource($encrypted)
        && is_resource($decrypted),
    'Could not create in-memory test streams.'
);

fwrite($input, $plaintext);
rewind($input);

$sourceHash = AlanFullbeard_Contact_Vault_Backup_Stream::encrypt(
    $input,
    $encrypted,
    $masterKey
);
rewind($encrypted);
$restoreHash = AlanFullbeard_Contact_Vault_Backup_Stream::decrypt(
    $encrypted,
    $decrypted,
    $masterKey
);
rewind($decrypted);
$restored = stream_get_contents($decrypted);

contact_vault_backup_assert(
    hash('sha256', $plaintext) === $sourceHash
        && $sourceHash === $restoreHash,
    'Backup and restore hashes should match the plaintext.'
);
contact_vault_backup_assert(
    $restored === $plaintext,
    'The encrypted database stream should round-trip without data loss.'
);

rewind($encrypted);
$tampered = stream_get_contents($encrypted);
contact_vault_backup_assert(
    is_string($tampered) && strlen($tampered) > 100,
    'The encrypted fixture is unexpectedly short.'
);
$tamperOffset = intdiv(strlen($tampered), 2);
$tampered[$tamperOffset] = chr(ord($tampered[$tamperOffset]) ^ 1);

$tamperedInput = fopen('php://temp', 'w+b');
$tamperedOutput = fopen('php://temp', 'w+b');
contact_vault_backup_assert(
    is_resource($tamperedInput) && is_resource($tamperedOutput),
    'Could not create tamper-test streams.'
);
fwrite($tamperedInput, $tampered);
rewind($tamperedInput);
$tamperingRejected = false;

try {
    AlanFullbeard_Contact_Vault_Backup_Stream::decrypt(
        $tamperedInput,
        $tamperedOutput,
        $masterKey
    );
} catch (RuntimeException) {
    $tamperingRejected = true;
}

contact_vault_backup_assert(
    $tamperingRejected,
    'Authenticated backup decryption must reject modified ciphertext.'
);

echo "All encrypted database-backup stream tests passed.\n";
