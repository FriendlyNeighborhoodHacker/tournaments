<?php
require_once __DIR__ . '/../config.php';

/**
 * Tournaments data access - read-only.
 * Write operations (create/update/delete) are handled by TournamentManagement.
 */
class Tournaments {
  public static function upcoming(): array {
    $sql = "SELECT * FROM tournaments WHERE start_date >= (CURDATE() - INTERVAL 1 DAY) ORDER BY start_date ASC";
    return pdo()->query($sql)->fetchAll();
  }

  public static function allAsc(): array {
    $sql = "SELECT * FROM tournaments ORDER BY start_date ASC";
    return pdo()->query($sql)->fetchAll();
  }

  public static function find(int $id): ?array {
    $st = pdo()->prepare("SELECT * FROM tournaments WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }




  // Previous tournaments: those that have ended before today
  public static function previousDesc(): array {
    $sql = "SELECT * FROM tournaments WHERE end_date < CURDATE() ORDER BY start_date DESC";
    return pdo()->query($sql)->fetchAll();
  }

  // Recent previous tournaments limited by count
  public static function previousRecent(int $limit = 3): array {
    $limit = max(1, (int)$limit);
    $st = pdo()->prepare("SELECT * FROM tournaments WHERE end_date < CURDATE() ORDER BY start_date DESC LIMIT ?");
    // PDO emulation may require integer param binding
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }
}
