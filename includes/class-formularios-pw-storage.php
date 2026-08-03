<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Storage — Gestiona lectura y escritura cifrada de expedientes y adjuntos privados.
 */
final class Formularios_PW_Storage
{
    /**
     * MAX_UPLOAD_BYTES — Define el tamaño máximo por archivo para cargas externas.
     */
    private const MAX_UPLOAD_BYTES = 25 * 1024 * 1024;

    /**
     * allowed_mimes — Devuelve los MIME permitidos para materiales del cliente.
     */
    public static function allowed_mimes(): array
    {
        return array(
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'video/mp4',
            'video/quicktime',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
        );
    }

    /**
     * storage_dir — Obtiene el directorio base privado para expedientes.
     */
    public static function storage_dir(): string
    {
        if (defined('CODEPTY_PW_STORAGE_DIR')) {
            return rtrim((string) CODEPTY_PW_STORAGE_DIR, '/');
        }

        return rtrim(dirname(ABSPATH), '/') . '/private/codepty-presencia-web';
    }

    /**
     * ensure_storage_ready — Crea estructura privada base cuando aún no existe.
     */
    public static function ensure_storage_ready(): void
    {
        $base = self::storage_dir();
        $folders = array(
            $base,
            $base . '/cases',
            $base . '/attachments',
        );

        foreach ($folders as $folder) {
            if (!is_dir($folder) && !wp_mkdir_p($folder)) {
                throw new RuntimeException('No se pudo crear el directorio privado: ' . $folder);
            }
        }

        if (!is_writable($base)) {
            throw new RuntimeException('El directorio privado no tiene permisos de escritura: ' . $base);
        }
    }

    /**
     * read_case_payload — Descifra y devuelve el contenido completo de un expediente.
     */
    public static function read_case_payload(string $storage_ref): array
    {
        $path = self::case_file_path($storage_ref);

        if (!is_file($path)) {
            return self::default_payload($storage_ref);
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException('No se pudo leer el expediente cifrado.');
        }

        $envelope = json_decode($raw, true);
        if (!is_array($envelope)) {
            throw new RuntimeException('El expediente cifrado tiene formato inválido.');
        }

        return Formularios_PW_Crypto::decrypt_json($envelope);
    }

    /**
     * write_case_payload — Cifra y persiste el contenido del expediente de forma atómica.
     */
    public static function write_case_payload(string $storage_ref, array $payload): void
    {
        self::ensure_storage_ready();

        $path = self::case_file_path($storage_ref);
        $directory = dirname($path);

        if (!is_dir($directory) && !wp_mkdir_p($directory)) {
            throw new RuntimeException('No se pudo crear el subdirectorio del expediente.');
        }

        $payload['meta']['storage_ref'] = $storage_ref;
        $payload['meta']['updated_at'] = gmdate('c');

        $envelope = Formularios_PW_Crypto::encrypt_json($payload);
        $encoded = wp_json_encode($envelope, JSON_UNESCAPED_SLASHES);

        if (!is_string($encoded)) {
            throw new RuntimeException('No se pudo serializar el sobre cifrado.');
        }

        $tmp = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $encoded, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir el archivo temporal del expediente.');
        }

        @chmod($tmp, 0600);

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo reemplazar el archivo cifrado del expediente.');
        }

        @chmod($path, 0600);
    }

    /**
     * store_uploaded_file — Valida y cifra un archivo cargado, devolviendo su metadato seguro.
     */
    public static function store_uploaded_file(string $storage_ref, array $uploaded): array
    {
        self::ensure_storage_ready();

        if (!isset($uploaded['tmp_name'], $uploaded['name'], $uploaded['size'], $uploaded['error'])) {
            throw new RuntimeException('La carga recibida no tiene el formato esperado.');
        }

        if ((int) $uploaded['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error en la carga del archivo. Código: ' . (int) $uploaded['error']);
        }

        $size = (int) $uploaded['size'];
        if ($size <= 0 || $size > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('El archivo supera el tamaño permitido de 25MB o está vacío.');
        }

        $tmp_path = (string) $uploaded['tmp_name'];
        if (!is_uploaded_file($tmp_path) || !is_readable($tmp_path)) {
            throw new RuntimeException('No se puede leer el archivo temporal subido.');
        }

        $mime = (string) self::detect_mime_type($tmp_path);
        if (!in_array($mime, self::allowed_mimes(), true)) {
            throw new RuntimeException('Tipo de archivo no permitido: ' . $mime);
        }

        $binary = file_get_contents($tmp_path);
        if (!is_string($binary) || $binary === '') {
            throw new RuntimeException('No se pudo leer el contenido binario del archivo subido.');
        }

        $asset_ref = bin2hex(random_bytes(16));
        $asset_path = self::attachment_file_path($storage_ref, $asset_ref);
        $asset_dir = dirname($asset_path);

        if (!is_dir($asset_dir) && !wp_mkdir_p($asset_dir)) {
            throw new RuntimeException('No se pudo crear el directorio de adjuntos cifrados.');
        }

        $envelope = Formularios_PW_Crypto::encrypt_json(
            array(
                'filename' => sanitize_file_name((string) $uploaded['name']),
                'mime' => $mime,
                'binary_base64' => base64_encode($binary),
            )
        );

        $encoded = wp_json_encode($envelope, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || file_put_contents($asset_path, $encoded, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el adjunto cifrado.');
        }

        @chmod($asset_path, 0600);

        return array(
            'asset_ref' => $asset_ref,
            'original_name' => sanitize_file_name((string) $uploaded['name']),
            'mime' => $mime,
            'size' => $size,
            'created_at' => gmdate('c'),
        );
    }

    /**
     * default_payload — Construye la estructura inicial de un expediente nuevo.
     */
    public static function default_payload(string $storage_ref): array
    {
        return array(
            'schema_version' => 1,
            'external_form' => array(),
            'internal_form' => array(),
            'attachments_meta' => array(),
            'timeline' => array(),
            'meta' => array(
                'storage_ref' => $storage_ref,
                'created_at' => gmdate('c'),
                'updated_at' => gmdate('c'),
            ),
        );
    }

    /**
     * detect_mime_type — Detecta MIME real desde contenido para evitar suplantación de extensiones.
     */
    private static function detect_mime_type(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return '';
        }

        $mime = (string) finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime;
    }

    /**
     * case_file_path — Resuelve la ruta física del archivo .pty de un expediente.
     */
    private static function case_file_path(string $storage_ref): string
    {
        $prefix = substr($storage_ref, 0, 2);

        return self::storage_dir() . '/cases/' . $prefix . '/' . $storage_ref . '.pty';
    }

    /**
     * attachment_file_path — Resuelve la ruta física de un adjunto cifrado del expediente.
     */
    private static function attachment_file_path(string $storage_ref, string $asset_ref): string
    {
        $prefix = substr($storage_ref, 0, 2);

        return self::storage_dir() . '/attachments/' . $prefix . '/' . $storage_ref . '/' . $asset_ref . '.pty';
    }
}
