<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

class JudgeManagement {
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

  private static function intOrNull($v): ?int {
    if ($v === null) return null;
    $s = trim((string)$v);
    if ($s === '') return null;
    return (int)$s;
  }

  // Placeholder for future logging
  private static function log(string $action, array $details = []): void {
    // no-op for now
  }

  // Judges CRUD
  public static function create(array $data): int {
    $first = self::str($data['first_name'] ?? '');
    $last  = self::str($data['last_name'] ?? '');
    $email = self::nullableStr($data['email'] ?? null);
    $phone = self::nullableStr($data['phone'] ?? null);
    $sponsorId = (int)($data['sponsor_id'] ?? 0);

    if ($first === '' || $last === '') {
      throw new InvalidArgumentException('First and last name are required.');
    }
    if ($sponsorId <= 0) {
      throw new InvalidArgumentException('Sponsor is required.');
    }

    $st = self::pdo()->prepare("INSERT INTO judges (first_name,last_name,email,phone,sponsor_id) VALUES (?,?,?,?,?)");
    $st->execute([$first, $last, $email, $phone, $sponsorId]);
    $id = (int)self::pdo()->lastInsertId();
    self::log('judge.create', ['id' => $id, 'sponsor_id' => $sponsorId]);
    return $id;
  }

  public static function update(int $id, array $data): bool {
    $first = self::str($data['first_name'] ?? '');
    $last  = self::str($data['last_name'] ?? '');
    $email = self::nullableStr($data['email'] ?? null);
    $phone = self::nullableStr($data['phone'] ?? null);
    $sponsorId = self::intOrNull($data['sponsor_id'] ?? null);

    if ($first === '' || $last === '') {
      throw new InvalidArgumentException('First and last name are required.');
    }

    if ($sponsorId !== null) {
      $st = self::pdo()->prepare("UPDATE judges SET first_name=?, last_name=?, email=?, phone=?, sponsor_id=? WHERE id=?");
      $ok = $st->execute([$first, $last, $email, $phone, $sponsorId, $id]);
    } else {
      $st = self::pdo()->prepare("UPDATE judges SET first_name=?, last_name=?, email=?, phone=? WHERE id=?");
      $ok = $st->execute([$first, $last, $email, $phone, $id]);
    }
    if ($ok) self::log('judge.update', ['id' => $id]);
    return $ok;
  }

  public static function delete(int $id): bool {
    $st = self::pdo()->prepare("DELETE FROM judges WHERE id=?");
    $ok = $st->execute([$id]);
    if ($ok) self::log('judge.delete', ['id' => $id]);
    return $ok;
  }

  // Signup associations
  public static function attachToSignup(int $signupId, int $judgeId): bool {
    $st = self::pdo()->prepare("INSERT IGNORE INTO signup_judges (signup_id, judge_id) VALUES (?,?)");
    $ok = $st->execute([$signupId, $judgeId]);
    if ($ok) self::log('judge.attach_signup', ['signup_id' => $signupId, 'judge_id' => $judgeId]);
    return $ok;
  }

  public static function detachFromSignup(int $signupId, int $judgeId): bool {
    $st = self::pdo()->prepare("DELETE FROM signup_judges WHERE signup_id=? AND judge_id=?");
    $ok = $st->execute([$signupId, $judgeId]);
    if ($ok) self::log('judge.detach_signup', ['signup_id' => $signupId, 'judge_id' => $judgeId]);
    return $ok;
  }

  // Replace a signup's judges, and enforce rule to remove overlapping tournament_judges
  public static function setJudgesForSignup(int $signupId, array $judgeIds): bool {
    $pdo = self::pdo();
    $judgeIds = array_values(array_unique(array_map('intval', $judgeIds)));
    try {
      // Find tournament for this signup
      $stTid = $pdo->prepare('SELECT tournament_id FROM signups WHERE id=?');
      $stTid->execute([$signupId]);
      $tournamentId = (int)$stTid->fetchColumn();

      $pdo->beginTransaction();

      // Clear current
      $pdo->prepare('DELETE FROM signup_judges WHERE signup_id=?')->execute([$signupId]);

      // Insert new set
      if (!empty($judgeIds)) {
        $ins = $pdo->prepare('INSERT INTO signup_judges (signup_id, judge_id) VALUES (?,?)');
        foreach ($judgeIds as $jid) {
          if ($jid > 0) $ins->execute([$signupId, $jid]);
        }
      }

      // Remove overlapping tournament_judges if any of these judges are attached to this tournament via tournament_judges
      if (!empty($judgeIds) && $tournamentId > 0) {
        $in = implode(',', array_fill(0, count($judgeIds), '?'));
        $params = array_merge([$tournamentId], $judgeIds);
        $pdo->prepare("DELETE FROM tournament_judges WHERE tournament_id=? AND judge_id IN ($in)")->execute($params);
      }

      $pdo->commit();
      self::log('judge.set_signup_list', ['signup_id' => $signupId, 'count' => count($judgeIds)]);
      return true;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      throw $e;
    }
  }

  // Tournament-level associations
  public static function attachTournamentJudge(int $tournamentId, int $judgeId): bool {
    $st = self::pdo()->prepare("INSERT IGNORE INTO tournament_judges (tournament_id, judge_id) VALUES (?,?)");
    $ok = $st->execute([$tournamentId, $judgeId]);
    if ($ok) self::log('judge.attach_tournament', ['tournament_id' => $tournamentId, 'judge_id' => $judgeId]);
    return $ok;
  }

  public static function detachTournamentJudge(int $tournamentId, int $judgeId): bool {
    $st = self::pdo()->prepare("DELETE FROM tournament_judges WHERE tournament_id=? AND judge_id=?");
    $ok = $st->execute([$tournamentId, $judgeId]);
    if ($ok) self::log('judge.detach_tournament', ['tournament_id' => $tournamentId, 'judge_id' => $judgeId]);
    return $ok;
  }
}
