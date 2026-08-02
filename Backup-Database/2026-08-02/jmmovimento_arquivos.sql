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
-- Table structure for table `arquivos`
--

DROP TABLE IF EXISTS `arquivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `arquivos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_original` varchar(255) COLLATE latin1_general_ci NOT NULL,
  `nome_sistema` varchar(255) COLLATE latin1_general_ci NOT NULL,
  `tamanho` bigint(20) DEFAULT NULL,
  `tipo_mime` varchar(100) COLLATE latin1_general_ci DEFAULT NULL,
  `pasta_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_upload` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `arquivos`
--

LOCK TABLES `arquivos` WRITE;
/*!40000 ALTER TABLE `arquivos` DISABLE KEYS */;
INSERT INTO `arquivos` VALUES (1,'Fluxo de Caixa - JMM.pdf','D_69aee68d0fe4f.pdf',99510,'application/pdf',1,1,'2026-03-09 15:26:05'),(2,'Cronograma JMM 14MAR26.pdf','D_69aee68d11a5a.pdf',158919,'application/pdf',1,1,'2026-03-09 15:26:05'),(3,'Cronograma JMM 14MAR26.docx','D_69aee68d1507c.docx',16943,'application/vnd.openxmlformats-officedocument.wordprocessingml.document',1,1,'2026-03-09 15:26:05'),(4,'Gol_Hulk-Galo.png','D_69aee6aabfe68.png',308210,'image/png',1,1,'2026-03-09 15:26:34'),(5,'IMG-20260301-WA0005.jpg','D_69af02584085e.jpg',152229,'image/jpeg',1,1,'2026-03-09 17:24:40'),(6,'IMG-20260228-WA0043.jpg','D_69af02798294a.jpg',220855,'image/jpeg',1,1,'2026-03-09 17:25:13'),(7,'17730771319635223488098608864388.jpg','D_69af02984f049.jpg',2006069,'image/jpeg',2,1,'2026-03-09 17:25:44'),(9,'Logs_Auditoria.pdf','69af4c50248f1_drive.pdf',54721,'application/pdf',6,5,'2026-03-09 22:40:16'),(10,'Fluxo de Caixa - JMM.pdf','69af4c50268e3_drive.pdf',99510,'application/pdf',6,5,'2026-03-09 22:40:16'),(11,'Cronograma JMM 14MAR26.pdf','69af4c5028356_drive.pdf',158919,'application/pdf',6,5,'2026-03-09 22:40:16'),(12,'Cronograma JMM 14MAR26.docx','69af4c502a2e3_drive.docx',16943,'application/vnd.openxmlformats-officedocument.wordprocessingml.document',6,5,'2026-03-09 22:40:16'),(18,'Fluxo de Caixa - JMM.pdf','69af5f012b80c_drive.pdf',99510,'application/pdf',8,1,'2026-03-10 00:00:01'),(19,'Cronograma JMM 14MAR26.pdf','69af5f012d170_drive.pdf',158919,'application/pdf',8,1,'2026-03-10 00:00:01'),(22,'Sucesso-PassouGalo.jpg','69af605699b06_drive.jpg',191561,'image/jpeg',10,1,'2026-03-10 00:05:42'),(24,'Galooooooo2.jpg','69af60569e1be_drive.jpg',155505,'image/jpeg',10,1,'2026-03-10 00:05:42'),(34,'2ª Programação JMM.pdf','69af651827281_drive.pdf',166556,'application/pdf',8,1,'2026-03-10 00:26:00'),(35,'2ª Programação JMM.docx','69af65182ae50_drive.docx',15935,'application/vnd.openxmlformats-officedocument.wordprocessingml.document',8,1,'2026-03-10 00:26:00'),(36,'Logs_Auditoria_JMM.pdf','69af65182f17d_drive.pdf',54789,'application/pdf',8,1,'2026-03-10 00:26:00'),(37,'IMG-20260301-WA0005.jpg','69af6a6e6c537_drive.jpg',152229,'image/jpeg',13,5,'2026-03-10 00:48:46'),(38,'IMG-20260228-WA0046.jpg','69af6a9a6be9c_drive.jpg',208910,'image/jpeg',13,5,'2026-03-10 00:49:30'),(39,'IMG-20260228-WA0040.jpg','69af6a9a6f6a3_drive.jpg',175793,'image/jpeg',13,5,'2026-03-10 00:49:30'),(40,'IMG-20260228-WA0042.jpg','69af6a9a71b5b_drive.jpg',184674,'image/jpeg',13,5,'2026-03-10 00:49:30'),(41,'IMG-20260228-WA0041.jpg','69af6a9a7450d_drive.jpg',198081,'image/jpeg',13,5,'2026-03-10 00:49:30'),(42,'IMG-20260228-WA0043.jpg','69af6a9a7696d_drive.jpg',220855,'image/jpeg',13,5,'2026-03-10 00:49:30'),(43,'IMG-20260228-WA0044.jpg','69af6a9a78d31_drive.jpg',222394,'image/jpeg',13,5,'2026-03-10 00:49:30'),(44,'IMG-20260228-WA0045.jpg','69af6a9a7b23b_drive.jpg',237538,'image/jpeg',13,5,'2026-03-10 00:49:30'),(45,'IMG-20260227-WA0086.jpg','69af6a9a7d27c_drive.jpg',246712,'image/jpeg',13,5,'2026-03-10 00:49:30'),(47,'IMG-20260301-WA0012.jpg','69af6a9a81208_drive.jpg',259359,'image/jpeg',13,5,'2026-03-10 00:49:30'),(52,'IMG-20260228-WA0041.jpg','69b062831ac44_drive.jpg',198081,'image/jpeg',13,5,'2026-03-10 18:27:15'),(53,'IMG-20260227-WA0085.jpg','69b062831d24d_drive.jpg',241623,'image/jpeg',13,5,'2026-03-10 18:27:15'),(54,'IMG-20260227-WA0068.jpg','69b062831edde_drive.jpg',52698,'image/jpeg',13,5,'2026-03-10 18:27:15'),(55,'IMG-20260227-WA0066.jpg','69b062832042d_drive.jpg',102227,'image/jpeg',13,5,'2026-03-10 18:27:15'),(56,'IMG-20260227-WA0067.jpg','69b06283218dc_drive.jpg',177648,'image/jpeg',13,5,'2026-03-10 18:27:15'),(57,'IMG-20260226-WA0032.jpg','69b062832318d_drive.jpg',201335,'image/jpeg',13,5,'2026-03-10 18:27:15'),(58,'20260224_102116.jpg','69b0628324a9a_drive.jpg',5616891,'image/jpeg',13,5,'2026-03-10 18:27:15'),(59,'Monza_Motor_Ok.jpg','69b45d913a64b_drive.jpg',165588,'image/jpeg',13,5,'2026-03-13 18:55:13'),(60,'Grupo-Gincana.docx','69b55ecd571d8_drive.docx',14877,'application/vnd.openxmlformats-officedocument.wordprocessingml.document',8,5,'2026-03-14 13:12:45'),(61,'Relatorio_ATA_Encontro_JMM_14-03-2026.pdf','69be95cf87877_drive.pdf',57258,'application/pdf',14,5,'2026-03-21 12:57:51'),(62,'Cronograma JMM 11ABR26.pdf','69daf92c95e5a_drive.pdf',154108,'application/pdf',15,5,'2026-04-12 01:45:16'),(63,'Ata-Reuniao_Encontro_11-04-2026.pdf','69daf955157df_drive.pdf',477633,'application/pdf',15,5,'2026-04-12 01:45:57'),(64,'Impressão de Ata - JMM-25-04-2026.pdf','69ed55523fab1_drive.pdf',130476,'application/pdf',15,5,'2026-04-25 23:59:14'),(65,'JMM 25ABR26.docx','69ed557f0ec1b_drive.docx',199911,'application/vnd.openxmlformats-officedocument.wordprocessingml.document',15,5,'2026-04-25 23:59:59'),(66,'JMM 25ABR26.pdf','69ed559e19ec0_drive.pdf',153050,'application/pdf',15,5,'2026-04-26 00:00:30'),(67,'WhatsApp Image 2026-05-07 at 15.47.01.jpeg','6a047d903499a_drive.jpeg',45921,'image/jpeg',17,5,'2026-05-13 13:33:04'),(68,'WhatsApp Image 2026-05-07 at 11.57.50.jpeg','6a047da9e248e_drive.jpeg',156672,'image/jpeg',17,5,'2026-05-13 13:33:29'),(69,'Kit 100 Vela Rechaud Decoração Casamento Festa Flutuante Cor Branco Marca Conceito Vintage Liso Inodora.pdf','6a047edc94ae2_drive.pdf',22940,'application/pdf',17,5,'2026-05-13 13:38:36'),(70,'WhatsApp Image 2026-04-25 at 18.46.36.jpeg','6a048035339d7_drive.jpeg',161448,'image/jpeg',17,5,'2026-05-13 13:44:21'),(71,'Ata - JMM-09-05-2026.pdf','6a0e3b9adde41_drive.pdf',127205,'application/pdf',18,5,'2026-05-20 22:54:18'),(72,'Ata - JMM-09-05-2026.pdf','6a0e3bb8eeebf_drive.pdf',127205,'application/pdf',19,5,'2026-05-20 22:54:48');
/*!40000 ALTER TABLE `arquivos` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-02 18:39:49
