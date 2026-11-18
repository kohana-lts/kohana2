# Upgrade from Kohana 2.4

This file lists the changes that can affect an existing Kohana 2.4
application. Most security fixes use a secure default and have an opt-out.
Read each section before you deploy, and run your own tests.

## Requirements

- PHP 8.1 or later. The `mbstring` and `openssl` extensions are necessary.
- ext/mysql and ext/mcrypt are not necessary any more. The `mysql` database
  type uses MySQLi.

## Configuration that you must set

The shipped secrets of Kohana 2 were public. This fork ships no secrets, and
the features that need one throw a `Kohana_Exception` until you set it:

| Setting | File | Used by |
|---------|------|---------|
| `$config['salt']` | `application/config/cookie.php` | `cookie::set()`, `cookie::get()`, sessions |
| `$config['default']['key']` | `application/config/encryption.php` | `Encrypt`, the cookie session driver |
| `$config['internal_cache_key']` | `application/config/config.php` | the internal cache, when `internal_cache_encrypt` is TRUE |
| `$config['allowed_hosts']` | `application/config/config.php` | `url::base()`, `url::site()` and other helpers with a protocol, when `site_domain` has no host |

Use long random values, for example `base64_encode(random_bytes(32))`. The
old public values ('K0hAN4 15 Th3 B357', 'K0H@NA+PHP_7hE-SW!FtFraM3w0R|<' and
'foobar-changeme') are refused.

`allowed_hosts` is a list of host names. `array('*')` keeps the old behavior
(any Host header), which is not safe. Alternatively, put the host into
`site_domain`.

Set the environment value `KOHANA_ENV=PRODUCTION` on production servers, or
set `IN_PRODUCTION` to `TRUE` in `index.php`. In production:

- error pages show no details (`display_errors` defaults to `! IN_PRODUCTION`
  in `application/config/config.php`),
- the Profiler does not render,
- `index.php` does not run `install.php`,
- demonstration controllers with `ALLOW_PRODUCTION = FALSE` (for example the
  Welcome controller) give a 404.

Copy the new `display_errors` line into your application config, or set it
yourself: the old default was TRUE in every environment.

## Encryption

- `Encrypt` uses AES-256-CBC with HMAC-SHA256. New values start with `v2:`.
- Values from the old mcrypt version do not decode by default. To read them
  during a migration, set `'legacy' => TRUE` with the old `'cipher'` and
  `'mode'`. Only `rijndael-128` is supported. The old public default key works
  only together with `'legacy' => TRUE`. Re-encrypt the data with a new key,
  then remove `'legacy'`.
- The internal cache files of the old version are discarded once.

## Cookies and sessions

- Cookies are signed with HMAC-SHA256. All cookies that an older version
  signed are invalid: users must log in again one time.
- `cookie.user_agent` (default FALSE) adds the User-Agent to the signature.
- `cookie.httponly` is TRUE and the new `cookie.samesite` is 'Lax'. Cross-site
  POST returns (payment callbacks, single sign-on) need `samesite` 'None' and
  `secure` TRUE. Set `samesite` to '' to send no attribute.
- The default session driver is `native`. The `cookie` driver always encrypts
  and authenticates its data, so it needs an encryption key. Its data has an
  expiry. Data cookies from the old version are invalid.
- A session does not adopt an ID that the client chose. `Session::instance($id)`
  reopens only an ID that exists in the storage.
- Call `Session::regenerate()` after a login. `session.regenerate` stays 0.

## Input

- Global input cleaning (key checks, UTF-8 and control characters) and
  `global_xss_filtering` (when TRUE) now run on every request.
- A request key with disallowed characters is dropped and logged. Before, the
  request stopped with "Disallowed key characters".
- `xss_clean()` removes `javascript:`, `vbscript:` and `data:` URLs from
  attributes, also `data:` images. For user HTML, use the `htmlpurifier` tool.
- `Input::ip_address()` reads X-Forwarded-For and Client-IP only from trusted
  proxies. Behind a load balancer or a reverse proxy, set
  `input.trusted_proxies` (new file `system/config/input.php`, IP addresses or
  CIDR ranges). Else `ip_address()` returns the proxy address.

## URLs and helpers

- `valid::url()` accepts only http and https. Use `valid::url($url, NULL)` or
  the rule `url[ftp]` for other schemes.
- `html::anchor()` writes `#` for `javascript:`, `vbscript:` and `data:` URLs.
  `html::$blocked_schemes = array()` turns this off.
- The HTML and form helpers escape data values. Titles and label text stay raw
  where the docblocks say so. `form::button()` escapes its content: pass FALSE
  as the fourth argument to keep HTML.
- `url::redirect()` sends a relative Location header for local URIs. Use the
  new `url::redirect_local()` for targets from the request.
- `feed::parse($feed, $limit, $source)` reads a file or a URL only when
  `$source` is 'file' or 'url'. Without it, `feed::parse($url)` returns an
  empty array.
- `download::dialog()` sends an RFC 6266 file name.
- `text::random()` uses `random_int()`. The output format does not change.
- New helper `csrf`: `csrf::token()`, `csrf::field()` and `csrf::valid($token)`.

## Database and ORM

- `order_by()` accepts only `ASC`, `DESC` or a `Database_Expression` as the
  direction. Random order: `order_by(db::expr('RAND()'))`.
- Identifiers are quoted with doubled quote characters. A column string with
  a double quote must be a call of a function in `Database::$column_functions`
  (for example `COUNT("*")`). `Database::$legacy_column_expressions = TRUE`
  restores the old pass-through, which is not safe with request input.
- `where()`, `having()` and the triplet arrays accept only operators in
  `Database_Builder::$operators`. `join()` accepts only types in
  `Database_Builder::$join_types`. Add values to these public static arrays if
  necessary.
- New `db::escape_like()`.
- ORM: `set(array)` throws for the primary key and many-to-many relations,
  and `load_values()` skips them. Use `set($array, $keys)` to set only listed
  keys. `protected $protected_columns = array();` in a model turns this off.

## Files and paths

- `upload::save()` without a file name uses a random name with one clean
  extension (`x.php.jpg` gives `<random>.jpg`).
- `upload::type()` checks the file content with finfo against
  `config/mimes.php`. The MIME list is old: some valid uploads can fail until
  you add the type that finfo reports in `application/config/mimes.php`.
  `upload.check_mime = FALSE` turns the content check off.
- The default upload directory is `APPPATH.'upload'`, and new directories get
  mode 0755.
- `Kohana::find_file()` returns FALSE for names with `..`, NUL, a backslash or
  a leading slash, and for files whose real path is outside the include paths.
  `Kohana::$find_file_realpath_check = FALSE` allows symlinks that point
  outside.
- The file cache stores entries under hashed names. Old cache files are
  ignored. `Cache::delete_all()` removes them.

## Error handling

- The debug error page escapes all values. The shutdown handler reports only
  fatal errors.
- `locale.timezone` can be empty: PHP then uses its default timezone.
