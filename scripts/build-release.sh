#!/usr/bin/env bash

set -euo pipefail

plugin_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
output_path="${1:-/tmp/formularios_.zip}"
output_path="$(realpath -m "$output_path")"

if [[ "$output_path" != *.zip ]]; then
    echo "ERROR: el destino debe terminar en .zip" >&2
    exit 1
fi

if [[ "$output_path" == "$plugin_dir"/* ]]; then
    echo "ERROR: el ZIP debe generarse fuera del directorio del plugin" >&2
    exit 1
fi

for command_name in php zip unzip; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "ERROR: falta el comando requerido: $command_name" >&2
        exit 1
    fi
done

stage_dir="$(mktemp -d)"
trap 'rm -rf "$stage_dir"' EXIT
package_dir="$stage_dir/formularios_"
mkdir -p "$package_dir/includes" "$package_dir/assets/css" "$package_dir/assets/js"

cp "$plugin_dir/formularios_.php" "$plugin_dir/README.md" "$plugin_dir/CHANGELOG.md" "$plugin_dir/uninstall.php" "$package_dir/"
cp "$plugin_dir"/includes/*.php "$package_dir/includes/"
cp "$plugin_dir"/assets/css/*.css "$package_dir/assets/css/"
cp "$plugin_dir"/assets/js/*.js "$package_dir/assets/js/"

header_version="$(php -r '$source = file_get_contents($argv[1]); preg_match("/^ \\* Version: ([0-9]+\\.[0-9]+\\.[0-9]+)$/m", $source, $match); echo $match[1] ?? "";' "$package_dir/formularios_.php")"
constant_version="$(php -r '$source = file_get_contents($argv[1]); preg_match("/define\\(\x27FORMULARIOS_PW_VERSION\x27, \x27([0-9]+\\.[0-9]+\\.[0-9]+)\x27\\)/", $source, $match); echo $match[1] ?? "";' "$package_dir/formularios_.php")"

if [[ -z "$header_version" || "$header_version" != "$constant_version" ]]; then
    echo "ERROR: la versión de cabecera y FORMULARIOS_PW_VERSION no coinciden" >&2
    exit 1
fi

forbidden_file="$(find "$package_dir" -type f \( -name 'wp-config.php' -o -name '.env' -o -name '*.key' -o -name '*.pem' -o -name '*.log' -o -name '*.sql' -o -name '*.bak' -o -name '*.backup' -o -name '*~' \) -print -quit)"
if [[ -n "$forbidden_file" ]]; then
    echo "ERROR: archivo privado o local detectado: $forbidden_file" >&2
    exit 1
fi

if grep -RIEq --exclude='README.md' --exclude='CHANGELOG.md' '(BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|github_pat_[A-Za-z0-9_]+|ghp_[A-Za-z0-9]+|DB_PASSWORD[[:space:]]*=|SMTP_PASS(WORD)?[[:space:]]*=)' "$package_dir"; then
    echo "ERROR: posible secreto detectado en el paquete" >&2
    exit 1
fi

mkdir -p "$(dirname "$output_path")"
rm -f "$output_path"
(
    cd "$stage_dir"
    zip -qr "$output_path" formularios_
)

if ! unzip -Z1 "$output_path" | grep -q '^formularios_/formularios_\.php$'; then
    echo "ERROR: falta formularios_/formularios_.php en el ZIP" >&2
    exit 1
fi

if unzip -Z1 "$output_path" | grep -Ev '^formularios_(/|$)' | grep -q .; then
    echo "ERROR: el ZIP contiene rutas fuera de formularios_/" >&2
    exit 1
fi

echo "ZIP creado: $output_path"
echo "Versión: $header_version"
