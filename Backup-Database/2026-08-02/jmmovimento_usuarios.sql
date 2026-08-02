-- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
--
-- Host: jmmovimento.mysql.dbaas.com.br    Database: jmmovimento
-- ------------------------------------------------------
-- Server version	5.7.32-35-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE latin1_general_ci NOT NULL,
  `email` varchar(100) COLLATE latin1_general_ci NOT NULL,
  `celular` varchar(20) COLLATE latin1_general_ci DEFAULT NULL,
  `senha` varchar(255) COLLATE latin1_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `nivel` enum('admin','membro') COLLATE latin1_general_ci DEFAULT 'membro',
  `forcar_reset` tinyint(1) DEFAULT '1',
  `quota_limite` bigint(20) DEFAULT '1073741824',
  `ultimo_acesso` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Administrator','souzafelipe@bdsoft.com.br',NULL,'$2y$10$VsR6ZH3nLOIeh6sAjxL1nOdKk2CSQa.30yxOPCFNpjuGPSx5MuITS','2026-03-05 20:08:57','admin',0,10737418240,'2026-06-20 09:05:26'),(5,'Souza Felipe','souza.ffr@gmail.com','(31)97195-7751','$2y$10$p6P3I/J.j1ZeBpJBqbBK9.BFgVtUtYlJuRGUWvGV8jpkh/ApaEieS','2026-03-09 13:57:48','membro',0,10737418240,'2026-08-02 18:32:00'),(6,'Fernanda Duarte','dfnoleto@gmail.com','1298189-0275','$2y$10$HejLN7xUTWhszlDH03Wm0eUUkwCh3Ag7Xx0HGSb8g1nNwPn7fK26G','2026-03-09 18:21:36','membro',0,1073741824,'2026-08-01 14:09:13'),(7,'Senhor Adriano Xavier','adrianoxxavier@gmail.com','(12) 98805-5751','$2y$10$WGx24H80T.iAe2wR/d91.uycWPh8O6bGe85l3oAjXCAIwXEt6iyKu','2026-03-09 18:25:12','membro',0,1073741824,'2026-08-02 15:52:32'),(8,'Dona Vilma Xavier','vilmaleexavier@hotmail.com','(12) 98805-5750','$2y$10$aL2cug8PA5ROjm/o9O6KpuW1jKRnWd88qzcltxPr.1NiatPn.aHcW','2026-03-09 18:29:04','membro',1,1073741824,NULL),(9,'Senhor Gabriel Silva','gabrielcrz10@hotmail.com','(12) 98143-5564','$2y$10$CTzpieOVQU3P38F/A0hZVusqx2IfMlYVla0OhnQ98KGCSJCXYcJa.','2026-03-09 18:30:53','membro',1,1073741824,NULL),(12,'Lucila Igreja','lucilajoel@yahoo.com.br','12 98118-5180','$2y$10$se2wwMHMwebkP2.odekKDuQo0LPfcQgNE2lnkkNizRqSTi3xbknli','2026-03-15 12:16:59','membro',0,1073741824,'2026-04-23 10:02:25'),(13,'Kayque Lima Secretaria','kayquelima567@gmail.com','12 99198-4580','$2y$10$b46S06jl1SuUsRrVuKORpOgL7iX4/b/VDSeRDY0pV2VkwP42.jKfi','2026-03-26 12:12:22','membro',0,1073741824,'2026-03-26 11:36:05');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-02 18:39:46
