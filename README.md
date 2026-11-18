# Kohana 2.4, maintained fork with security fixes and PHP 8 support

This repository is an unofficial, community-maintained fork of the
[Kohana PHP framework](https://github.com/kohana/kohana2) 2.x. The last upstream
2.x commit is from April 2010. This fork keeps Kohana 2.4 applications
running on current PHP versions and fixes the security problems that people
found in the 2.x code.

The fork changes as little as possible. Read [UPGRADING.md](UPGRADING.md)
before you replace the files of an existing application: some security fixes
change default behavior.

## Which Kohana 2 version this is

The base is the last state of the upstream 2.x code: the `master` branch of
kohana/kohana2 (commit `02f78360`, 2010-04-02). That is the `2.4` release
tag plus 126 later upstream fixes. The full upstream history is in this
repository. The tag `upstream-2.4-master` marks the upstream state.

One upstream change is reverted: commit `94ce7b21` (2010-02-05) moved ORM to
an external SVN module that no longer exists. ORM was part of the 2.4 release,
so this fork keeps it in `system/libraries`.

Kohana **2.3.4** is a different code line with a different API (for example
the database library). Most applications that still run Kohana 2 use 2.3.4.
This fork does not cover 2.3.4. Applications on 2.3.4 cannot use this fork
without changes.

## Status

| Item | Value |
|------|-------|
| Base | kohana/kohana2 `master` (2.4 plus upstream fixes), ORM restored |
| PHP | 8.1, 8.2, 8.3, 8.4 and 8.5 (tested in CI) |
| Security fixes | See [SECURITY.md](SECURITY.md) |
| Behavior changes | See [UPGRADING.md](UPGRADING.md) |

To see every change of this fork, run:

    git diff upstream-2.4-master main

## Requirements

- PHP 8.1 or later
- The `mbstring` and `openssl` extensions
- `mysqli` for MySQL. The `mysql` extension does not exist since PHP 7.0.
  The `mysql` database type now uses MySQLi, so existing configurations keep
  working.

## Use it in an application

Replace the `system/` directory of your application with the directory of
this repository. Then read [UPGRADING.md](UPGRADING.md) and update your
configuration. Set `IN_PRODUCTION` to `TRUE` in `index.php` on production
servers.

## Run the tests

Upstream Kohana 2 had no unit tests. This fork adds a PHPUnit 9.6 test suite
in `tests/`:

    composer install
    vendor/bin/phpunit

The MySQL tests run only when you set these environment variables:
`KOHANA_TEST_MYSQL_HOST`, `KOHANA_TEST_MYSQL_PORT`, `KOHANA_TEST_MYSQL_USER`,
`KOHANA_TEST_MYSQL_PASSWORD` and `KOHANA_TEST_MYSQL_DATABASE`.

## Report a security problem

Do not open a public issue for a security problem. Send a private report to
the maintainers of this repository (for example a GitHub security advisory).

## License

Kohana is released under the BSD license. See `Kohana License.html`. The
copyright of the original code stays with the Kohana Team.
