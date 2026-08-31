#!/usr/bin/env bash

set -euo pipefail

if docker compose version >/dev/null 2>&1; then
	DOCKER_COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
	DOCKER_COMPOSE=(docker-compose)
else
	echo "docker compose is required but was not found." >&2
	exit 1
fi

docker_compose() {
	"${DOCKER_COMPOSE[@]}" "$@"
}

# Get install path
INSTALL_PATH="$(wp-env status --json | node -e 'const s=JSON.parse(require("fs").readFileSync(0,"utf8")); console.log(s.installPath||s.workDirectoryPath||"")')"
if [[ -z "$INSTALL_PATH" || ! -d "$INSTALL_PATH" ]]; then
	echo "Could not determine wp-env install path." >&2
	exit 1
fi
cd "$INSTALL_PATH"

# Reload Apache flag
RELOAD=false

# Install PHP FTP Extension
if [[ $(docker_compose exec -T -u root wordpress php -m | grep ftp) != "ftp" ]]; then

	echo "Installing: FTP Extension."
	docker_compose exec -T -u root wordpress docker-php-ext-install ftp
	if [[ $(docker_compose exec -T -u root wordpress php -m | grep ftp) == "ftp" ]]; then
		echo "FTP Extension: Installed."
	else
		echo "FTP Extension: Failed."
	fi

	RELOAD=true
else
	echo "FTP Extension: Skipped."
fi

# Install PHP ZIP Extension on cli
if ! docker_compose exec -T -u root cli apk info | grep -q '^zip$'; then
	echo "Installing: zip Extension."
	docker_compose exec -T -u root cli apk add --no-cache libzip-dev zip
	docker_compose exec -T -u root cli docker-php-ext-install zip
	if [[ $(docker_compose exec -T -u root cli php -m | grep zip) == "zip" ]]; then
		echo "zip Extension: Installed."
	else
		echo "zip Extension: Failed."
	fi

	RELOAD=true
else
	echo "zip Extension: Skipped."
fi

# Reload Apache
if [[ $RELOAD == true ]]; then
	docker_compose exec -T -u root wordpress service apache2 reload
fi
