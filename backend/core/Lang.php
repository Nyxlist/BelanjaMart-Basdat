<?php
/**
 * Lang - lightweight i18n translation system.
 *
 * Usage:
 *   __('Welcome back, :name!', ['name' => $user['name']])
 *   __('cart.empty')
 *
 * Language files are JSON stored in backend/lang/{locale}.json
 * Fallback locale is always 'en'.
 */

class Lang
{
    private static ?string $locale = null;
    private static array $strings = [];
    private static array $fallback = [];
    private static bool $loaded = false;

    /** Available locales (code => display label). */
    public const LOCALES = [
        'en' => 'English',
        'id' => 'Bahasa Indonesia',
        'ja' => '日本語',
        'zh' => '中文',
    ];

    /** Get the current active locale. */
    public static function locale(): string
    {
        if (self::$locale === null) {
            self::$locale = $_SESSION['__locale']
                ?? config('defaults.locale_code', 'en');
        }
        return self::$locale;
    }

    /** Set the active locale (also persists to session). */
    public static function setLocale(string $locale): void
    {
        if (!array_key_exists($locale, self::LOCALES)) {
            $locale = 'en';
        }
        self::$locale = $locale;
        $_SESSION['__locale'] = $locale;
        self::$loaded = false; // force reload
    }

    /** Load language files. */
    private static function load(): void
    {
        if (self::$loaded) return;

        $dir = __DIR__ . '/../lang/';
        $locale = self::locale();

        // Load fallback (English) first
        $fallbackFile = $dir . 'en.json';
        if (is_file($fallbackFile)) {
            self::$fallback = json_decode(file_get_contents($fallbackFile), true) ?: [];
        }

        // Load current locale
        if ($locale !== 'en') {
            $localeFile = $dir . $locale . '.json';
            if (is_file($localeFile)) {
                self::$strings = json_decode(file_get_contents($localeFile), true) ?: [];
            }
        } else {
            self::$strings = self::$fallback;
        }

        self::$loaded = true;
    }

    /**
     * Translate a key.
     *
     * @param string $key   The translation key (e.g. 'Welcome back')
     * @param array  $replace Placeholder replacements [:name => 'Alex']
     * @return string Translated string, or the key itself if not found
     */
    public static function get(string $key, array $replace = []): string
    {
        self::load();

        // Look up in current locale, then fallback, then return key as-is
        $text = self::$strings[$key] ?? self::$fallback[$key] ?? $key;

        // Replace :placeholder tokens
        foreach ($replace as $placeholder => $value) {
            $text = str_replace(':' . $placeholder, (string) $value, $text);
        }

        return $text;
    }

    /** Get all available locales. */
    public static function available(): array
    {
        return self::LOCALES;
    }
}
