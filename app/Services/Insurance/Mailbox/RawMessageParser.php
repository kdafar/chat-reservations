<?php

namespace App\Services\Insurance\Mailbox;

/**
 * Turns a raw RFC-822 message into the flat shape the importer wants.
 *
 * Deliberately small: enough to read what a claims clerk actually types back —
 * headers, the text/plain part of a multipart reply (falling back to stripped
 * HTML), quoted-printable and base64 bodies, and UTF-8 encoded-word subjects,
 * which is how Arabic subjects arrive. Anything exotic still lands in the
 * unmatched queue for a human rather than being dropped.
 */
class RawMessageParser
{
    public function parse(string $raw): array
    {
        [$headerBlock, $body] = $this->split($raw);
        $headers = $this->headers($headerBlock);

        $contentType = $headers['content-type'] ?? 'text/plain';
        $text = $this->extractText($body, $contentType, $headers['content-transfer-encoding'] ?? '');

        [$fromName, $fromEmail] = $this->address($headers['from'] ?? '');

        return [
            'message_id' => trim($headers['message-id'] ?? '', '<> '),
            'in_reply_to' => trim($headers['in-reply-to'] ?? '', '<> ') ?: null,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'subject' => $this->decodeWords($headers['subject'] ?? ''),
            'received_at' => isset($headers['date']) ? date('Y-m-d H:i:s', strtotime($headers['date'])) : null,
            'body' => $this->stripQuotedHistory($text),
        ];
    }

    /**
     * Headers end at the first blank line. When a message arrives without one
     * — malformed, but it happens — everything from the first line that isn't
     * a header is treated as the body, rather than losing the body entirely.
     */
    private function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $pos = strpos($raw, "\n\n");
        if ($pos !== false) {
            return [substr($raw, 0, $pos), substr($raw, $pos + 2)];
        }

        $lines = explode("\n", $raw);
        foreach ($lines as $i => $line) {
            if ($line !== '' && ! preg_match('/^([A-Za-z\-]+):\s|^\s+\S/', $line)) {
                return [implode("\n", array_slice($lines, 0, $i)), implode("\n", array_slice($lines, $i))];
            }
        }

        return [$raw, ''];
    }

    /** Lower-cased header map, with folded continuation lines joined. */
    private function headers(string $block): array
    {
        $out = [];
        $current = null;
        foreach (explode("\n", $block) as $line) {
            if ($current !== null && preg_match('/^\s+/', $line)) {
                $out[$current] .= ' '.trim($line);

                continue;
            }
            if (preg_match('/^([A-Za-z\-]+):\s*(.*)$/', $line, $m)) {
                $current = strtolower($m[1]);
                $out[$current] = trim($m[2]);
            }
        }

        return $out;
    }

    /** Pick the readable part of the body and decode its transfer encoding. */
    private function extractText(string $body, string $contentType, string $encoding): string
    {
        if (stripos($contentType, 'multipart/') !== false
            && preg_match('/boundary="?([^";\s]+)"?/i', $contentType, $m)) {
            $parts = explode('--'.$m[1], $body);
            $html = null;
            foreach ($parts as $part) {
                if (! str_contains($part, ':')) {
                    continue;
                }
                [$ph, $pb] = $this->split(ltrim($part, "\n"));
                $headers = $this->headers($ph);
                $type = $headers['content-type'] ?? '';
                $decoded = $this->decodeBody($pb, $headers['content-transfer-encoding'] ?? '');

                if (stripos($type, 'text/plain') !== false) {
                    return $decoded;               // preferred — what they typed
                }
                if (stripos($type, 'text/html') !== false) {
                    $html = $decoded;              // fallback for HTML-only replies
                }
            }
            if ($html !== null) {
                return $this->htmlToText($html);
            }
        }

        $decoded = $this->decodeBody($body, $encoding);

        return stripos($contentType, 'text/html') !== false ? $this->htmlToText($decoded) : $decoded;
    }

    private function decodeBody(string $body, string $encoding): string
    {
        return match (strtolower(trim($encoding))) {
            'base64' => base64_decode($body) ?: $body,
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<br\s*/?>|</p>|</div>|</tr>#i', "\n", $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** RFC 2047 encoded words — how non-ASCII subjects travel. */
    private function decodeWords(string $value): string
    {
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded !== false ? $decoded : $value;
    }

    /** @return array{0:?string,1:?string} name, email */
    private function address(string $value): array
    {
        $value = $this->decodeWords($value);
        if (preg_match('/^\s*"?([^"<]*)"?\s*<([^>]+)>/', $value, $m)) {
            return [trim($m[1]) ?: null, strtolower(trim($m[2]))];
        }

        return [null, strtolower(trim($value)) ?: null];
    }

    /**
     * Drop the statement we sent back to ourselves. Without this every stored
     * reply carries the whole original letter and the clerk has to hunt for the
     * two sentences that are actually new.
     */
    private function stripQuotedHistory(string $text): string
    {
        $lines = preg_split("/\n/", str_replace("\r\n", "\n", $text));
        $kept = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            // "On 27 Jul 2026, X wrote:" and friends, or Outlook's separator.
            if (preg_match('/^On .{5,80}\bwrote:\s*$/i', $trimmed)
                || preg_match('/^-{2,}\s*(Original Message|Forwarded message)/i', $trimmed)
                || preg_match('/^_{5,}$/', $trimmed)
                || preg_match('/^From:\s.+@/i', $trimmed)) {
                break;
            }
            if (str_starts_with($trimmed, '>')) {
                continue;
            }
            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }
}
