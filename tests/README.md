# Unit Tests using LocalWP

## Install Tests

1. Add svn to path: `export PATH="$PATH:~/apache-svn/bin"`.
2. Install tests: `bash bin/install-wp-tests.sh wordpress_db_test root root`.
3. Edit test wp-config using `code ~/AppData/Local/Temp/wordpress-tests-lib/wp-tests-config.php` setting `ABSPATH` to `C:\\Users\\james\\AppData\\Local\\Temp\\wordpress\\`.

## Ruinng Tests

`WP_TESTS_DIR="C:\\Users\\james\\AppData\\Local\\Temp\\wordpress-tests-lib\\" vendor/bin/phpunit`

