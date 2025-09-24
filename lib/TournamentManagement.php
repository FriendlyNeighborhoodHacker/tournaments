<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

class TournamentManagement {
  private static function pdo(): PDO {
    return pdo();
  }

  private static function str(string $v): string {
    return trim($v);
  }

  private static function nullableStr(?string $v): ?string {
    if ($v === null) return null;
    $v = trim($v);
    return $v === '' ? null : $v;
  }

  private static function parseDate(?string $date): ?string {
    $date = self::nullableStr($date);
    if ($date === null) return null;
    // Expect YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      throw new InvalidArgumentException('Date must be in YYYY-MM-DD format.');
    }
    [$y, $m, $d] = array_map('intval', explode('-', $date));
    if (!checkdate($m, $d, $y)) {
      throw new InvalidArgumentException('Invalid calendar date provided.');
    }
    return $date;
  }

  private static function parsePositiveIntOrNull($val): ?int {
    if ($val === null) return null;
    $s = trim((string)$val);
    if ($s === '') return null;
    $i = (int)$s;
    if ($i < 1) {
      throw new InvalidArgumentException('Max teams must be at least 1 when provided.');
    }
    return $i;
  }

  private static function parseTeamSizeMaxOrNull($val): ?int {
    if ($val === null) return null;
    $s = trim((string)$val);
    if ($s === '') return null;
    $i = (int)$s;
    if ($i < 1) {
      throw new InvalidArgumentException('Team size maximum must be at least 1 when provided.');
    }
    return $i;
  }

  // Placeholder for future activity logging
  private static function log(string $action, ?int $id, array $details = []): void {
    // no-op for now; implement later
  }

  public static function create(array $data): int {
    $name = self::str($data['name'] ?? '');
    $location = self::str($data['location'] ?? '');
    $start = self::parseDate($data['start_date'] ?? null);
    $end = self::parseDate($data['end_date'] ?? null);
    $maxTeams = self::parsePositiveIntOrNull($data['max_teams'] ?? null);
    $teamSizeMax = self::parseTeamSizeMaxOrNull($data['team_size_max'] ?? null);
    $deadline = self::parseDate($data['signup_deadline'] ?? null);

    if ($name === '' || $location === '' || $start === null || $end === null) {
      throw new InvalidArgumentException('Name, location, start date, and end date are required.');
    }
    if ($start > $end) {
      throw new InvalidArgumentException('Start date must be on or before end date.');
    }

    $st = self::pdo()->prepare("INSERT INTO tournaments (name,location,start_date,end_date,max_teams,team_size_max,signup_deadline) VALUES (?,?,?,?,?,?,?)");
    $st->execute([$name, $location, $start, $end, $maxTeams, $teamSizeMax, $deadline]);
    $id = (int)self::pdo()->lastInsertId();
    self::log('tournament.create', $id, ['name' => $name]);
    return $id;
  }

  public static function update(int $id, array $data): bool {
    $name = self::str($data['name'] ?? '');
    $location = self::str($data['location'] ?? '');
    $start = self::parseDate($data['start_date'] ?? null);
    $end = self::parseDate($data['end_date'] ?? null);
    $maxTeams = self::parsePositiveIntOrNull($data['max_teams'] ?? null);
    $teamSizeMax = self::parseTeamSizeMaxOrNull($data['team_size_max'] ?? null);
    $deadline = self::parseDate($data['signup_deadline'] ?? null);

    if ($name === '' || $location === '' || $start === null || $end === null) {
      throw new InvalidArgumentException('Name, location, start date, and end date are required.');
    }
    if ($start > $end) {
      throw new InvalidArgumentException('Start date must be on or before end date.');
    }

    $st = self::pdo()->prepare("UPDATE tournaments SET name=?, location=?, start_date=?, end_date=?, max_teams=?, team_size_max=?, signup_deadline=? WHERE id=?");
    $ok = $st->execute([$name, $location, $start, $end, $maxTeams, $teamSizeMax, $deadline, $id]);
    if ($ok) self::log('tournament.update', $id, ['name' => $name]);
    return $ok;
  }

  public static function delete(int $id): bool {
    $st = self::pdo()->prepare("DELETE FROM tournaments WHERE id=?");
    $ok = $st->execute([$id]);
    if ($ok) self::log('tournament.delete', $id);
    return $ok;
  }
}
