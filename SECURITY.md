# Security fixes

This file lists the security problems in Kohana 2.4 (upstream `master`) that
this fork fixes, and the problems that are still open. Each fix has a test.

Labels:

- **Upstream ticket**: an upstream ticket or commit names the problem.
- **Hardening**: found in a code review of this fork. No earlier public report.

No CVE identifier exists for Kohana 2.4 code. The exploit-db entry EDB-24436
is for Kohana 2.3.3 and does not apply to 2.4.

To report a new problem, see [README.md](README.md#report-a-security-problem).

## Fixed

| ID | Problem | Label | Severity | Commit |
|----|---------|-------|----------|--------|
| K2-01 | `xss_clean()` bypasses of tickets #2669, #2710 and #2771 (fixed upstream after the 2.4 tag; this fork adds regression tests) | Upstream ticket | Medium | `9706d594` |
| K2-02 | Reflected XSS on the debug error page and the 404 page | Hardening | Medium | `c0dc5a8d`, `2b46ed0f` |
| K2-03 | Reflected XSS in the shipped Welcome controller | Hardening | Medium | `659acccd` |
| K2-04 | SQL injection through the `order_by()` direction | Hardening | High | `96acdf9f` |
| K2-05 | The cookie session driver let the client forge session data and inject PHP objects (with the public default salt) | Hardening | High | `13d21ef5`, `75ab9c5e` |
| K2-06 | Session fixation: the session adopted an ID that the client chose | Hardening | High | `12c5a42c` |
| K2-07 | `Encrypt` had no MAC, needed mcrypt (removed in PHP 7.2) and shipped a public default key. The internal cache used mcrypt in ECB mode | Hardening | Medium | `628c4ae3`, `7be67c8f`, `8d52a1a5` |
| K2-08 | The cookie signature was `sha1()` of concatenated values, compared with `===` | Hardening | Low to medium | `db8b7f6f` |
| K2-09 | Client IP spoofing through X-Forwarded-For and Client-IP in `Input::ip_address()` | Hardening | Medium | `887f82c6` |
| K2-10 | Global input cleaning and `global_xss_filtering` did not run unless code created Input | Hardening | Medium | `3ebbbef9`, `96075121` |
| K2-11 | `xss_clean()` bypasses with HTML5 entities and `data:` URLs | Hardening | Medium | `8bda4f80`, `23298f85` |
| K2-12 | Host header injection in absolute URLs and redirects | Hardening | Medium | `e694b3ab` |
| K2-13 | HTML and form helpers printed data values without escaping | Hardening | Low to medium | `f26fa863` |
| K2-14 | `valid::url()` and `html::anchor()` accepted `javascript:` URLs | Hardening | Medium | `6d817d23` |
| K2-15 | Identifier and operator injection in the query builder | Hardening | Medium | `0910331e`, `eb246ff9` |
| K2-16 | `upload::save()` kept the client file name, `upload::type()` checked only the extension, uploads went under DOCROOT | Hardening | Medium | `41bb2f25` |
| K2-17 | Path traversal in `Kohana::find_file()` (views, i18n, messages) | Hardening | Medium | `dc0dd171` |
| K2-18 | The file cache driver used cache keys as `glob()` patterns | Hardening | Medium | `345db16d`, `a0775e51` |
| K2-19 | No CSRF protection: new `csrf` helper | Hardening | Medium | `b048104e` |
| K2-20 | The Profiler rendered in production and printed some values raw | Hardening | Low to medium | `5186c592` |
| K2-21 | `index.php` ran `install.php` in production. An orphan Captcha controller | Hardening | Low | `965ed8a1` |
| K2-22 | `text::random()` used `mt_rand()` | Hardening | Low | `204044da` |
| K2-23 | No safe redirect for untrusted targets: new `url::redirect_local()` | Hardening | Low | `717f0159` |
| K2-24 | `Input` stopped the request on a bad cookie name, unsafe download file names, `feed::parse()` read files and URLs | Hardening | Low | `6adfeb89` |
| K2-25 | ORM mass assignment: `set(array)` and `load_values()` accepted the primary key and many-to-many relations | Hardening | Medium | `cfed7a95` |
| K2-26 | The shutdown handler showed an error page for errors that code silenced with `@` | Hardening | Low | `f0e45abd` |

## PHP 8 problems with a security effect

- The `mysql` database driver used ext/mysql, which PHP 7.0 removed
  (`5df0f800`).
- `ORM::with()` threw a TypeError on PHP 8 (`cfed7a95`).
- Session drivers now register as a `SessionHandlerInterface` object
  (`f14f3c7b`). The database driver `regenerate()` left the old session ID
  valid; this is fixed in the same commit.

## Open

| Problem | Severity | Why it is open |
|---------|----------|----------------|
| A copied data cookie of the cookie session driver works until it expires, also after logout. Anyone with the encryption key can forge data. | Medium | A client-side session cannot be revoked. Use the `native` or `database` driver (`native` is the default now). |
| `'escape' => FALSE` in a database group turns off all value quoting. | Medium | Some applications use it on purpose. The library logs an alert. |
| `upload::save()` does not check the file type. Applications must call `upload::type()` first. | Low | The API is upstream. |
| `Router::$query_string` and `Router::$complete_uri` hold the URL-decoded query string. | Low | Upstream design (ticket #1887). The error and 404 pages escape it. |
| `session.regenerate` stays 0. | Low | Rotation on each request breaks parallel requests with the native driver. Call `Session::regenerate()` after a login. |
| `xss_clean()` is a blocklist filter and cannot be complete. | Medium | Use the `htmlpurifier` tool for user HTML. |

## Checked, not a problem in 2.4

- Router controller loading: `realpath()` and a prefix check block `..` paths.
- Upload file names: PHP removes `../` from `$_FILES[...]['name']`.
- The File log driver writes `.log.php` files with a `die()` header.
- ImageMagick and GraphicsMagick drivers pass all dynamic arguments through
  `escapeshellarg()`.
- Controller dispatch refuses methods that start with `_` and non-public
  methods.
