#!/usr/bin/env bash

# Get install Path
cd $(wp-env install-path)

# Reload Apache flag
RELOAD=false

# Install PHP FTP Extension
if [[ $(docker-compose exec -it -u root wordpress php -m | grep ftp) != "ftp" ]]; then

    echo "Installing: FTP Extension."
    docker-compose exec -it -u root wordpress docker-php-ext-install ftp
    if [[ $(docker-compose exec -it -u root wordpress php -m | grep ftp) == "ftp" ]]; then
        echo "FTP Extension: Installed."
    else
        echo "FTP Extension: Failed."
    fi

    RELOAD=true
else
    echo "FTP Extension: Skipped."
fi

# Install PHP ZIP Extension on cli
if ! docker-compose exec -it -u root cli apk info | grep -q '^zip$'; then
    echo "Installing: zip Extension."
    docker-compose exec -it -u root cli apk add --no-cache libzip-dev zip
    docker-compose exec -it -u root cli docker-php-ext-install zip
    if [[ $(docker-compose exec -it -u root cli php -m | grep zip) == "zip" ]]; then
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
    docker-compose exec -it -u root wordpress service apache2 reload
fi
