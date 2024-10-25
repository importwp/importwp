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
fi

# Reload Apache
if [[ $RELOAD == true ]]; then
    docker-compose exec -it -u root wordpress service apache2 reload
fi
