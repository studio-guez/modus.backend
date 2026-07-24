<?php

/**
 * SpamGuard
 *
 * Scoring-based heuristics to detect automated/spam submissions in free-text
 * form fields without relying on an external anti-spam service.
 *
 * Returns a numeric score per field rather than a binary flag so the caller
 * can decide the appropriate action:
 *   total score ≥ 7  → very high confidence spam → silently discard
 *   total score ≥ 3  → suspicious → deliver with '[À VÉRIFIER]' subject prefix
 *   total score  < 3 → clean → deliver normally
 *
 * Checks are intentionally conservative. Generic programming terms
 * (function, select … from, template literals) are not flagged; only
 * clearly executable/injectable payloads are treated as strong signals.
 *
 * @author    Octoplus Solutions
 * @license   Proprietary - All rights reserved. Not free for use.
 * @version   2.0.0
 */
class SpamGuard
{
    // Field types control which checks are applied. Name fields receive the
    // most lenient treatment to avoid penalising unusual but real names.
    public const FIELD_NAME  = 'name';   // nom, prenom
    public const FIELD_SHORT = 'short';  // institution, nomProjet
    public const FIELD_TEXT  = 'text';   // description (free prose)

    // One link in a description is acceptable (e.g. a project website).
    // More than this threshold is a medium spam signal.
    private const MAX_LINKS = 3;

    // Only patterns that reliably indicate genuinely dangerous or injectable
    // content. Intentionally excludes broad terms like 'function()',
    // 'select … from', '{{…}}', and '${…}' that can appear in legitimate
    // project descriptions and would generate false positives.
    private const EXECUTABLE_PATTERNS = [
        '/<\s*script/i',                // <script> → XSS
        '/<\?php/i',                    // <?php → PHP injection
        '/\bjavascript:/i',             // javascript: URI → XSS
        '/\bdata:[^,]{0,30};base64,/i', // data-URI base64 payload
        '/\beval\s*\(/i',               // eval() → code execution
        '/\bunion\s+select\b/i',        // SQL UNION SELECT injection
        '/\bdrop\s+table\b/i',          // SQL DROP TABLE
    ];

