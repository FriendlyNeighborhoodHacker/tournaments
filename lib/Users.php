<?php
require_once __DIR__ . '/../config.php';

/**
 * Users data access - read-only.
 * Write operations (create/update/delete/password changes) are handled by UserManagement.
 */
class Users {
  public static function find(int $id): ?array {
    $st = pdo()->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function findByEmail(string $email): ?array {
    $st = pdo()->prepare("SELECT * FROM users WHERE email = ?");
    $st->execute([$email]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function roster(): array {
    $sql = "SELECT id, first_name, last_name, email, phone, is_coach, is_admin FROM users ORDER BY last_name, first_name";
    return pdo()->query($sql)->fetchAll();
  }




}
