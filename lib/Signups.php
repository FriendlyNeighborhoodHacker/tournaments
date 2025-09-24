<?php
require_once __DIR__ . '/../config.php';

class Signups {
  // Queries

  public static function myUpcomingSignups(int $userId): array {
    $sql = "select s.* from tournaments t
      inner join signups s on s.tournament_id = t.id
      inner join signup_members sm on sm.signup_id = s.id
      where start_date >= CURDATE() and sm.user_id = ?";
    $st = pdo()->prepare($sql);
    $st->execute([$userId]);
    return $st->fetchAll();
  }

  // Returns map: tournament_id => aggregated row with members + creator info
  public static function aggregateByTournament(array $signupIds): array {
    if (empty($signupIds)) return [];
    $placeholders = implode(',', array_fill(0, count($signupIds), '?'));
    $sql = "
SELECT s.*, sm.tournament_id, cb.first_name cb_fn, cb.last_name cb_ln,
       GROUP_CONCAT(CONCAT(u.first_name,' ',u.last_name) ORDER BY u.last_name SEPARATOR ', ') AS members
FROM signups s
  JOIN users cb ON cb.id = s.created_by_user_id
  JOIN signup_members sm ON sm.signup_id = s.id
  JOIN users u ON u.id = sm.user_id
WHERE s.id IN ($placeholders)
GROUP BY s.id
ORDER BY s.created_at ASC
";
    $st = pdo()->prepare($sql);
    $st->execute($signupIds);
    $rows = $st->fetchAll();
    $byT = [];
    foreach ($rows as $row) $byT[$row['tournament_id']] = $row;
    return $byT;
  }

  public static function teamsForTournament(int $tournamentId): array {
    $sql = "
      SELECT s.*, cb.first_name cb_fn, cb.last_name cb_ln,
             GROUP_CONCAT(CONCAT(u.first_name,' ',u.last_name) ORDER BY u.last_name SEPARATOR ', ') AS members
      FROM signups s
        JOIN users cb ON cb.id = s.created_by_user_id
        JOIN signup_members sm ON sm.signup_id = s.id
        JOIN users u ON u.id = sm.user_id
      WHERE s.tournament_id = ?
      GROUP BY s.id
      ORDER BY s.created_at ASC
    ";
    $st = pdo()->prepare($sql);
    $st->execute([$tournamentId]);
    return $st->fetchAll();
  }

  // List all members attending a tournament with their ride status (NULL/0/1)
  public static function membersWithRideForTournament(int $tournamentId): array {
    $sql = "
      SELECT u.id AS user_id,
             u.first_name, u.last_name,
             sm.has_ride
      FROM signup_members sm
      JOIN users u ON u.id = sm.user_id
      WHERE sm.tournament_id = ?
      ORDER BY u.last_name, u.first_name
    ";
    $st = pdo()->prepare($sql);
    $st->execute([$tournamentId]);
    return $st->fetchAll();
  }

  public static function userHasAny(int $userId): bool {
    $st = pdo()->prepare("SELECT 1 FROM signup_members WHERE user_id=? LIMIT 1");
    $st->execute([$userId]);
    return (bool)$st->fetchColumn();
  }

  public static function membersForSignup(int $signupId): array {
    $st = pdo()->prepare("SELECT user_id FROM signup_members WHERE signup_id=?");
    $st->execute([$signupId]);
    return array_column($st->fetchAll(), 'user_id');
  }

