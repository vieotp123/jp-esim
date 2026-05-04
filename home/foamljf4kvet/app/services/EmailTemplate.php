<?php
declare(strict_types=1);

final class EmailTemplate
{
    private const DIR = '/home/levanrin2404/esimtravel/templates/email';

    public static function render(string $name, array $vars): string
    {
        $path = self::DIR . '/' . $name . '.html';
        if (!is_file($path)) {
            throw new RuntimeException('Email template not found: ' . $name);
        }
        $html = (string)file_get_contents($path);
        foreach ($vars as $key => $value) {
            $html = str_replace('{{' . $key . '}}', htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'), $html);
        }
        return $html;
    }

    public static function renderRaw(string $name, array $vars): string
    {
        $path = self::DIR . '/' . $name . '.html';
        if (!is_file($path)) {
            throw new RuntimeException('Email template not found: ' . $name);
        }
        $html = (string)file_get_contents($path);
        foreach ($vars as $key => $value) {
            $html = str_replace('{{' . $key . '}}', (string)$value, $html);
        }
        return $html;
    }

    public static function plaintext(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/(?:p|div|tr|li|h[1-6])>/i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}
