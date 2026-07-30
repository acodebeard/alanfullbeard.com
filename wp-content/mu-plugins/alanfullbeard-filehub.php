<?php
/**
 * Plugin Name: Alan Fullbeard File Hub
 * Description: Provides administrator-only access to private files stored outside the public webroot.
 * Author: @acodebeard
 * Version: 1.0.0
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('ALANFULLBEARD_FILEHUB_ROOT')) {
    define('ALANFULLBEARD_FILEHUB_ROOT', '/home/afullbeard/filehub_data');
}

if (! defined('ALANFULLBEARD_FILEHUB_CAPABILITY')) {
    define('ALANFULLBEARD_FILEHUB_CAPABILITY', 'manage_options');
}

if (! defined('ALANFULLBEARD_FILEHUB_MAX_UPLOAD_BYTES')) {
    define('ALANFULLBEARD_FILEHUB_MAX_UPLOAD_BYTES', 10 * 1024 * 1024);
}

if (! function_exists('alanfullbeard_filehub_allowed_extensions')) {
    /**
     * @return list<string>
     */
    function alanfullbeard_filehub_allowed_extensions(): array
    {
        return [
            'txt',
            'pdf',
            'png',
            'jpg',
            'jpeg',
            'webp',
            'zip',
            '7z',
            'gz',
            'tar',
            'xz',
            'mp4',
            'mov',
            'mp3',
            'wav',
            'csv',
            'json',
            'docx',
            'xlsx',
            'pptx',
        ];
    }
}

if (! function_exists('alanfullbeard_filehub_capability')) {
    function alanfullbeard_filehub_capability(): string
    {
        $capability = ALANFULLBEARD_FILEHUB_CAPABILITY;
        return is_string($capability) && '' !== $capability ? $capability : 'manage_options';
    }
}

if (! function_exists('alanfullbeard_filehub_require_admin')) {
    function alanfullbeard_filehub_require_admin(): void
    {
        if (! is_user_logged_in() || ! current_user_can(alanfullbeard_filehub_capability())) {
            wp_die(
                esc_html__('You are not allowed to access File Hub.', 'alanfullbeard'),
                esc_html__('Access denied', 'alanfullbeard'),
                ['response' => 403]
            );
        }
    }
}

if (! function_exists('alanfullbeard_filehub_root_key')) {
    function alanfullbeard_filehub_root_key(mixed $value): string
    {
        return is_string($value) && 'favorites' === $value ? 'favorites' : 'storage';
    }
}

if (! function_exists('alanfullbeard_filehub_base_path')) {
    function alanfullbeard_filehub_base_path(): string
    {
        $configuredPath = ALANFULLBEARD_FILEHUB_ROOT;
        return is_string($configuredPath) ? rtrim($configuredPath, '/') : '';
    }
}

if (! function_exists('alanfullbeard_filehub_directory')) {
    function alanfullbeard_filehub_directory(string $root): string
    {
        return alanfullbeard_filehub_base_path() . '/' . alanfullbeard_filehub_root_key($root);
    }
}

