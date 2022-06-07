/* SQL to update all the historical tables from the current season */

SET @end_year = (SELECT YEAR(`match_date`) FROM `slc_fixture_date`
	ORDER BY `match_date` DESC LIMIT 1);

SELECT 'Inserting cup draws' as '';
INSERT INTO `slh_cup_draw`
SELECT @end_year, `comp_id`, `round`, `match_num`, `team1`, `team2`, `team1_goals`,
	`team2_goals`, `result_extra`, `home_team`
FROM `slc_cup_draw`;

SELECT 'Inserting cup years' as '';
INSERT INTO `slh_cup_year`
SELECT DISTINCT c.group_id, @end_year, MAX(a.max_rounds)
	FROM
	(SELECT cd.comp_id, MAX(cd.round) AS max_rounds
		FROM slc_cup_draw AS cd
		GROUP BY cd.comp_id) AS a, sl_competition c
	WHERE c.id = a.comp_id
	GROUP BY c.group_id
	ORDER BY c.group_id;

SELECT 'Inserting league years' as '';
INSERT INTO `slh_league_year`
	SELECT DISTINCT c.group_id, @end_year
	FROM (SELECT DISTINCT comp_id FROM slc_table) AS t,
		sl_competition AS c
	WHERE c.id = t.comp_id
	ORDER BY c.group_id;

SELECT 'Inserting results' as '';
INSERT INTO `slh_result`
(`year`,`match_date`,`comp_id`,`comp_id2`,`competition`,`home`,`away`,`home_goals`,`away_goals`,
`result`,`home_points`,`away_points`,`points_multi`)
SELECT @end_year,	`match_date`, `comp_id`, `comp_id2`,
	`competition`, `home`, `away`, `home_goals`, `away_goals`, `result`,
	`home_points`, `away_points`, `points_multi`
FROM `slc_fixture` ORDER BY `id`;

SELECT 'Inserting league tables' as '';
INSERT INTO `slh_table`
SELECT @end_year, `comp_id`, `position`, `team`, `played`, `won`, `drawn`, `lost`,
	`goals_for`, `goals_against`, `goal_avg`, `points_deducted`, `points`, NULL,
	`divider`, `tiebreaker`
FROM `slc_table`;

SELECT 'Inserting deductions' as '';
INSERT INTO `slh_deduction`
(`year`, `comp_id`, `team`, `penalty`, `deduct_date`, `reason`)
SELECT @end_year, `comp_id`, `team`, `penalty`, `deduct_date`, `reason`
from `slc_deduction`;

-- League winners
SELECT 'Inserting league winners' as '';
INSERT INTO `slh_winner`
(`comp_id`,`year`,`winner`,`runner_up`,`result`,`has_data`)
SELECT comp_id, @end_year, team, NULL, NULL, 1
FROM slc_table WHERE position = 1 AND played > 0
ORDER BY comp_id;

-- Flags winners
SELECT 'Inserting flags winners' as '';
INSERT INTO `slh_winner`
(`comp_id`,`year`,`winner`,`runner_up`,`result`,`has_data`)
SELECT comp_id, @end_year,
	CASE
		WHEN team1_goals IS NULL THEN 'Void'
		WHEN team1_goals > team2_goals THEN team1
		ELSE team2 END,
	CASE
		WHEN team1_goals IS NULL THEN NULL
		WHEN team1_goals > team2_goals THEN team2
		ELSE team1 END,
	CASE
		WHEN team1_goals IS NULL THEN NULL
		WHEN team1_goals > team2_goals THEN CONCAT(team1_goals, ' - ', team2_goals)
		ELSE CONCAT(team2_goals, ' - ', team1_goals) END,
	1
FROM slc_cup_draw
WHERE home_team = 0 -- FIXME: works so long as the final is the only round without home or away
ORDER BY comp_id;

-- Varsity winners
-- SELECT 'Inserting varsity winners' as '';
-- INSERT IGNORE INTO `slh_winner`
-- (`comp_id`,`year`,`winner`,`runner_up`,`result`,`has_data`)
-- SELECT comp_id, @end_year, 
-- 	CASE
-- 		WHEN home_goals = away_goals THEN 'Drawn'
-- 		WHEN home_goals > away_goals THEN home
--		ELSE away END,
-- 	CASE
-- 		WHEN home_goals = away_goals THEN NULL
-- 		WHEN home_goals > away_goals THEN away
--		 ELSE home END,
-- 	CASE
-- 		WHEN home_goals > away_goals THEN CONCAT(home_goals, ' - ', away_goals)
--		ELSE CONCAT(away_goals, ' - ', home_goals) END,
--	 0
-- FROM slc_fixture f, sl_competition c
-- WHERE c.name = 'Varsity'
-- AND f.home IN ('Oxford Uni','Cambridge Uni')
-- AND f.home_goals IS NOT NULL
-- AND f.comp_id = c.id;

SELECT 'History database updates completed' as '';
