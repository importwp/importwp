# Development Notes

## Change version number

```bash
bash bin/version.sh <version>
```

## Generate Zip

A bash script is included to generate a versioned zip in the `build` folder.

```bash
bash bin/build.sh
```

## PHPUnit Tests

Install test dependencies

```bash
wp-env run cli --env-cwd=wp-content/plugins/importwp composer install
```

Run PHPUnit tests

```bash
wp-env run cli --env-cwd=wp-content/plugins/importwp ./vendor/bin/phpunit
```