if (! function_exists('alanfullbeard_filehub_prepare_directories')) {
    function alanfullbeard_filehub_prepare_directories(): bool
    {
        $basePath = alanfullbeard_filehub_base_path();
        if ('' === $basePath) {
            return false;
        }

        $directories = [
            $basePath,
            $basePath . '/storage',
            $basePath . '/favorites',
            $basePath . '/tmp',
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                return false;
            }

            if (! chmod($directory, 0700)) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('alanfullbeard_filehub_input_string')) {
    /**
     * @param array<mixed> $source
     */
    function alanfullbeard_filehub_input_string(array $source, string $key): string
    {
        $value = $source[$key] ?? '';
        return is_string($value) ? wp_unslash($value) : '';
    }
}

if (! function_exists('alanfullbeard_filehub_valid_name')) {
    function alanfullbeard_filehub_valid_name(string $name): string
    {
        $cleanName = sanitize_file_name($name);
        if ('' === $cleanName || '.' === $cleanName || '..' === $cleanName) {
            return '';
        }

        $extension = strtolower((string) pathinfo($cleanName, PATHINFO_EXTENSION));
        if (! in_array($extension, alanfullbeard_filehub_allowed_extensions(), true)) {
            return '';
        }

        return $cleanName;
    }
}

if (! function_exists('alanfullbeard_filehub_existing_file')) {
    function alanfullbeard_filehub_existing_file(string $directory, string $requestedName): ?string
    {
        $name = basename($requestedName);
        if ('' === $name || '.' === $name || '..' === $name || $name !== $requestedName) {
            return null;
        }

        $directoryPath = realpath($directory);
        $filePath = realpath($directory . '/' . $name);

        if (
            false === $directoryPath
            || false === $filePath
            || dirname($filePath) !== $directoryPath
            || ! is_file($filePath)
            || is_link($filePath)
        ) {
            return null;
        }

        return $filePath;
    }
}

if (! function_exists('alanfullbeard_filehub_files')) {
    /**
     * @return list<array{name: string, size: int, modified: int}>
     */
    function alanfullbeard_filehub_files(string $directory): array
    {
        $files = [];
        $entries = is_dir($directory) ? scandir($directory) : false;

        if (! is_array($entries)) {
            return $files;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = alanfullbeard_filehub_existing_file($directory, $entry);
            if (null === $path) {
                continue;
            }

            $size = filesize($path);
            $modified = filemtime($path);
            if (! is_int($size) || ! is_int($modified)) {
                continue;
            }

            $files[] = [
                'name' => $entry,
                'size' => $size,
                'modified' => $modified,
            ];
        }

        usort(
            $files,
            static fn (array $left, array $right): int => $right['modified'] <=> $left['modified']
        );

        return $files;
    }
}

if (! function_exists('alanfullbeard_filehub_bytes')) {
    function alanfullbeard_filehub_bytes(int $bytes): string
    {
        return size_format($bytes, 1);
    }
}

if (! function_exists('alanfullbeard_filehub_url')) {
    /**
     * @param array<string, string> $arguments
     */
    function alanfullbeard_filehub_url(array $arguments = []): string
    {
        return add_query_arg(
            array_merge(['page' => 'alanfullbeard-filehub'], $arguments),
            admin_url('tools.php')
        );
    }
}

if (! function_exists('alanfullbeard_filehub_redirect')) {
    function alanfullbeard_filehub_redirect(string $root, string $notice): never
    {
        wp_safe_redirect(
            alanfullbeard_filehub_url([
                'root' => alanfullbeard_filehub_root_key($root),
                'filehub_notice' => $notice,
            ])
        );
        exit;
    }
}

if (! function_exists('alanfullbeard_filehub_notice')) {
    function alanfullbeard_filehub_notice(): void
    {
        $noticeCode = alanfullbeard_filehub_input_string($_GET, 'filehub_notice');
        $notices = [
            'uploaded' => ['success', __('File uploaded.', 'alanfullbeard')],
            'renamed' => ['success', __('File renamed.', 'alanfullbeard')],
            'moved' => ['success', __('File moved.', 'alanfullbeard')],
            'deleted' => ['success', __('File deleted.', 'alanfullbeard')],
            'invalid-file' => ['error', __('That file is not available.', 'alanfullbeard')],
            'invalid-name' => ['error', __('Use an allowed filename and extension.', 'alanfullbeard')],
            'file-exists' => ['error', __('A file with that name already exists.', 'alanfullbeard')],
            'upload-failed' => ['error', __('The upload could not be completed.', 'alanfullbeard')],
            'file-too-large' => ['error', __('The selected file is too large.', 'alanfullbeard')],
            'storage-unavailable' => ['error', __('Private storage is unavailable.', 'alanfullbeard')],
            'operation-failed' => ['error', __('The file operation could not be completed.', 'alanfullbeard')],
        ];

        if (! isset($notices[$noticeCode])) {
            return;
        }

        [$type, $message] = $notices[$noticeCode];
        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }
}

if (! function_exists('alanfullbeard_filehub_render_page')) {
    function alanfullbeard_filehub_render_page(): void
    {
        alanfullbeard_filehub_require_admin();

        $root = alanfullbeard_filehub_root_key($_GET['root'] ?? 'storage');
        $storageReady = alanfullbeard_filehub_prepare_directories();
        $files = $storageReady
            ? alanfullbeard_filehub_files(alanfullbeard_filehub_directory($root))
            : [];
        $effectiveMaxUpload = min(
            (int) ALANFULLBEARD_FILEHUB_MAX_UPLOAD_BYTES,
            (int) wp_max_upload_size()
        );
        ?>
        <div class="wrap alanfullbeard-filehub">
            <h1><?php esc_html_e('File Hub', 'alanfullbeard'); ?></h1>
            <p><?php esc_html_e('Private files are stored outside the website and are available only to administrators.', 'alanfullbeard'); ?></p>

            <?php alanfullbeard_filehub_notice(); ?>

            <?php if (! $storageReady) : ?>
                <div class="notice notice-error"><p><?php esc_html_e('Private storage is unavailable.', 'alanfullbeard'); ?></p></div>
            <?php else : ?>
                <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('File groups', 'alanfullbeard'); ?>">
                    <a class="nav-tab <?php echo 'storage' === $root ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(alanfullbeard_filehub_url(['root' => 'storage'])); ?>">
                        <?php esc_html_e('Storage', 'alanfullbeard'); ?>
                    </a>
                    <a class="nav-tab <?php echo 'favorites' === $root ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(alanfullbeard_filehub_url(['root' => 'favorites'])); ?>">
                        <?php esc_html_e('Favorites', 'alanfullbeard'); ?>
                    </a>
                </nav>

                <form class="alanfullbeard-filehub__upload" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="alanfullbeard_filehub_upload">
                    <input type="hidden" name="root" value="<?php echo esc_attr($root); ?>">
                    <?php wp_nonce_field('alanfullbeard_filehub_upload'); ?>
                    <label for="alanfullbeard-filehub-upload"><?php esc_html_e('Upload a file', 'alanfullbeard'); ?></label>
                    <input id="alanfullbeard-filehub-upload" type="file" name="file" required>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Upload', 'alanfullbeard'); ?></button>
                    <p class="description">
                        <?php
                        printf(
                            esc_html__('Maximum upload size: %s.', 'alanfullbeard'),
                            esc_html(alanfullbeard_filehub_bytes($effectiveMaxUpload))
                        );
                        ?>
                    </p>
                </form>

                <table class="widefat striped alanfullbeard-filehub__table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('File', 'alanfullbeard'); ?></th>
                            <th scope="col"><?php esc_html_e('Size', 'alanfullbeard'); ?></th>
                            <th scope="col"><?php esc_html_e('Modified', 'alanfullbeard'); ?></th>
                            <th scope="col"><?php esc_html_e('Actions', 'alanfullbeard'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ([] === $files) : ?>
                            <tr><td colspan="4"><?php esc_html_e('No files.', 'alanfullbeard'); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ($files as $file) : ?>
                                <?php
                                $name = $file['name'];
                                $downloadUrl = wp_nonce_url(
                                    add_query_arg(
                                        [
                                            'action' => 'alanfullbeard_filehub_download',
                                            'root' => $root,
                                            'file' => $name,
                                        ],
                                        admin_url('admin-post.php')
                                    ),
                                    'alanfullbeard_filehub_download_' . $root . '_' . $name
                                );
                                ?>
                                <tr>
                                    <td data-label="<?php esc_attr_e('File', 'alanfullbeard'); ?>"><code><?php echo esc_html($name); ?></code></td>
                                    <td data-label="<?php esc_attr_e('Size', 'alanfullbeard'); ?>"><?php echo esc_html(alanfullbeard_filehub_bytes($file['size'])); ?></td>
                                    <td data-label="<?php esc_attr_e('Modified', 'alanfullbeard'); ?>"><?php echo esc_html(wp_date('Y-m-d H:i', $file['modified'])); ?></td>
                                    <td data-label="<?php esc_attr_e('Actions', 'alanfullbeard'); ?>">
                                        <div class="alanfullbeard-filehub__actions">
                                            <a class="button" href="<?php echo esc_url($downloadUrl); ?>"><?php esc_html_e('Download', 'alanfullbeard'); ?></a>

                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                <input type="hidden" name="action" value="alanfullbeard_filehub_rename">
                                                <input type="hidden" name="root" value="<?php echo esc_attr($root); ?>">
                                                <input type="hidden" name="old_name" value="<?php echo esc_attr($name); ?>">
                                                <?php wp_nonce_field('alanfullbeard_filehub_rename_' . $root . '_' . $name); ?>
                                                <label class="screen-reader-text" for="rename-<?php echo esc_attr(md5($root . $name)); ?>"><?php esc_html_e('New filename', 'alanfullbeard'); ?></label>
                                                <input id="rename-<?php echo esc_attr(md5($root . $name)); ?>" type="text" name="new_name" value="<?php echo esc_attr($name); ?>" required>
                                                <button class="button" type="submit"><?php esc_html_e('Rename', 'alanfullbeard'); ?></button>
                                            </form>

                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                <input type="hidden" name="action" value="alanfullbeard_filehub_move">
                                                <input type="hidden" name="root" value="<?php echo esc_attr($root); ?>">
                                                <input type="hidden" name="file" value="<?php echo esc_attr($name); ?>">
                                                <?php wp_nonce_field('alanfullbeard_filehub_move_' . $root . '_' . $name); ?>
                                                <button class="button" type="submit">
                                                    <?php echo 'favorites' === $root ? esc_html__('Move to Storage', 'alanfullbeard') : esc_html__('Move to Favorites', 'alanfullbeard'); ?>
                                                </button>
                                            </form>

                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this file permanently?', 'alanfullbeard')); ?>')">
                                                <input type="hidden" name="action" value="alanfullbeard_filehub_delete">
                                                <input type="hidden" name="root" value="<?php echo esc_attr($root); ?>">
                                                <input type="hidden" name="file" value="<?php echo esc_attr($name); ?>">
                                                <?php wp_nonce_field('alanfullbeard_filehub_delete_' . $root . '_' . $name); ?>
                                                <button class="button button-link-delete" type="submit"><?php esc_html_e('Delete', 'alanfullbeard'); ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (! function_exists('alanfullbeard_filehub_menu')) {
    function alanfullbeard_filehub_menu(): void
    {
        add_management_page(
            __('File Hub', 'alanfullbeard'),
            __('File Hub', 'alanfullbeard'),
            alanfullbeard_filehub_capability(),
            'alanfullbeard-filehub',
            'alanfullbeard_filehub_render_page'
        );
    }
}
add_action('admin_menu', 'alanfullbeard_filehub_menu');

if (! function_exists('alanfullbeard_filehub_admin_styles')) {
    function alanfullbeard_filehub_admin_styles(string $hookSuffix): void
    {
        if ('tools_page_alanfullbeard-filehub' !== $hookSuffix) {
            return;
        }

        wp_add_inline_style(
            'common',
            '.alanfullbeard-filehub__upload{margin:1.5rem 0;padding:1rem;background:#fff;border:1px solid #c3c4c7}'
            . '.alanfullbeard-filehub__upload label{display:block;font-weight:600;margin-bottom:.5rem}'
            . '.alanfullbeard-filehub__actions{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}'
            . '.alanfullbeard-filehub__actions form{display:flex;gap:.35rem;align-items:center;margin:0}'
            . '.alanfullbeard-filehub__actions input[type=text]{max-width:16rem}'
            . '@media(max-width:782px){.alanfullbeard-filehub__table thead{display:none}'
            . '.alanfullbeard-filehub__table tr,.alanfullbeard-filehub__table td{display:block}'
            . '.alanfullbeard-filehub__table td::before{content:attr(data-label);display:block;font-weight:600;margin-bottom:.25rem}'
            . '.alanfullbeard-filehub__actions,.alanfullbeard-filehub__actions form{align-items:stretch;flex-direction:column}'
            . '.alanfullbeard-filehub__actions .button,.alanfullbeard-filehub__actions input[type=text]{width:100%;max-width:none}}'
        );
    }
}
add_action('admin_enqueue_scripts', 'alanfullbeard_filehub_admin_styles');

if (! function_exists('alanfullbeard_filehub_handle_upload')) {
    function alanfullbeard_filehub_handle_upload(): never
    {
        alanfullbeard_filehub_require_admin();
        check_admin_referer('alanfullbeard_filehub_upload');

        $root = alanfullbeard_filehub_root_key($_POST['root'] ?? 'storage');
        if (! alanfullbeard_filehub_prepare_directories()) {
            alanfullbeard_filehub_redirect($root, 'storage-unavailable');
        }

        $upload = $_FILES['file'] ?? null;
        if (! is_array($upload)) {
            alanfullbeard_filehub_redirect($root, 'upload-failed');
        }

        $uploadError = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
        $uploadSize = $upload['size'] ?? null;
        $uploadName = $upload['name'] ?? null;
        $temporaryName = $upload['tmp_name'] ?? null;

        if (! is_int($uploadError) || UPLOAD_ERR_OK !== $uploadError) {
            alanfullbeard_filehub_redirect($root, 'upload-failed');
        }

        if (! is_int($uploadSize) || $uploadSize < 0) {
            alanfullbeard_filehub_redirect($root, 'upload-failed');
        }

        $effectiveMaxUpload = min(
            (int) ALANFULLBEARD_FILEHUB_MAX_UPLOAD_BYTES,
            (int) wp_max_upload_size()
        );
        if ($uploadSize > $effectiveMaxUpload) {
            alanfullbeard_filehub_redirect($root, 'file-too-large');
        }

        if (! is_string($uploadName) || ! is_string($temporaryName)) {
            alanfullbeard_filehub_redirect($root, 'upload-failed');
        }

        $name = alanfullbeard_filehub_valid_name(wp_unslash($uploadName));
        if ('' === $name) {
            alanfullbeard_filehub_redirect($root, 'invalid-name');
        }

        $destination = alanfullbeard_filehub_directory($root) . '/' . $name;
        if (file_exists($destination)) {
            alanfullbeard_filehub_redirect($root, 'file-exists');
        }

        if (! is_uploaded_file($temporaryName) || ! move_uploaded_file($temporaryName, $destination)) {
            alanfullbeard_filehub_redirect($root, 'upload-failed');
        }

        if (! chmod($destination, 0600)) {
            alanfullbeard_filehub_redirect($root, 'operation-failed');
        }

        alanfullbeard_filehub_redirect($root, 'uploaded');
    }
}
add_action('admin_post_alanfullbeard_filehub_upload', 'alanfullbeard_filehub_handle_upload');

if (! function_exists('alanfullbeard_filehub_handle_download')) {
    function alanfullbeard_filehub_handle_download(): never
    {
        alanfullbeard_filehub_require_admin();

        $root = alanfullbeard_filehub_root_key($_GET['root'] ?? 'storage');
        $name = alanfullbeard_filehub_input_string($_GET, 'file');
        check_admin_referer('alanfullbeard_filehub_download_' . $root . '_' . $name);

        $path = alanfullbeard_filehub_existing_file(
            alanfullbeard_filehub_directory($root),
            $name
        );
        if (null === $path) {
            wp_die(
                esc_html__('That file is not available.', 'alanfullbeard'),
                esc_html__('File not found', 'alanfullbeard'),
                ['response' => 404]
            );
        }

        $size = filesize($path);
        if (! is_int($size)) {
            wp_die(
                esc_html__('That file is not available.', 'alanfullbeard'),
                esc_html__('File not found', 'alanfullbeard'),
                ['response' => 404]
            );
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . $size);
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');

        $fallbackName = preg_replace('/[^\x20-\x7E]/', '_', $name);
        $fallbackName = is_string($fallbackName)
            ? str_replace(['"', '\\'], '_', $fallbackName)
            : 'download';
        header(
            'Content-Disposition: attachment; filename="' . $fallbackName
            . '"; filename*=UTF-8\'\'' . rawurlencode($name)
        );

        readfile($path);
        exit;
    }
}
add_action('admin_post_alanfullbeard_filehub_download', 'alanfullbeard_filehub_handle_download');

if (! function_exists('alanfullbeard_filehub_handle_rename')) {
    function alanfullbeard_filehub_handle_rename(): never
    {
        alanfullbeard_filehub_require_admin();

        $root = alanfullbeard_filehub_root_key($_POST['root'] ?? 'storage');
        $oldName = alanfullbeard_filehub_input_string($_POST, 'old_name');
        check_admin_referer('alanfullbeard_filehub_rename_' . $root . '_' . $oldName);

        $newName = alanfullbeard_filehub_valid_name(
            alanfullbeard_filehub_input_string($_POST, 'new_name')
        );
        if ('' === $newName) {
            alanfullbeard_filehub_redirect($root, 'invalid-name');
        }

        $directory = alanfullbeard_filehub_directory($root);
        $source = alanfullbeard_filehub_existing_file($directory, $oldName);
        if (null === $source) {
            alanfullbeard_filehub_redirect($root, 'invalid-file');
        }

        $destination = $directory . '/' . $newName;
        if (file_exists($destination)) {
            alanfullbeard_filehub_redirect($root, 'file-exists');
        }

        if (! rename($source, $destination) || ! chmod($destination, 0600)) {
            alanfullbeard_filehub_redirect($root, 'operation-failed');
        }

        alanfullbeard_filehub_redirect($root, 'renamed');
    }
}
add_action('admin_post_alanfullbeard_filehub_rename', 'alanfullbeard_filehub_handle_rename');

if (! function_exists('alanfullbeard_filehub_handle_move')) {
    function alanfullbeard_filehub_handle_move(): never
    {
        alanfullbeard_filehub_require_admin();

        $root = alanfullbeard_filehub_root_key($_POST['root'] ?? 'storage');
        $name = alanfullbeard_filehub_input_string($_POST, 'file');
        check_admin_referer('alanfullbeard_filehub_move_' . $root . '_' . $name);

        $destinationRoot = 'favorites' === $root ? 'storage' : 'favorites';
        $source = alanfullbeard_filehub_existing_file(
            alanfullbeard_filehub_directory($root),
            $name
        );
        if (null === $source) {
            alanfullbeard_filehub_redirect($root, 'invalid-file');
        }

        $destination = alanfullbeard_filehub_directory($destinationRoot) . '/' . $name;
        if (file_exists($destination)) {
            alanfullbeard_filehub_redirect($root, 'file-exists');
        }

        if (! rename($source, $destination) || ! chmod($destination, 0600)) {
            alanfullbeard_filehub_redirect($root, 'operation-failed');
        }

        alanfullbeard_filehub_redirect($destinationRoot, 'moved');
    }
}
add_action('admin_post_alanfullbeard_filehub_move', 'alanfullbeard_filehub_handle_move');

if (! function_exists('alanfullbeard_filehub_handle_delete')) {
    function alanfullbeard_filehub_handle_delete(): never
    {
        alanfullbeard_filehub_require_admin();

        $root = alanfullbeard_filehub_root_key($_POST['root'] ?? 'storage');
        $name = alanfullbeard_filehub_input_string($_POST, 'file');
        check_admin_referer('alanfullbeard_filehub_delete_' . $root . '_' . $name);

        $path = alanfullbeard_filehub_existing_file(
            alanfullbeard_filehub_directory($root),
            $name
        );
        if (null === $path) {
            alanfullbeard_filehub_redirect($root, 'invalid-file');
        }

        if (! unlink($path)) {
            alanfullbeard_filehub_redirect($root, 'operation-failed');
        }

        alanfullbeard_filehub_redirect($root, 'deleted');
    }
}
add_action('admin_post_alanfullbeard_filehub_delete', 'alanfullbeard_filehub_handle_delete');
