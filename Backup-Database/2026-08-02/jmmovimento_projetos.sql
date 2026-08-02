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
-- Table structure for table `projetos`
--

DROP TABLE IF EXISTS `projetos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projetos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_projeto` varchar(255) COLLATE latin1_general_ci NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `mensagem` text COLLATE latin1_general_ci,
  `tem_anexo` tinyint(1) DEFAULT '0',
  `anexo_path` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projetos`
--

LOCK TABLES `projetos` WRITE;
/*!40000 ALTER TABLE `projetos` DISABLE KEYS */;
INSERT INTO `projetos` VALUES (3,'Rifa dos Amigos da Matriz 2026','2026-03-15','2026-05-08','2026-03-15 01:34:27','Olá!\r\nComo vai?\r\nEspero que esteja bem!\r\n\r\n*PEÇO QUE LEIA ATENTAMENTE TODAS AS ORIENTAÇÕES ABAIXO, NÃO É SPAM E NEM TENTATIVA DE GOLPE!*\r\n\r\nSomos agentes de pastoral da Paróquia Nossa Senhora d\'Ajuda e estaremos utilizando esse meio de comunicação para realizar as vendas de nossa Ação Entre Amigos 2026. Por esse contato, as pessoas que estarão responsáveis por realizar as vendas serão: Kayque, Lucila e Luiza Helena.\r\nA nossa Ação Entre Amigos deste ano traz algumas novidades nos prêmios: \r\n\r\n1º Prêmio: Moto Honda CGStart160 0km \r\n2º Prêmio: R$3000,00 em dinheiro\r\n3º Prêmio: R$2000,00 em dinheiro\r\n4º Prêmio: TV 43 polegadas\r\n5º Prêmio: Notebook\r\n\r\nO valor de cada bilhete individual é de apenas R$20,00. \r\nMas se desejar aumentar as suas chances de ganhar, você também pode estar comprando um carnê inteiro, que contém 20 bilhetes, e custa R$400,00. \r\nO sorteio será no dia 09 de maio de 2026, sábado, véspera do dia das mães pela Loteria Federal. \r\nJá pensou presentear a mãe com um desses belíssimos prêmios? \r\n\r\nO pagamento deve ser feito através do Pix, a chave Pix é: \r\npnsd.financeiro@gmail.com\r\nParoquia Nossa Senhora da Juda\r\n\r\nApós realizar o pagamento, o comprovante deve ser enviado neste contato, juntamente com seu nome completo, telefone/celular para contato e Endereço, para podermos fazer o registro de seu bilhete.\r\n\r\n* Aguarde a nossa conferência e o preenchimento de seu bilhete. *\r\n____________________________________________________\r\n\r\nComprando essa ação entre amigos, além de você concorrer a prêmios maravilhosos, você colabora diretamente com o restauro da Igreja Matriz, entrando para a história desta Igreja. \r\n\r\nQue Deus lhe abençoe, e que São João Batista, Padroeiro de Caçapava interceda sempre por você!',1,'uploads/marketing/1774869236_Premiacao.jpeg'),(4,'Projeto Teste Sistema','2026-03-18','2026-05-08','2026-03-15 12:24:15','Olá!\r\nComo vai?\r\nEspero que esteja bem!\r\n\r\n*PEÇO QUE LEIA ATENTAMENTE TODAS AS ORIENTAÇÕES ABAIXO, NÃO É SPAM E NEM TENTATIVA DE GOLPE!*\r\n\r\nSomos agentes de pastoral da Paróquia Nossa Senhora d\'Ajuda e estaremos utilizando esse meio de comunicação para realizar as vendas de nossa Ação Entre Amigos 2026. Por esse contato, as pessoas que estarão responsáveis por realizar as vendas serão: Kayque, Lucila e Luiza Helena.\r\nA nossa Ação Entre Amigos deste ano traz algumas novidades nos prêmios: \r\n\r\n1º Prêmio: Moto Honda CGStart160 0km \r\n2º Prêmio: R$3000,00 em dinheiro\r\n3º Prêmio: R$2000,00 em dinheiro\r\n4º Prêmio: TV 43 polegadas\r\n5º Prêmio: Notebook\r\n\r\nO valor de cada bilhete individual é de apenas R$20,00. \r\nMas se desejar aumentar as suas chances de ganhar, você também pode estar comprando um carnê inteiro, que contém 20 bilhetes, e custa R$400,00. \r\nO sorteio será no dia 09 de maio de 2026, sábado, véspera do dia das mães pela Loteria Federal. \r\nJá pensou presentear a mãe com um desses belíssimos prêmios? \r\n\r\nO pagamento deve ser feito através do Pix, a chave Pix é: \r\npnsd.financeiro@gmail.com\r\nParoquia Nossa Senhora da Juda\r\n\r\nApós realizar o pagamento, o comprovante deve ser enviado neste contato, juntamente com seu nome completo, telefone/celular para contato e Endereço, para podermos fazer o registro de seu bilhete.\r\n\r\n* Aguarde a nossa conferência e o preenchimento de seu bilhete. *\r\n____________________________________________________\r\n\r\nComprando essa ação entre amigos, além de você concorrer a prêmios maravilhosos, você colabora diretamente com o restauro da Igreja Matriz, entrando para a história desta Igreja. \r\n\r\nQue Deus lhe abençoe, e que São João Batista, Padroeiro de Caçapava interceda sempre por você!',1,'uploads/marketing/1774614121_Premiacao.jpeg'),(5,'Terço de São José em prol da nossa Paróquia','2026-04-23','2026-04-24','2026-04-23 00:29:53','Terço de São José em prol da nossa Paróquia!\r\n\r\nNós cremos no poder da oração e sabemos que, pela força dela, muitas coisas podem ser transformadas.\r\n\r\nNeste momento em que a nossa Paróquia vive um tempo de desafios e grandes responsabilidades, especialmente com as obras que acontecem em nossas comunidades e o grande restauro da nossa Igreja Matriz, queremos nos unir ainda mais em oração.\r\n\r\nSabemos que muitos paroquianos e fiéis estão lutando e colaborando com muito empenho, seja ajudando materialmente, vendendo e trabalhando.\r\n\r\nMas, além da ajuda concreta, queremos também fortalecer nossa fé por meio da oração.\r\n\r\nPor isso, convidamos você para este momento especial, pedindo a poderosa intercessão de São José, para que ele nos ajude, proteja e conduza nossa Paróquia neste tempo tão importante.\r\n\r\nVenha rezar conosco e unir sua fé à nossa!\r\n\r\nPróxima sexta-feira às 15:30 horas na Igreja Matriz São João Batista!\r\n\r\nJuntos, na oração, somos mais fortes.',1,'uploads/marketing/1776904193_Terco_SaoJose_ParoquiaSaoJoaoBatista.jpeg');
/*!40000 ALTER TABLE `projetos` ENABLE KEYS */;
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
