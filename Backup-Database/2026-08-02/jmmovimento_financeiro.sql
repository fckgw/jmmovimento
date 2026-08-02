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
-- Table structure for table `financeiro`
--

DROP TABLE IF EXISTS `financeiro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financeiro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('pagar','receber') COLLATE latin1_general_ci NOT NULL,
  `conta_id` int(11) DEFAULT NULL,
  `estabelecimento` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  `descricao` text COLLATE latin1_general_ci,
  `valor` decimal(10,2) NOT NULL,
  `desconto` decimal(10,2) DEFAULT '0.00',
  `forma_pagamento` varchar(100) COLLATE latin1_general_ci DEFAULT NULL,
  `vencimento` datetime DEFAULT NULL,
  `status` varchar(50) COLLATE latin1_general_ci DEFAULT 'pendente',
  `comprovante_url` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  `itens_json` longtext COLLATE latin1_general_ci,
  `data_cadastro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financeiro`
--

LOCK TABLES `financeiro` WRITE;
/*!40000 ALTER TABLE `financeiro` DISABLE KEYS */;
INSERT INTO `financeiro` VALUES (3,'pagar',1,'SUPERMERCADO VILLA SIMPATIA','Encontro Sabado',103.99,0.00,NULL,'2026-03-09 18:00:00','pago','','[{\"nome\":\"MOLHO TOMATE\",\"qtd\":\"3\",\"unit\":\"13,49\",\"total\":\"40,47\"},{\"nome\":\"BATATA PALHA\",\"qtd\":\"2\",\"unit\":\"14,90\",\"total\":\"29,80\"},{\"nome\":\"SALSICHA\",\"qtd\":\"2.5\",\"unit\":\"13,49\",\"total\":\"33,72\"}]','2026-03-09 12:45:18'),(4,'receber',1,'Doação','Doação para Inicial',5000.00,0.00,NULL,'2026-03-01 09:45:00','pago','','[{\"nome\":\"Doação Fiel\",\"qtd\":\"1\",\"unit\":\"5.000,00\",\"total\":\"5.000,00\"}]','2026-03-09 12:46:10'),(5,'pagar',1,'Internet','',57.00,0.00,NULL,'2026-05-13 09:37:00','pago','','[{\"nome\":\"Terço - Lembrança Aniversario\",\"qtd\":\"1\",\"unit\":\"57,00\",\"total\":\"57,00\"}]','2026-05-13 12:38:33'),(6,'pagar',1,'Grafica','',27.50,0.00,NULL,'2026-05-07 15:45:00','pago','','[{\"nome\":\"Grafica DuVale\",\"qtd\":\"1\",\"unit\":\"27,50\",\"total\":\"27,50\"}]','2026-05-13 13:31:29'),(7,'pagar',1,'algodão Doce','',71.85,0.00,NULL,'2026-05-06 14:24:00','pago','FIN_6a047e3d21ac5.jpeg','[{\"nome\":\"bombom Sonho de Valsa\",\"qtd\":\"1\",\"unit\":\"69,90\",\"total\":\"69,90\"},{\"nome\":\"TWIX\",\"qtd\":\"1\",\"unit\":\"1,95\",\"total\":\"1,95\"}]','2026-05-13 13:35:57'),(8,'pagar',1,'Mercado Livre','',44.65,0.00,NULL,'2026-05-06 14:40:00','pago','','[{\"nome\":\"KIT 100 Velas Rechaud\",\"qtd\":\"1\",\"unit\":\"44,65\",\"total\":\"44,65\"}]','2026-05-13 13:38:07'),(9,'pagar',1,'Gelo Posto Cerejeira','',12.00,0.00,NULL,'2026-04-25 15:45:00','pago','','[{\"nome\":\"Gelo em Cubos\",\"qtd\":\"1\",\"unit\":\"12,00\",\"total\":\"12,00\"}]','2026-05-13 13:46:01'),(10,'pagar',1,'T A Machado Sorvertes','',49.50,0.00,NULL,'2026-04-25 15:12:00','pago','','[{\"nome\":\"Sorvete Picole Diversos\",\"qtd\":\"11\",\"unit\":\"1,00\",\"total\":\"11,00\"},{\"nome\":\"Sorvete Picole Diversos\",\"qtd\":\"33\",\"unit\":\"1,00\",\"total\":\"33,00\"},{\"nome\":\"Sorvete Picole Diversos\",\"qtd\":\"1\",\"unit\":\"5,50\",\"total\":\"5,50\"}]','2026-05-13 13:50:19');
/*!40000 ALTER TABLE `financeiro` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-02 18:39:50
