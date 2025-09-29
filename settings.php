<?php
require_once __DIR__.'/config.php';

class Settings {
  private static ?array $cache = null;

  private static function loadAll(): void {
    if (self::$cache !== null) return;
    self::$cache = [];
    try {
      $rows = pdo()->query("SELECT key_name, value FROM settings")->fetchAll();
      foreach ($rows as $r) {
        self::$cache[$r['key_name']] = $r['value'];
      }
    } catch (Throwable $e) {
      // Table may not exist yet; keep cache empty
      self::$cache = [];
    }
  }

  public static function get(string $key, string $default = ''): string {
    self::loadAll();
    return array_key_exists($key, self::$cache) ? (string)self::$cache[$key] : $default;
  }

  public static function set(string $key, ?string $value): void {
    $st = pdo()->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
    $st->execute([$key, $value]);
    if (self::$cache !== null) {
      self::$cache[$key] = $value;
    }
  }

  // Returns the selected timezone ID (IANA), defaulting to PHP's default if not set
  public static function timezoneId(): string {
    $tz = self::get('timezone', date_default_timezone_get());
    return $tz ?: 'UTC';
  }

  // Formats a SQL DATETIME string into YYYY-MM-DD HH:MM in the configured timezone
  public static function formatDateTime(?string $sqlDateTime): string {
    if (!$sqlDateTime) return '';
    try {
      $dt = new DateTime($sqlDateTime); // treat as server default tz
      $tz = new DateTimeZone(self::timezoneId());
      $dt->setTimezone($tz);
      return $dt->format('Y-m-d H:i T');
    } catch (Throwable $e) {
      return $sqlDateTime;
    }
  }

  // Formats a date range - if start and end are the same, shows single date; otherwise shows range with arrow
  public static function formatDateRange(?string $startDate, ?string $endDate): string {
    if (!$startDate || !$endDate) return '';
    if ($startDate === $endDate) {
      return h($startDate);
    } else {
      return h($startDate) . ' → ' . h($endDate);
    }
  }
}
