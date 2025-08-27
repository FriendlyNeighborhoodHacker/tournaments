<?php
require_once __DIR__ . '/../config.php';

class Judges {
  // CRUD

  public static function listAll(): array {
    $sql = "SELECT j.*, u.first_name AS s_fn, u.last_name AS s_ln
            FROM judges j
            JOIN users u ON u.id = j.sponsor_id
            ORDER BY j.last_name, j.first_name";
    return pdo()->query($sql)->fetchAll();
  }

  public static function create(string $first, string $last, ?string $email, ?string $phone, int $sponsorId): int {
    $st = pdo()->prepare("INSERT INTO judges (first_name,last_name,email,phone,sponsor_id) VALUES (?,?,?,?,?)");
    $st->execute([trim($first), trim($last), $email ?: null, $phone ?: null, $sponsorId]);
    return (int)pdo()->lastInsertId();
  }

  public static function update(int $id, string $first, string $last, ?string $email, ?string $phone, ?int $sponsorId = null): bool {
    if ($sponsorId !== null) {
      $st = pdo()->prepare("UPDATE judges SET first_name=?, last_name=?, email=?, phone=?, sponsor_id=? WHERE id=?");
      return $st->execute([trim($first), trim($last), $email ?: null, $phone ?: null, $sponsorId, $id]);
    } else {
      $st = pdo()->prepare("UPDATE judges SET first_name=?, last_name=?, email=?, phone=? WHERE id=?");
      return $st->execute([trim($first), trim($last), $email ?: null, $phone ?: null, $id]);
    }
  }

  public static function find(int $id): ?array {
    $st = pdo()->prepare("SELECT * FROM judges WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function delete(int $id): bool {
    $st = pdo()->prepare("DELETE FROM judges WHERE id=?");
    return $st->execute([$id]);
  }

  // Associations to signups

  public static function judgesForSignup(int $signupId): array {
    $sql = "SELECT j.*
            FROM signup_judges sj
            JOIN judges j ON j.id = sj.judge_id
            WHERE sj.signup_id = ?
            ORDER BY j.last_name, j.first_name";
    $st = pdo()->prepare($sql);
    $st->execute([$signupId]);
    return $st->fetchAll();
  }

  // For a tournament: list unique judges across all its signups
  public static function judgesForTournament(int $tournamentId): array {
    $sql = "SELECT DISTINCT j.id, j.first_name, j.last_name
            FROM signup_judges sj
            JOIN judges j ON j.id = sj.judge_id
            JOIN signups s ON s.id = sj.signup_id
            WHERE s.tournament_id = ?
            ORDER BY j.last_name, j.first_name";
    $st = pdo()->prepare($sql);
    $st->execute([$tournamentId]);
    return $st->fetchAll();
  }

  // Map signup_id => array of judges for a tournament (single query)
  public static function judgesBySignupForTournament(int $tournamentId): array {
    $sql = "SELECT sj.signup_id, j.id AS judge_id, j.first_name, j.last_name
            FROM signup_judges sj
            JOIN judges j ON j.id = sj.judge_id
            JOIN signups s ON s.id = sj.signup_id
            WHERE s.tournament_id = ?
            ORDER BY j.last_name, j.first_name";
    $st = pdo()->prepare($sql);
    $st->execute([$tournamentId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
      $sid = (int)$r['signup_id'];
      if (!isset($out[$sid])) $out[$sid] = [];
      $out[$sid][] = $r;
    }
    return $out;
  }

  public static function attachToSignup(int $signupId, int $judgeId): bool {
    $st = pdo()->prepare("INSERT IGNORE INTO signup_judges (signup_id, judge_id) VALUES (?,?)");
    return $st->execute([$signupId, $judgeId]);
  }

  public static function detachFromSignup(int $signupId, int $judgeId): bool {
    $st = pdo()->prepare("DELETE FROM signup_judges WHERE signup_id=? AND judge_id=?");
    return $st->execute([$signupId, $judgeId]);
  }

  // List judges sponsored by any of the given user IDs
  public static function listBySponsors(array $userIds): array {
    $ids = array_values(array_unique(array_map('intval', $userIds)));
    if (empty($ids)) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT * FROM judges WHERE sponsor_id IN ($placeholders) ORDER BY last_name, first_name";
    $st = pdo()->prepare($sql);
    $st->execute($ids);
    return $st->fetchAll();
  }
}
