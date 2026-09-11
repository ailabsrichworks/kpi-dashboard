<?php

namespace App\Services;

/**
 * Maps a company code to the logo file that actually shows up against
 * whatever background it's placed on. Some companies only ship one logo
 * variant (a self-contained badge that works on either background); others
 * (RCG) ship a light-on-transparent version and a black-on-transparent
 * version, meant for a dark background and a light background respectively
 * — using the wrong one makes the wordmark invisible, which is why the
 * sidebar and the choose-company page were both showing a plain letter
 * instead of a real logo.
 */
class CompanyLogoService
{
    /**
     * Absolute URL to the right logo variant for $companyCode against
     * $backgroundHex, or null if no logo file exists for that company at
     * all — callers fall back to their existing letter-initial tile in
     * that case.
     */
    public static function forBackground(?string $companyCode, string $backgroundHex): ?string
    {
        $code = strtoupper(trim((string) $companyCode));
        if ($code === '') {
            return null;
        }

        $variant = static::isDark($backgroundHex) ? "{$code}-Logo.png" : "{$code}-Logo-black.png";
        if (is_file(public_path("images/{$variant}"))) {
            return asset("images/{$variant}");
        }

        // Not every company has both variants — a self-contained badge logo
        // (a solid-colour circle, say) reads fine on either background, so
        // there's no "-black" counterpart to fall back from.
        $base = "{$code}-Logo.png";
        if (is_file(public_path("images/{$base}"))) {
            return asset("images/{$base}");
        }

        return null;
    }

    /**
     * Perceived brightness (YIQ), the same formula commonly used for
     * text/foreground contrast checks. Below the threshold counts as dark.
     */
    public static function isDark(string $hex): bool
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return true; // unrecognised value — assume dark, matching the sidebar's own default
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;

        return $brightness < 140;
    }
}
