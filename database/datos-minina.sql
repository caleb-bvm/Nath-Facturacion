
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


INSERT INTO `clientes` (`id`, `nombre`, `telefono`, `activo`, `created_at`, `correo`, `nit`, `nrc`) VALUES (7,'Caleb Ortiz','0000-0001',1,'2026-10-12 00:31:25','calebortiz288@gmail.com','0000-000000-001-0','000001-0'),(8,'Nathaly Martinez','0000-0002',1,'2026-10-12 00:31:25','nathalymartinez696@gmail.com','0000-000000-002-0','000002-0');





INSERT INTO `productos` (`id`, `codigo`, `descripcion`, `precio`, `activo`, `created_at`) VALUES (20,'MIN-0001','Cuaderno universitario rayado, 100 hojas',2.50,1,'2026-10-12 00:28:03'),(21,'MIN-0002','Cuaderno cuadriculado, 100 hojas',2.50,1,'2026-10-12 00:28:03'),(22,'MIN-0003','Libreta de dibujo, 40 hojas',3.25,1,'2026-10-12 00:28:03'),(23,'MIN-0004','Resma de papel bond carta, 500 hojas',5.75,1,'2026-10-12 00:28:03'),(24,'MIN-0005','Cartulina blanca, pliego',0.40,1,'2026-10-12 00:28:03'),(25,'MIN-0006','Cartulina de color, pliego',0.50,1,'2026-10-12 00:28:03'),(26,'MIN-0007','Papel kraft, pliego',0.65,1,'2026-10-12 00:28:03'),(27,'MIN-0008','Papel de regalo, pliego',0.75,1,'2026-10-12 00:28:03'),(28,'MIN-0009','Lápiz de grafito HB',0.30,1,'2026-10-12 00:28:03'),(29,'MIN-0010','Bolígrafo azul',0.40,1,'2026-10-12 00:28:03'),(30,'MIN-0011','Bolígrafo negro',0.40,1,'2026-10-12 00:28:03'),(31,'MIN-0012','Borrador blanco',0.25,1,'2026-10-12 00:28:03'),(32,'MIN-0013','Sacapuntas metálico',0.50,1,'2026-10-12 00:28:03'),(33,'MIN-0014','Regla transparente de 30 cm',0.75,1,'2026-10-12 00:28:03'),(34,'MIN-0015','Colores de madera, caja de 12',2.75,1,'2026-10-12 00:28:03'),(35,'MIN-0016','Marcadores, caja de 12',3.50,1,'2026-10-12 00:28:03'),(36,'MIN-0017','Resaltador amarillo',0.85,1,'2026-10-12 00:28:03'),(37,'MIN-0018','Pegamento en barra de 21 g',1.20,1,'2026-10-12 00:28:03'),(38,'MIN-0019','Tijera escolar de punta redonda',1.50,1,'2026-10-12 00:28:03'),(39,'MIN-0020','Cinta adhesiva transparente',0.80,1,'2026-10-12 00:28:03'),(40,'MIN-0021','Notas adhesivas, bloque de 100',1.25,1,'2026-10-12 00:28:03'),(41,'MIN-0022','Folder manila tamaño carta',0.25,1,'2026-10-12 00:28:03'),(42,'MIN-0023','Sobre manila tamaño carta',0.20,1,'2026-10-12 00:28:03'),(43,'MIN-0024','Grapadora de escritorio',4.50,1,'2026-10-12 00:28:03');

INSERT INTO `sucursales` (`id`, `nombre`, `direccion`, `activo`, `created_at`) VALUES (3,'Minina · Casa Matriz','Centro Histórico, Avenida España.',1,'2026-10-12 00:28:03');

INSERT INTO `vendedores` (`id`, `nombre`, `usuario`, `clave`, `fecha_nacimiento`, `activo`, `created_at`, `rol`, `carnet`, `dias`, `hora_inicio`, `hora_fin`, `fecha_desde`, `fecha_hasta`) VALUES (13,'Administración Minina','admin','admin123','1990-01-01',1,'2026-10-12 00:19:09','admin',NULL,'1,2,3,4,5,6','08:00:00','18:00:00',NULL,NULL);
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

