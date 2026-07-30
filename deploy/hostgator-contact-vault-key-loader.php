<?php
/**
 * Plugin Name: Alan Fullbeard Contact Vault Key Loader
 * Description: Loads the contact-vault key from outside the public webroot.
 * Author: @acodebeard
 * Version: 1.0.0
 */

declare(strict_types=1);

$alanfullbeardContactVaultKeyFile =
    '/home2/afullbeard/private-config/contact-vault-key.php';

if (is_readable($alanfullbeardContactVaultKeyFile)) {
    require_once $alanfullbeardContactVaultKeyFile;
}
