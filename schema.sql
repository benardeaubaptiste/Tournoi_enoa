-- Schéma de la base tournoi_enoa (structure uniquement, sans données).
-- Exporté depuis l'instance de développement pour que le projet reste reproductible
-- sur une nouvelle machine, alors qu'aucun fichier SQL n'existait auparavant.
--
-- Utilisation :
--   CREATE DATABASE tournoi_enoa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   mysql -u root tournoi_enoa < schema.sql
--   -- puis créer un compte admin, ex. :
--   INSERT INTO admins (username, password_hash, display_name, role) VALUES
--     ('admin', '<sortie de password_hash("...", PASSWORD_BCRYPT)>', 'Administrateur', 'admin');
--   -- les comptes créés depuis la page d'inscription du site reçoivent automatiquement
--   -- le rôle 'gestionnaire' (mêmes droits que 'admin', juste une étiquette différente).

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Tables créées dans l'ordre des dépendances (clés étrangères) pour que
-- l'import fonctionne même si FOREIGN_KEY_CHECKS reste actif (ex. phpMyAdmin).
DROP VIEW IF EXISTS `tournament_dashboard`;
DROP TABLE IF EXISTS `admin_logs`;
DROP TABLE IF EXISTS `rankings`;
DROP TABLE IF EXISTS `matches`;
DROP TABLE IF EXISTS `pool_teams`;
DROP TABLE IF EXISTS `pools`;
DROP TABLE IF EXISTS `players`;
DROP TABLE IF EXISTS `teams`;
DROP TABLE IF EXISTS `courts`;
DROP TABLE IF EXISTS `tournament_settings`;
DROP TABLE IF EXISTS `tournaments`;
DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','gestionnaire') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gestionnaire',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tournaments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` int unsigned NOT NULL,
  `titre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_tournoi` date NOT NULL,
  `date_limite_inscriptions` date DEFAULT NULL,
  `date_limite_paiement` date DEFAULT NULL,
  `statut` enum('draft','inscriptions','poules_a_generer','poules_validees','planning_genere','en_cours','phase_finale','termine','archive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournament_numero` (`numero`),
  KEY `idx_tournament_date` (`date_tournoi`),
  KEY `idx_tournament_statut` (`statut`),
  KEY `fk_tournament_created_by` (`created_by`),
  CONSTRAINT `fk_tournament_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tournament_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint unsigned NOT NULL,
  `heure_debut_tournoi` time NOT NULL DEFAULT '09:00:00',
  `heure_fin_tournoi` time NOT NULL DEFAULT '18:00:00',
  `nombre_max_equipes` tinyint unsigned NOT NULL DEFAULT '16',
  `nombre_poules` tinyint unsigned NOT NULL DEFAULT '4',
  `mode_repartition` enum('ordre_inscription','aleatoire') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ordre_inscription',
  `nombre_terrains` tinyint unsigned NOT NULL DEFAULT '3',
  `heure_debut_qualifications` time DEFAULT NULL,
  `heure_fin_qualifications` time DEFAULT NULL,
  `duree_match_qualification` smallint unsigned NOT NULL DEFAULT '15',
  `temps_entre_matchs_qualification` smallint unsigned NOT NULL DEFAULT '5',
  `heure_debut_phase_finale` time DEFAULT NULL,
  `heure_fin_phase_finale` time DEFAULT NULL,
  `duree_match_phase_finale` smallint unsigned DEFAULT NULL,
  `temps_entre_matchs_phase_finale` smallint unsigned NOT NULL DEFAULT '5',
  `duree_grande_finale` smallint unsigned NOT NULL DEFAULT '20',
  `prix_par_joueur` decimal(10,2) NOT NULL DEFAULT '0.00',
  `max_matchs_consecutifs_qualifications` tinyint unsigned NOT NULL DEFAULT '2',
  `max_matchs_consecutifs_phase_finale` tinyint unsigned NOT NULL DEFAULT '1',
  `points_victoire` decimal(5,2) NOT NULL DEFAULT '3.00',
  `points_defaite` decimal(5,2) NOT NULL DEFAULT '1.00',
  `nombre_equipes_upper` tinyint unsigned NOT NULL DEFAULT '8',
  `nombre_equipes_lower` tinyint unsigned NOT NULL DEFAULT '8',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_tournament` (`tournament_id`),
  CONSTRAINT `fk_settings_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_settings_break_final` CHECK ((`temps_entre_matchs_phase_finale` >= 0)),
  CONSTRAINT `chk_settings_break_qualification` CHECK ((`temps_entre_matchs_qualification` >= 0)),
  CONSTRAINT `chk_settings_consecutive_final` CHECK ((`max_matchs_consecutifs_phase_finale` >= 1)),
  CONSTRAINT `chk_settings_consecutive_qual` CHECK ((`max_matchs_consecutifs_qualifications` >= 1)),
  CONSTRAINT `chk_settings_courts` CHECK ((`nombre_terrains` between 1 and 20)),
  CONSTRAINT `chk_settings_grand_final_duration` CHECK ((`duree_grande_finale` > 0)),
  CONSTRAINT `chk_settings_match_duration` CHECK ((`duree_match_qualification` > 0)),
  CONSTRAINT `chk_settings_max_teams` CHECK ((`nombre_max_equipes` between 8 and 20)),
  CONSTRAINT `chk_settings_pools` CHECK ((`nombre_poules` between 2 and 4)),
  CONSTRAINT `chk_settings_price` CHECK ((`prix_par_joueur` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint unsigned NOT NULL,
  `numero` tinyint unsigned NOT NULL,
  `nom` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_court_tournament_numero` (`tournament_id`,`numero`),
  KEY `idx_court_tournament` (`tournament_id`),
  CONSTRAINT `fk_court_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint unsigned NOT NULL,
  `numero_inscription` smallint unsigned NOT NULL,
  `nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `club` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_joueurs` tinyint unsigned NOT NULL DEFAULT '1',
  `prix_par_joueur` decimal(10,2) NOT NULL,
  `montant_a_payer` decimal(10,2) NOT NULL,
  `statut_paiement` enum('impaye','partiel','paye') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'impaye',
  `montant_paye` decimal(10,2) NOT NULL DEFAULT '0.00',
  `date_paiement` datetime DEFAULT NULL,
  `statut` enum('inscrite','confirmee','forfait','elimine') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inscrite',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_team_registration_number` (`tournament_id`,`numero_inscription`),
  KEY `idx_team_tournament` (`tournament_id`),
  KEY `idx_team_payment` (`tournament_id`,`statut_paiement`),
  KEY `idx_team_name` (`tournament_id`,`nom`),
  CONSTRAINT `fk_team_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_team_amount` CHECK ((`montant_a_payer` >= 0)),
  CONSTRAINT `chk_team_player_price` CHECK ((`prix_par_joueur` >= 0)),
  CONSTRAINT `chk_team_players` CHECK ((`nombre_joueurs` >= 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `players` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_maillot` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_players_team` (`team_id`),
  CONSTRAINT `fk_players_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pools` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint unsigned NOT NULL,
  `numero` tinyint unsigned NOT NULL,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('draft','validee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pool_tournament_numero` (`tournament_id`,`numero`),
  KEY `idx_pool_tournament` (`tournament_id`),
  CONSTRAINT `fk_pool_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_pool_numero` CHECK ((`numero` between 1 and 4))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pool_teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pool_id` bigint unsigned NOT NULL,
  `team_id` bigint unsigned NOT NULL,
  `position` tinyint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pool_team` (`pool_id`,`team_id`),
  UNIQUE KEY `uq_pool_position` (`pool_id`,`position`),
  KEY `idx_pool_team_team` (`team_id`),
  CONSTRAINT `fk_pool_team_pool` FOREIGN KEY (`pool_id`) REFERENCES `pools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pool_team_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_pool_team_position` CHECK ((`position` >= 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint unsigned NOT NULL,
  `phase` enum('qualification','upper','lower','grand_final') COLLATE utf8mb4_unicode_ci NOT NULL,
  `pool_id` bigint unsigned DEFAULT NULL,
  `round_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `round_number` tinyint unsigned DEFAULT NULL,
  `match_number` int unsigned NOT NULL,
  `team_a_id` bigint unsigned DEFAULT NULL,
  `team_b_id` bigint unsigned DEFAULT NULL,
  `winner_team_id` bigint unsigned DEFAULT NULL,
  `loser_team_id` bigint unsigned DEFAULT NULL,
  `court_id` bigint unsigned DEFAULT NULL,
  `scheduled_start` datetime DEFAULT NULL,
  `scheduled_end` datetime DEFAULT NULL,
  `duration_seconds` int unsigned DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `status` enum('pending','scheduled','live','finished','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `score_team_a` tinyint unsigned NOT NULL DEFAULT '0',
  `score_team_b` tinyint unsigned NOT NULL DEFAULT '0',
  `next_match_id` bigint unsigned DEFAULT NULL,
  `next_match_slot` enum('team_a','team_b') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loser_next_match_id` bigint unsigned DEFAULT NULL,
  `loser_next_match_slot` enum('team_a','team_b') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_match_tournament_number` (`tournament_id`,`match_number`),
  KEY `fk_matches_team_a` (`team_a_id`),
  KEY `fk_matches_team_b` (`team_b_id`),
  KEY `fk_matches_winner` (`winner_team_id`),
  KEY `fk_matches_loser` (`loser_team_id`),
  KEY `fk_matches_next` (`next_match_id`),
  KEY `fk_matches_loser_next` (`loser_next_match_id`),
  KEY `idx_matches_tournament` (`tournament_id`),
  KEY `idx_matches_phase` (`tournament_id`,`phase`),
  KEY `idx_matches_pool` (`pool_id`),
  KEY `idx_matches_schedule` (`tournament_id`,`scheduled_start`),
  KEY `idx_matches_status` (`tournament_id`,`status`),
  KEY `idx_matches_court` (`court_id`,`scheduled_start`),
  CONSTRAINT `fk_matches_court` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_matches_loser` FOREIGN KEY (`loser_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_matches_loser_next` FOREIGN KEY (`loser_next_match_id`) REFERENCES `matches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_matches_next` FOREIGN KEY (`next_match_id`) REFERENCES `matches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_matches_pool` FOREIGN KEY (`pool_id`) REFERENCES `pools` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_matches_team_a` FOREIGN KEY (`team_a_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_matches_team_b` FOREIGN KEY (`team_b_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_matches_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_matches_winner` FOREIGN KEY (`winner_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rankings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint unsigned NOT NULL,
  `pool_id` bigint unsigned DEFAULT NULL,
  `team_id` bigint unsigned NOT NULL,
  `phase` enum('qualification','global','final') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'qualification',
  `position` tinyint unsigned NOT NULL,
  `matchs_joues` int unsigned NOT NULL DEFAULT '0',
  `victoires` int unsigned NOT NULL DEFAULT '0',
  `defaites` int unsigned NOT NULL DEFAULT '0',
  `points` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sets_gagnes` int unsigned NOT NULL DEFAULT '0',
  `sets_perdus` int unsigned NOT NULL DEFAULT '0',
  `difference_sets` int NOT NULL DEFAULT '0',
  `points_marques` int unsigned NOT NULL DEFAULT '0',
  `points_encaisses` int unsigned NOT NULL DEFAULT '0',
  `difference_points` int NOT NULL DEFAULT '0',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ranking_team_phase` (`tournament_id`,`pool_id`,`team_id`,`phase`),
  KEY `idx_rankings_tournament` (`tournament_id`),
  KEY `idx_rankings_pool` (`pool_id`),
  KEY `idx_rankings_team` (`team_id`),
  CONSTRAINT `fk_rankings_pool` FOREIGN KEY (`pool_id`) REFERENCES `pools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rankings_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rankings_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint unsigned DEFAULT NULL,
  `tournament_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_admin` (`admin_id`),
  KEY `idx_logs_tournament` (`tournament_id`),
  KEY `idx_logs_date` (`created_at`),
  CONSTRAINT `fk_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_logs_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `tournament_dashboard`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `tournament_dashboard` AS select `t`.`id` AS `id`,`t`.`numero` AS `numero`,`t`.`titre` AS `titre`,`t`.`date_tournoi` AS `date_tournoi`,`t`.`statut` AS `statut`,count(distinct `te`.`id`) AS `nombre_equipes`,coalesce(sum(`te`.`montant_a_payer`),0) AS `montant_total`,coalesce(sum(`te`.`montant_paye`),0) AS `montant_encaisse`,coalesce(sum((`te`.`montant_a_payer` - `te`.`montant_paye`)),0) AS `montant_restant` from (`tournaments` `t` left join `teams` `te` on((`te`.`tournament_id` = `t`.`id`))) group by `t`.`id`,`t`.`numero`,`t`.`titre`,`t`.`date_tournoi`,`t`.`statut` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

