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
}
