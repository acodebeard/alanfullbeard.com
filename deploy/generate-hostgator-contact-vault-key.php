<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This key generator may only run from the command line.\n");
    exit(1);
}

if ($argc !== 2 || ! is_string($argv[1]) || $argv[1] === '') {
    fwrite(STDERR, "Usage: php generate-hostgator-contact-vault-key.php KEY_FILE\n");
    exit(1);
}

$path = $argv[1];
$encoded = base64_encode(random_bytes(32));
$contents = "<?php\n\ndeclare(strict_types=1);\n\ndefine(" .
    "\"ALANFULLBEARD_CONTACT_VAULT_KEY\", " .
    var_export($encoded, true) .
    ");\n";

$handle = @fopen($path, 'x');

if (! is_resource($handle)) {
    fwrite(STDERR, "Could not exclusively create the contact-vault key file.\n");
    exit(1);
}

$written = fwrite($handle, $contents);
$flushed = fflush($handle);
$protected = chmod($path, 0600);
$closed = fclose($handle);

if (
    $written !== strlen($contents) ||
    ! $flushed ||
    ! $protected ||
    ! $closed
) {
    fwrite(STDERR, "Could not safely write and protect the contact-vault key file.\n");
    exit(1);
}

fwrite(STDOUT, "Created protected contact-vault key file.\n");
