#!/bin/sh
#
# SPDX-FileCopyrightText: 2023 Alec Kojaev <alec@kojaev.name>
# SPDX-License-Identifier:  CC0-1.0
#
# This script needs `sed`.
#
set -e

CUR_DIR=$(dirname "$0")

mkdir -p "$CUR_DIR/build"
sed -n '/^## [^\[]/{p;:a;n;/^## /q;p;ba}' "$CUR_DIR/CHANGELOG.md"  >"$CUR_DIR/build/relinfo.md"
