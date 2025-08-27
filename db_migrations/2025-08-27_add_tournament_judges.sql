-- Migration: Add tournament_judges table (judges attached directly to tournaments)
-- Applies to MySQL.

CREATE TABLE IF NOT EXISTS tournament_judges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  judge_id INT NOT NULL,
  CONSTRAINT fk_tj_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_tj_judge     FOREIGN KEY (judge_id)      REFERENCES judges(id)      ON DELETE CASCADE,
  UNIQUE KEY uniq_tj (tournament_id, judge_id)
) ENGINE=InnoDB;

-- Helpful indexes
CREATE INDEX idx_tj_tournament_id ON tournament_judges(tournament_id);
CREATE INDEX idx_tj_judge_id ON tournament_judges(judge_id);
