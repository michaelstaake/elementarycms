<?php

declare(strict_types=1);

namespace Elementary;

class FileManager
{
    public static function getStorageDir(): string
    {
        return ELEMENTARY_ROOT . '/file';
    }

    public static function getFiles(): array
    {
        return Database::fetchAll(
            'SELECT f.*, COALESCE(u.display_name, u.username) AS uploader_name
             FROM files f
             LEFT JOIN users u ON f.uploaded_by = u.id
             WHERE f.deleted_at IS NULL
             ORDER BY f.created_at DESC'
        );
    }

    public static function getFile(int $id): ?array
    {
        return Database::fetch('SELECT * FROM files WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public static function getFileByAdminSlug(string $slug): ?array
    {
        return Database::fetch(
            "SELECT * FROM files WHERE REPLACE(filename, '.', '-') = ? AND deleted_at IS NULL",
            [$slug]
        );
    }

    public static function getFileAdminSlug(array $file): string
    {
        return admin_file_slug($file['filename']);
    }

    public static function getDeletedFiles(): array
    {
        return Database::fetchAll(
            'SELECT f.*, COALESCE(u.display_name, u.username) AS uploader_name
             FROM files f
             LEFT JOIN users u ON f.uploaded_by = u.id
             WHERE f.deleted_at IS NOT NULL
             ORDER BY f.deleted_at DESC'
        );
    }

    public static function getDeletedFile(int $id): ?array
    {
        return Database::fetch('SELECT * FROM files WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
    }

    public static function upload(array $uploadedFile, int $userId): array
    {
        if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => self::uploadErrorMessage((int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE))];
        }

        $originalName = basename((string) ($uploadedFile['name'] ?? ''));
        if ($originalName === '') {
            return ['success' => false, 'error' => 'No file was uploaded.'];
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '') {
            return ['success' => false, 'error' => 'Files must have an extension.'];
        }

        $typeError = self::validateFileType($extension);
        if ($typeError) {
            return ['success' => false, 'error' => $typeError];
        }

        $sizeError = self::validateFileSize((int) ($uploadedFile['size'] ?? 0));
        if ($sizeError) {
            return ['success' => false, 'error' => $sizeError];
        }

        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $slug = Validator::slug($baseName);
        if ($slug === '') {
            $slug = 'file';
        }

        $filename = self::uniqueFilename($slug, $extension);
        $storageDir = self::getStorageDir();

        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
            return ['success' => false, 'error' => 'Unable to create the file storage directory.'];
        }

        $destination = $storageDir . '/' . $filename;
        if (!move_uploaded_file((string) $uploadedFile['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'Failed to save the uploaded file.'];
        }

        $mimeType = mime_content_type($destination) ?: 'application/octet-stream';
        $now = date('Y-m-d H:i:s');
        $title = $baseName !== '' ? $baseName : $filename;

        $id = Database::insert('files', [
            'title'         => $title,
            'filename'      => $filename,
            'original_name' => $originalName,
            'mime_type'     => $mimeType,
            'size'          => (int) ($uploadedFile['size'] ?? 0),
            'alt_text'      => null,
            'description'   => null,
            'uploaded_by'   => $userId,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        Logger::log('file_uploaded', $userId, 'File ID: ' . $id . ' (' . $filename . ')');
        return ['success' => true, 'id' => $id];
    }

    public static function saveFile(array $data, int $id): array
    {
        $file = self::getFile($id);
        if (!$file) {
            return ['success' => false, 'error' => 'File not found.'];
        }

        $title = trim($data['title'] ?? '');
        if ($title === '') {
            return ['success' => false, 'error' => 'Title is required.'];
        }

        Database::update('files', [
            'title'       => $title,
            'alt_text'    => trim($data['alt_text'] ?? '') ?: null,
            'description' => trim($data['description'] ?? '') ?: null,
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        Logger::log('file_updated', Auth::user()['id'] ?? null, 'File ID: ' . $id);
        return ['success' => true];
    }

    public static function deleteFile(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        Database::update('files', ['deleted_at' => $now], 'id = ?', [$id]);
        Logger::log('file_deleted', Auth::user()['id'] ?? null, 'File ID: ' . $id);
    }

    public static function restoreFile(int $id): void
    {
        Database::update('files', ['deleted_at' => null], 'id = ?', [$id]);
        Logger::log('file_restored', Auth::user()['id'] ?? null, 'File ID: ' . $id);
    }

    public static function permanentlyDeleteFile(int $id): void
    {
        $file = self::getDeletedFile($id);
        if (!$file) {
            return;
        }

        self::removePhysicalFile($file['filename']);
        Database::delete('files', 'id = ?', [$id]);
        Logger::log('file_permanently_deleted', Auth::user()['id'] ?? null, 'File ID: ' . $id);
    }

    public static function permanentlyDeleteAllDeleted(): void
    {
        $files = self::getDeletedFiles();
        foreach ($files as $file) {
            self::removePhysicalFile($file['filename']);
        }
        Database::query('DELETE FROM files WHERE deleted_at IS NOT NULL');
    }

    public static function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1073741824, 2) . ' GB';
    }

    private static function uniqueFilename(string $slug, string $extension): string
    {
        $candidate = $slug . '.' . $extension;
        $counter = 2;

        while (
            Database::fetch('SELECT id FROM files WHERE filename = ?', [$candidate])
            || file_exists(self::getStorageDir() . '/' . $candidate)
        ) {
            $candidate = $slug . '-' . $counter . '.' . $extension;
            $counter++;
        }

        return $candidate;
    }

    private static function validateFileType(string $extension): ?string
    {
        // Always blocked for security, regardless of the configured file_types allow-list.
        $blocked = ['svg', 'html', 'htm', 'xhtml', 'shtml'];
        if (in_array(strtolower($extension), $blocked, true)) {
            return 'File type not allowed for security reasons: ' . $extension . '.';
        }

        $allowed = Config::get('file_types', ['png', 'jpg', 'jpeg', 'pdf']);
        if ($allowed === [] || $allowed === null) {
            return null;
        }

        $allowed = array_map('strtolower', (array) $allowed);
        if (!in_array(strtolower($extension), $allowed, true)) {
            return 'File type not allowed. Allowed types: ' . implode(', ', $allowed) . '.';
        }

        return null;
    }

    private static function validateFileSize(int $bytes): ?string
    {
        $limit = Config::get('file_size', '256M');
        if ($limit === '' || $limit === null) {
            return null;
        }

        $maxBytes = self::parseSize((string) $limit);
        if ($maxBytes > 0 && $bytes > $maxBytes) {
            return 'File exceeds the maximum size of ' . $limit . '.';
        }

        return null;
    }

    private static function parseSize(string $size): int
    {
        $size = trim($size);
        if ($size === '') {
            return 0;
        }

        $unit = strtolower(substr($size, -1));
        $value = (float) $size;

        return match ($unit) {
            'g' => (int) ($value * 1024 * 1024 * 1024),
            'm' => (int) ($value * 1024 * 1024),
            'k' => (int) ($value * 1024),
            default => (int) $size,
        };
    }

    private static function removePhysicalFile(string $filename): void
    {
        $path = self::getStorageDir() . '/' . basename($filename);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the maximum allowed size.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write the file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'File upload failed.',
        };
    }
}
