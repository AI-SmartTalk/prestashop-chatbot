#!/bin/sh
# PrestaShop 1.7.5.1 ships partial CLDR data but the installer still tries to
# fetch http://i18n.prestashop.com/cldr/json-full.zip (defunct) and to load
# `main/<locale>/<section>` via the icanboogie WebProvider, which now hits
# the same dead origin and aborts `php install/index_cli.php` with
# "Path not defined: …" or "La propriété Currency->name est vide.".
#
# We seed the CLDR cache with the supplemental data shipped in the image
# plus pre-bundled main/<locale>/*.json (fr-FR, en-US) sourced from
# unicode-cldr/cldr-*-full and re-keyed from `<lang>` to `<lang>-<region>`.
# Anything still missing degrades to empty arrays via a patched WebProvider.

set -e

CLDR_DIR=/var/www/html/translations/cldr
mkdir -p "$CLDR_DIR/datas/supplemental" "$CLDR_DIR/datas/main"

# 1) Minimal valid zip so Update::init() short-circuits the dead download
if [ ! -f "$CLDR_DIR/core.zip" ]; then
    php -r '$z = new ZipArchive(); $z->open("/var/www/html/translations/cldr/core.zip", ZipArchive::CREATE); $z->addFromString(".placeholder", ""); $z->close();'
fi

# 2) Pre-populate the FileProvider cache with shipped supplemental JSON
for src in "$CLDR_DIR/datas/supplemental/"*.json; do
    [ -f "$src" ] || continue
    name=$(basename "$src" .json)
    dst="$CLDR_DIR/supplemental--$name"
    [ -f "$dst" ] || cp "$src" "$dst"
done

# 3) Drop in pre-bundled main/<locale>/*.json and prime the cache
SEED_DIR=/tmp/ps1751-cldr-data/main
if [ -d "$SEED_DIR" ]; then
    for locale_dir in "$SEED_DIR"/*; do
        [ -d "$locale_dir" ] || continue
        locale=$(basename "$locale_dir")
        mkdir -p "$CLDR_DIR/datas/main/$locale"
        for src in "$locale_dir"/*.json; do
            [ -f "$src" ] || continue
            file=$(basename "$src" .json)
            cp "$src" "$CLDR_DIR/datas/main/$locale/$file.json"
            cp "$src" "$CLDR_DIR/main--$locale--$file"
        done
    done
fi

# 4) Patch icanboogie/cldr WebProvider to gracefully return empty data on
#    HTTP failure (the dead i18n.prestashop.com origin) instead of throwing.
WEBPROV=/var/www/html/vendor/icanboogie/cldr/lib/WebProvider.php
if [ -f "$WEBPROV" ] && ! grep -q 'PRESTASHOP_AIST_PATCH' "$WEBPROV"; then
    php -r '
        $f = "/var/www/html/vendor/icanboogie/cldr/lib/WebProvider.php";
        $c = file_get_contents($f);
        $needle = "if (\$http_code != 200)\n\t\t{\n\t\t\tthrow new ResourceNotFound(\$path);\n\t\t}";
        $replacement = "// PRESTASHOP_AIST_PATCH: degrade gracefully — origin is gone.\n\t\tif (\$http_code != 200)\n\t\t{\n\t\t\treturn array(\"main\" => array(), \"supplemental\" => array());\n\t\t}";
        $out = str_replace($needle, $replacement, $c);
        file_put_contents($f, $out);
    '
fi

chown -R www-data:www-data "$CLDR_DIR" 2>/dev/null || true
echo "  └─ CLDR cache seeded (core.zip + supplemental--* + main--{fr-FR,en-US}--* + WebProvider patched)"