  // Map tournament_id => has_ride (NULL/0/1) for a given user across multiple tournaments
  public static function hasRideByTournamentForUser(array $tournamentIds, int $userId): array {
    if (empty($tournamentIds)) return [];
    $placeholders = implode(',', array_fill(0, count($tournamentIds), '?'));
    $params = $tournamentIds;
    $params[] = $userId;
    $sql = "SELECT tournament_id, has_ride FROM signup_members WHERE tournament_id IN ($placeholders) AND user_id = ?";
    $st = pdo()->prepare($sql);
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll() as $row) {
      $out[(int)$row['tournament_id']] = $row['has_ride'];
    }
    return $out;
  }

  // Write operations (business logic)

  public static function createTeam(int $tournamentId, int $creatorUserId, array $memberIds, bool $goMaverick, string $comment = ''): int {
    $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
    $count = count($memberIds);

    // Get tournament's team size maximum
    $st = pdo()->prepare("SELECT team_size_max FROM tournaments WHERE id = ?");
    $st->execute([$tournamentId]);
    $tournament = $st->fetch();
    if (!$tournament) {
      throw new DomainException('Tournament not found.');
    }
    $teamSizeMax = $tournament['team_size_max'];

    if ($goMaverick) {
      if ($count !== 1) throw new DomainException('Maverick signup must be exactly 1 person.');
    } else {
      // Apply tournament-specific team size limits
      if ($teamSizeMax !== null) {
        // Tournament has a specific team size maximum
        if ($count < 2 || $count > $teamSizeMax) {
          if ($teamSizeMax == 2) {
            throw new DomainException("Team signups must be exactly 2 people for this tournament.");
          } else {
            throw new DomainException("Team signups must be 2 to {$teamSizeMax} people total for this tournament.");
          }
        }
      } else {
        // Tournament has no specific limit, use default behavior
        if ($count < 2 || $count > 3) throw new DomainException('Team signups must be 2 or 3 people total.');
      }
    }

    // Check conflicts
    $placeholders = implode(',', array_fill(0, $count, '?'));
    $params = array_merge([$tournamentId], $memberIds);
    $st = pdo()->prepare("SELECT u.first_name, u.last_name FROM signup_members sm JOIN users u ON u.id=sm.user_id WHERE sm.tournament_id=? AND sm.user_id IN ($placeholders)");
    $st->execute($params);
    $conf = $st->fetchAll();
    if ($conf) {
      $names = array_map(fn($r)=>$r['first_name'].' '.$r['last_name'], $conf);
      throw new DomainException('Already signed up: '.implode(', ', $names));
    }

    // Create signup transactionally
    pdo()->beginTransaction();
    try {
      $st1 = pdo()->prepare("INSERT INTO signups (tournament_id, created_by_user_id, go_maverick, comment) VALUES (?,?,?,?)");
      $st1->execute([$tournamentId, $creatorUserId, $goMaverick ? 1 : 0, $comment]);
      $signupId = (int)pdo()->lastInsertId();

      $stm = pdo()->prepare("INSERT INTO signup_members (signup_id, tournament_id, user_id) VALUES (?,?,?)");
      foreach ($memberIds as $uid) $stm->execute([$signupId, $tournamentId, $uid]);

      pdo()->commit();
      return $signupId;
    } catch (Throwable $e) {
      pdo()->rollBack();
      throw $e;
    }
  }

  public static function deleteTeamIfAllowed(int $signupId, int $requestingUserId, bool $isAdmin): bool {
    $st = pdo()->prepare("SELECT s.*, EXISTS(SELECT 1 FROM signup_members sm WHERE sm.signup_id=s.id AND sm.user_id=?) AS am_member FROM signups s WHERE s.id=?");
    $st->execute([$requestingUserId, $signupId]);
    $s = $st->fetch();
    if (!$s) return false;
    if (!$isAdmin && !$s['am_member']) return false;
    $del = pdo()->prepare("DELETE FROM signups WHERE id=?");
    return $del->execute([$signupId]);
  }

  public static function replaceTeam(int $signupId, int $tournamentId, array $memberIds, bool $goMaverick, string $comment): bool {
    $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
    $cnt = count($memberIds);
    
    // Get tournament's team size maximum
    $st = pdo()->prepare("SELECT team_size_max FROM tournaments WHERE id = ?");
    $st->execute([$tournamentId]);
    $tournament = $st->fetch();
    if (!$tournament) {
      throw new DomainException('Tournament not found.');
    }
    $teamSizeMax = $tournament['team_size_max'];

    if ($goMaverick) {
      if ($cnt !== 1) throw new DomainException('Maverick signup must be exactly 1 person.');
    } else {
      // Apply tournament-specific team size limits
      if ($teamSizeMax !== null) {
        // Tournament has a specific team size maximum
        if ($cnt < 2 || $cnt > $teamSizeMax) {
          if ($teamSizeMax == 2) {
            throw new DomainException("Team signups must be exactly 2 people for this tournament.");
          } else {
            throw new DomainException("Team signups must be 2 to {$teamSizeMax} people total for this tournament.");
          }
        }
      } else {
        // Tournament has no specific limit, use default behavior
        if ($cnt < 2 || $cnt > 3) throw new DomainException('Team signups must be 2 or 3 people total.');
      }
    }

    // Conflicts excluding current signup
    $placeholders = implode(',', array_fill(0, $cnt, '?'));
    $params = array_merge([$tournamentId, $signupId], $memberIds);
    $stx = pdo()->prepare("
      SELECT u.first_name,u.last_name
      FROM signup_members sm 
      JOIN users u ON u.id=sm.user_id
      WHERE sm.tournament_id=? AND sm.signup_id<>? AND sm.user_id IN ($placeholders)
    ");
    $stx->execute($params);
    $conf = $stx->fetchAll();
    if ($conf) {
      $names = implode(', ', array_map(fn($r)=>$r['first_name'].' '.$r['last_name'], $conf));
      throw new DomainException('Already signed: '.$names);
    }

    pdo()->beginTransaction();
    try {
      pdo()->prepare("UPDATE signups SET go_maverick=?, comment=? WHERE id=?")->execute([$goMaverick ? 1 : 0, $comment, $signupId]);
      pdo()->prepare("DELETE FROM signup_members WHERE signup_id=?")->execute([$signupId]);
      $stm = pdo()->prepare("INSERT INTO signup_members (signup_id,tournament_id,user_id) VALUES (?,?,?)");
      foreach ($memberIds as $uid) $stm->execute([$signupId, $tournamentId, $uid]);
      pdo()->commit();
      return true;
    } catch (Throwable $e) {
      pdo()->rollBack();
      throw $e;
    }
  }
}
