# API documentation generator

`API.md` in the project root is not written by hand. The JSON in it is captured
from a running server, so the examples cannot quietly drift from what the API
actually returns.

## Regenerate

With a server running on `http://127.0.0.1:8123` (`php artisan serve --port=8123`):

```bash
node docs/api/capture.mjs      # call every endpoint, save the real responses
php  docs/api/render.php       # turn captured.json into endpoint sections
php  docs/api/assemble.php    # stitch prose + sections into API.md
```

To capture against another host:

```bash
GEHNA_API_BASE=https://staging.example.com/api/v1 node docs/api/capture.mjs
```

## What each piece does

| File | Role |
| --- | --- |
| `capture.mjs` | Calls all 43 endpoints (registering a throwaway account for the authenticated ones) and writes `captured.json`. Tokens and throwaway emails/phones are scrubbed. |
| `render.php` | Formats `captured.json` into `endpoints.md`, rewriting the local origin to the production domain and shortening long text and image arrays. |
| `assemble.php` | Concatenates `prose/*.md` and `endpoints.md` into `API.md`. |
| `prose/*.md` | The hand-written parts: base URL, auth flow, envelope, status codes, filters, TypeScript interfaces, integration notes. |

## Two things it deliberately corrects

- The captured origin (`http://127.0.0.1:8123`) is rewritten to
  `https://astroemerging.com/gehna`, because a doc full of localhost is useless
  to the frontend developer.
- An empty `meta` or `errors` is forced back to `{}` rather than `[]`. PHP
  cannot tell the two apart once decoded, and printing `[]` would push the
  reader into writing `response.meta.length` against the wrong type.

The pieces are joined as raw bytes rather than through a shell, because
PowerShell's `Get-Content` / `Set-Content` round trip reinterprets UTF-8 through
the Windows-1252 code page and silently mangles every non-ASCII character.
