# Security fixes

This file lists the security problems in Kohana 2.4 (upstream `master`) that
this fork fixes, and the problems that are still open. Each fix has a test,
except K2-21 (`index.php`, checked by hand over HTTP).

Labels:

- **CVE**: a CVE identifier exists.
- **Upstream ticket**: an upstream ticket or commit names the problem.
- **Hardening**: found in a code review of this fork. No earlier public report.

Sources searched (2026-11-18): NVD, OSV, exploit-db and a web search for
Kohana 2. The old upstream issue tracker (dev.kohanaframework.org) was not
reachable through the Wayback Machine (HTTP 429), so tickets that only exist
there are not covered.

- CVE-2016-10510 is filed for Kohana 3, but Debian also fixed it in its
  Kohana 2.3.4 package (DLA-1241-1). Kohana 2.4 has the same code (K2-27).
- Exploit-db EDB-24436 ("Kohana 2.3.3 directory traversal") is about
  `classes/Kohana/Filebrowser.php`. No such file exists in Kohana 2 or 3:
  it belongs to a third-party application.

To report a new problem, see [README.md](README.md#report-a-security-problem).

## Fixed

| ID | Problem | Label | Severity | Commit |
|----|---------|-------|----------|--------|
| K2-01 | `xss_clean()` bypasses of tickets #2669, #2710 and #2771 (fixed upstream after the 2.4 tag; this fork adds regression tests) | Upstream ticket | Medium | `efd11f50` |
| K2-02 | Reflected XSS on the debug error page and the 404 page | Hardening | Medium | `10277b14`, `9e233b0b` |
| K2-03 | Reflected XSS in the shipped Welcome controller | Hardening | Medium | `a7ec78c4` |
| K2-04 | SQL injection through the `order_by()` direction | Hardening | High | `8a81b961` |
| K2-05 | The cookie session driver let the client forge session data and inject PHP objects (with the public default salt) | Hardening | High | `aedf4c42`, `eee807e8` |
| K2-06 | Session fixation: the session adopted an ID that the client chose | Hardening | High | `1b9c084a` |
| K2-07 | `Encrypt` had no MAC, needed mcrypt (removed in PHP 7.2) and shipped a public default key. The internal cache used mcrypt in ECB mode | Hardening | Medium | `97d2758c`, `a2e02df6`, `fda4cb57` |
| K2-08 | The cookie signature was `sha1()` of concatenated values, compared with `===` | Hardening | Low to medium | `e709634a` |
| K2-09 | Client IP spoofing through X-Forwarded-For and Client-IP in `Input::ip_address()` | Hardening | Medium | `65247086` |
| K2-10 | Global input cleaning and `global_xss_filtering` did not run unless code created Input | Hardening | Medium | `fbb5efd8`, `2a34c201` |
| K2-11 | `xss_clean()` bypasses with HTML5 entities and `data:` URLs | Hardening | Medium | `2b231157`, `c555b300` |
| K2-12 | Host header injection in absolute URLs and redirects | Hardening | Medium | `88503e49` |
| K2-13 | HTML and form helpers printed data values without escaping | Hardening | Low to medium | `3f54f71b` |
| K2-14 | `valid::url()` and `html::anchor()` accepted `javascript:` URLs | Hardening | Medium | `4cb6e0dc` |
| K2-15 | Identifier and operator injection in the query builder | Hardening | Medium | `9b8279f4`, `3aca13e7` |
| K2-16 | `upload::save()` kept the client file name, `upload::type()` checked only the extension, uploads went under DOCROOT | Hardening | Medium | `4b89a7e4` |
| K2-17 | Path traversal in `Kohana::find_file()` (views, i18n, messages) | Hardening | Medium | `edaa2f3f` |
| K2-18 | The file cache driver used cache keys as `glob()` patterns | Hardening | Medium | `ccc3318b`, `82ceb6c3` |
| K2-19 | No CSRF protection: new `csrf` helper | Hardening | Medium | `6840b378` |
| K2-20 | The Profiler rendered in production and printed some values raw | Hardening | Low to medium | `8651a1b8` |
| K2-21 | `index.php` ran `install.php` in production. An orphan Captcha controller | Hardening | Low | `d4385dc4` |
| K2-22 | `text::random()` used `mt_rand()` | Hardening | Low | `6af58978` |
| K2-23 | No safe redirect for untrusted targets: new `url::redirect_local()` | Hardening | Low | `e075525a` |
| K2-24 | `Input` stopped the request on a bad cookie name, unsafe download file names, `feed::parse()` read files and URLs | Hardening | Low | `e9dda8ea` |
| K2-25 | ORM mass assignment: `set(array)` and `load_values()` accepted the primary key and many-to-many relations | Hardening | Medium | `629df0e9` |
| K2-27 | XSS: `security::strip_image_tags()` bypass ([CVE-2016-10510](https://nvd.nist.gov/vuln/detail/CVE-2016-10510), Debian DLA-1241-1) | CVE | Medium | `cc12f1bd` |
| K2-26 | The shutdown handler showed an error page for errors that code silenced with `@` | Hardening | Low | `a39da6d4` |

## PHP 8 problems with a security effect

- The `mysql` database driver used ext/mysql, which PHP 7.0 removed
  (`9ae22c58`).
- `ORM::with()` threw a TypeError on PHP 8 (`629df0e9`).
- Session drivers now register as a `SessionHandlerInterface` object
  (`449d22be`). The database driver `regenerate()` left the old session ID
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
