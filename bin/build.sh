#!/usr/bin/env bash
SCRIPT=$(readlink -f "$0")
SCRIPTPATH=$(dirname "$SCRIPT")
FOLDER=$(basename $(realpath "$SCRIPTPATH/.."))

cd "$SCRIPTPATH/.."

pnpm i
pnpm build
wp-env run cli --env-cwd=wp-content/plugins/$FOLDER wp package install wp-cli/dist-archive-command:@stable
wp-env run cli --env-cwd=wp-content/plugins/$FOLDER wp i18n make-pot . languages/jc-importer.pot --skip-js --slug=jc-importer
wp-env run cli --env-cwd=wp-content/plugins/$FOLDER wp dist-archive . ./build  --plugin-dirname=jc-importer --create-target-dir