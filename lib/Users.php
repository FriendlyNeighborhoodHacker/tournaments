<?php
require_once __DIR__ . '/../config.php';

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

  public static function create(string $first, string $last, string $email, ?string $phone, string $passwordHash, bool $isCoach = false, bool $isAdmin = false): int {
    $st = pdo()->prepare("INSERT INTO users (first_name,last_name,email,phone,password_hash,is_coach,is_admin) VALUES (?,?,?,?,?,?,?)");
    $st->execute([trim($first), trim($last), strtolower(trim($email)), $phone, $passwordHash, $isCoach ? 1 : 0, $isAdmin ? 1 : 0]);
    return (int)pdo()->lastInsertId();
  }

  public static function update(int $id, string $first, string $last, string $email, ?string $phone, ?string $passwordHash, bool $isCoach, bool $isAdmin): bool {
    if ($passwordHash) {
      $st = pdo()->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, password_hash=?, is_coach=?, is_admin=? WHERE id=?");
      return $st->execute([trim($first), trim($last), strtolower(trim($email)), $phone, $passwordHash, $isCoach ? 1 : 0, $isAdmin ? 1 : 0, $id]);
    } else {
      $st = pdo()->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, is_coach=?, is_admin=? WHERE id=?");
      return $st->execute([trim($first), trim($last), strtolower(trim($email)), $phone, $isCoach ? 1 : 0, $isAdmin ? 1 : 0, $id]);
    }
  }

  public static function delete(int $id): bool {
    $st = pdo()->prepare("DELETE FROM users WHERE id=?");
    return $st->execute([$id]);
  }

  public static function updatePassword(int $id, string $newHash): bool {
    $st = pdo()->prepare("UPDATE users SET password_hash=? WHERE id=?");
    return $st->execute([$newHash, $id]);
  }
}
