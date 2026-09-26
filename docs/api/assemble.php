<?php

/**
 * Assembles API.md from the hand-written prose plus the endpoint sections
 * generated from captured responses.
 *
 * The pieces are concatenated as raw bytes on purpose: PowerShell's
 * Get-Content / Set-Content round trip reinterprets UTF-8 through the
 * Windows-1252 code page and silently mangles every non-ASCII character.
 */
$dir = __DIR__;

$parts = [
    "$dir/prose/01.md",   // title, base url, auth, envelope, status codes
    "$dir/endpoints.md",  // sections 1-7, rendered from captured.json
    "$dir/prose/03.md",   // section 8 filters, section 9 object reference
    "$dir/prose/04.md",   // sections 10-12 integration notes and config
];

// A blank line between the parts: markdown needs one before a heading, and a
// table followed immediately by "## ..." does not render as a table.
$content = implode("\n\n", array_map(
    fn ($file) => rtrim(file_get_contents($file)),
    $parts
));

// docs/api -> project root is two levels up.
$target = __DIR__.'/../../API.md';
file_put_contents($target, $content);

echo 'API.md written'.PHP_EOL;
echo '  bytes      : '.strlen($content).PHP_EOL;
echo '  lines      : '.substr_count($content, "\n").PHP_EOL;
echo '  has BOM    : '.(str_starts_with($content, "\xEF\xBB\xBF") ? 'YES' : 'no').PHP_EOL;
echo '  valid UTF-8: '.(mb_check_encoding($content, 'UTF-8') ? 'yes' : 'NO').PHP_EOL;
echo '  sections   : '.substr_count($content, "\n# ").PHP_EOL;
echo '  endpoints  : '.substr_count($content, "\n### ").PHP_EOL;
