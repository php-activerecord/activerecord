# Contributing to PHP ActiveRecord #

We always appreciate contributions to PHP ActiveRecord, but we are not always able to respond as quickly as we would like.
Please do not take delays personal and feel free to remind us by commenting on issues.

### Testing ###

PHP ActiveRecord has a full set of unit tests, which are run by PHPUnit.

In order to run these unit tests, you need to install the required packages using [Composer](https://getcomposer.org/):

```sh
composer install
```

After that you can run the tests by invoking the local PHPUnit

To run all the tests, simply use:

```sh
composer test
```
which runs:
```sh
vendor/bin/phpunit
```

Or run a single test file:

```sh
composer test dateTime
```
which runs:
```sh
vendor/bin/phpunit test/DateTimeTest.php
```

Or run a single test within a file:

```sh
composer test dateTime setIsoDate
```
which runs:
```sh
vendor/bin/phpunit --filter setIsoDate test/DateTimeTest.php
```

#### Skipped Tests ####

You might notice that some tests are marked as skipped. To obtain more information about skipped
tests, pass the `--verbose` flag to PHPUnit:

```sh
vendor/bin/phpunit --verbose
```

For [Docker](https://docs.docker.com/get-docker/) users, a docker-compose.yml has been provided in the project root that will provide:
- mysql
- postgres
- memcached

Simply run:
```shell
docker-compose up -d
```

Then, the necessary services will be available and the tests should pass (although you may need to install PHP memcache extensions in a separate step, see below).

When you're done, you can take it down with:
```sh
docker-compose down
```

#### Installing memcache on Windows
If you're a Windows user, finding the correct memcache drivers can be a bit tricky, as the PECL repo seems to be in disrepair. You can find them here:

https://github.com/nono303/PHP-memcache-dll/tree/master

Download the .dll that matches your version of PHP, install it into your /ext dir, and add this line to your php.ini:
```ini
extension=memcache
```

#### Alternate setup
If Docker is not available to you, or you would simply not use it, you will have to do your best to install the various services on your own.

* Install `memcached` and the PHP memcached extension (e.g., `brew install php56-memcache memcached` on macOS)
* Install the PDO drivers for PostgreSQL (e.g., `brew install php56-pdo-pgsql` on macOS)
* Create a MySQL database and a PostgreSQL database. You can either create these such that they are available at the default locations of `mysql://test:test@127.0.0.1/test` and `pgsql://test:test@127.0.0.1/test` respectively. Alternatively, you can set the `PHPAR_MYSQL` and `PHPAR_PGSQL` environment variables to specify a different location for the MySQL and PostgreSQL databases.

#### Style checker

* You can check your code with a PSR-2 style checker locally with the command:
```sh
composer style-check
```
and fix it with:
```sh
composer style-fix
```

#### Static analysis

* You can check your code with the PHPStan static analysis checker with the command:
```sh
composer stan
```

#### Before you submit your pull request

```sh
composer style-check
composer test
composer stan
```
will be run for every [pull request](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/proposing-changes-to-your-work-with-pull-requests/creating-a-pull-request-from-a-fork) as part of the Docker-enabled [continuous integration](https://docs.github.com/en/actions/automating-builds-and-tests/about-continuous-integration), so it would be good to run these checks locally before submitting your pull request.