    /**
     * Returns a spam-likelihood score for a single field value.
     *
     * Pass the raw (un-stripped) value so that executable tags that would
     * be hidden by strip_tags() are still detectable here.
     *
     * @param  string $text      Raw input value (before strip_tags)
     * @param  string $fieldType One of FIELD_NAME, FIELD_SHORT, FIELD_TEXT
     * @return int               0 = clean; higher = more suspicious
     */
    public static function scoreField(string $text, string $fieldType = self::FIELD_TEXT): int
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return 0;
        }

        $score = 0;

        // ── Strong signals (+4) ─────────────────────────────────────────────

        // Executable/injectable payloads are suspicious in any field type.
        foreach (self::EXECUTABLE_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed) === 1) {
                $score += 4;
                break;
            }
        }

        // A URL inside a name field has no legitimate use.
        if ($fieldType === self::FIELD_NAME && self::countLinks($trimmed) > 0) {
            $score += 4;
        }

        // ── Medium signals (+2) ─────────────────────────────────────────────

        // Link flooding in non-name fields.
        if ($fieldType !== self::FIELD_NAME && self::countLinks($trimmed) > self::MAX_LINKS) {
            $score += 2;
        }

        // Keyboard-mashing: same character repeated 8+ times in a row.
        if (self::hasExcessiveRepetition($trimmed)) {
            $score += 2;
        }

        // ── Weak signals (+1) ───────────────────────────────────────────────

        // Gibberish detection is only meaningful for longer free-text prose.
        if ($fieldType === self::FIELD_TEXT && self::looksLikeGibberish($trimmed)) {
            $score += 1;
        }

        // Random-case token check applies to short-text and free-text fields;
        // name fields are excluded entirely to avoid penalising unusual
        // surnames, all-caps names, or brand names.
        if ($fieldType !== self::FIELD_NAME && self::hasRandomCaseWord($trimmed)) {
            $score += 1;
        }

        return $score;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Counts URL-like substrings ("http(s)://…" or "www.…").
     */
    private static function countLinks(string $text): int
    {
        return (int) preg_match_all('/\b(https?:\/\/|www\.)\S+/iu', $text);
    }

    /**
     * True if the same character appears 8+ times consecutively, e.g.
     * "aaaaaaaa" or "!!!!!!!!". Real prose essentially never does this.
     */
    private static function hasExcessiveRepetition(string $text): bool
    {
        return preg_match('/(.)\1{7,}/u', $text) === 1;
    }

    /**
     * Rough "does this look like real prose?" check for free-text fields.
     *
     * Three signals are tested:
     *  1. Too few actual Unicode letters vs. digits/symbols.
     *  2. Vowel-to-letter ratio outside the normal French/English range
     *     (random character strings tend to have almost no vowels, or
     *     almost only vowels).
     *  3. A single unbroken token that is unreasonably long after URLs are
     *     stripped first (so a legitimate long URL is not penalised).
     */
    private static function looksLikeGibberish(string $text): bool
    {
        // Not enough content for a reliable signal.
        if (mb_strlen($text) < 15) {
            return false;
        }

        // Use Unicode letter property so accented, Cyrillic, Greek, etc.
        // characters are correctly counted as letters.
        $letters     = (string) preg_replace('/[^\p{L}]/u', '', $text);
        $letterCount = mb_strlen($letters);

        // Mostly non-letter characters → likely noise.
        if ($letterCount < 10) {
            return true;
        }

        // Vowel ratio outside the realistic French/English range.
        $vowelCount = (int) preg_match_all(
            '/[aeiouyàâäéèêëïîôöùûüæœAEIOUYÀÂÄÉÈÊËÏÎÔÖÙÛÜÆŒ]/u',
            $letters
        );
        $vowelRatio = $vowelCount / $letterCount;
        if ($vowelRatio < 0.15 || $vowelRatio > 0.80) {
            return true;
        }

        // Strip URLs before checking for abnormally long tokens so that a
        // legitimate link (which routinely exceeds 40 chars) is not penalised.
        $textWithoutUrls = (string) preg_replace(
            '/\bhttps?:\/\/\S+|\bwww\.\S+/iu',
            ' ',
            $text
        );
        foreach ((array) preg_split('/\s+/u', $textWithoutUrls) as $word) {
            if (mb_strlen((string) $word) > 40) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flags a word that has 2+ uppercase letters after its first character
     * AND also contains at least one lowercase letter (i.e. genuinely mixed-
     * case, not simply an all-caps acronym such as "UNESCO" or "EPFL").
     *
     * Examples flagged:     "paiHnlzQvJfokVn"  "xKqZpRvWabcDef"
     * Examples NOT flagged:
     *   - "UNESCO"     → all-caps, no lowercase → excluded by mixed-case check
     *   - "McDonald"   → only 1 uppercase after the first letter
     *   - "OpenAI"     → only 6 letters → below the 12-char minimum
     *   - "JavaScript" → 10 letters → below the 12-char minimum
     *
     * To avoid false positives from a single unusual word buried in normal
     * prose, the word must also account for ≥ 60% of the field's letters.
     */
    private static function hasRandomCaseWord(string $text): bool
    {
        $totalLetters = mb_strlen((string) preg_replace('/[^\p{L}]/u', '', $text));
        if ($totalLetters === 0) {
            return false;
        }

        foreach ((array) preg_split('/[\s,;:]+/u', $text) as $word) {
            $letters     = (string) preg_replace('/[^\p{L}]/u', '', (string) $word);
            $letterCount = mb_strlen($letters);

            // Minimum length raised to 12 to exclude short acronyms and brand
            // names like "EPFL", "OpenAI", or "UNESCO".
            if ($letterCount < 12) {
                continue;
            }

            // Only consider words that dominate the field's content.
            if ($letterCount / $totalLetters < 0.60) {
                continue;
            }

            // Require at least one lowercase letter; pure all-caps words
            // (acronyms, initialisms) are not suspicious.
            if (preg_match('/\p{Ll}/u', $letters) !== 1) {
                continue;
            }

            // Count uppercase letters after the first character.
            $upperCount = (int) preg_match_all('/\p{Lu}/u', mb_substr($letters, 1));
            if ($upperCount >= 2) {
                return true;
            }
        }

        return false;
    }
}
