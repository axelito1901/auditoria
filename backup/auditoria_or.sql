-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 22-08-2025 a las 22:47:09
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `auditoria_or`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audits`
--

CREATE TABLE `audits` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `audited_at` datetime NOT NULL DEFAULT current_timestamp(),
  `total_items` int(11) NOT NULL DEFAULT 0,
  `errors_count` int(11) NOT NULL DEFAULT 0,
  `not_applicable_count` int(11) NOT NULL DEFAULT 0,
  `error_percentage` decimal(5,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_answers`
--

CREATE TABLE `audit_answers` (
  `id` int(11) NOT NULL,
  `audit_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `value` enum('OK','1','N') NOT NULL,
  `comment` text DEFAULT NULL,
  `question_text` text DEFAULT NULL,
  `responsable_text` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist_items`
--

CREATE TABLE `checklist_items` (
  `id` int(11) NOT NULL,
  `item_order` int(11) NOT NULL,
  `question` text NOT NULL,
  `responsable_default` varchar(100) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `checklist_items`
--

INSERT INTO `checklist_items` (`id`, `item_order`, `question`, `responsable_default`, `active`) VALUES
(1, 1, 'Datos de cliente llenados en forma completa (email, telefonos, dirección, nombre, DNI).', 'As. Citas', 1),
(2, 2, 'Datos de la unidad llenados en forma completa (VIN, ID, chasis, nro motor, modelo, dominio, color).', 'As. Citas', 1),
(3, 3, 'Coincide la fecha de entrega de la OR con la informada en sistema ELSA?.', 'As. Citas', 1),
(4, 4, 'Trabajos solicitados por el cliente asentados en OR (división por items / claro sin interpretaciones ni diagnósticos).', 'As. Servicio', 1),
(5, 5, 'Presupuestos e importes de trabajos / servicios solicitados por el cliente  asentados en el frente de la OR.', 'As. Servicio', 1),
(6, 6, 'Fecha y hora de entrega pactada con el cliente asentada en OR.', 'As. Servicio', 1),
(7, 7, 'Kilometraje de la unidad asentada en OR.', 'As. Servicio', 1),
(8, 8, 'Recepción de unidad con tablet o planilla de llenado manual alternativa (adjunta a la OR).', 'As. Servicio', 1),
(9, 9, 'Registro de daños,  faltantes y firma del cliente mediante tablet o planilla de llenado manual alternativa.', 'As. Servicio', 1),
(10, 10, 'Ampliaciones de trabajos asentadas en OR.', 'As. Servicio', 1),
(11, 11, 'Firma del Asesor de Servicio al frente de la OR en el campo correspondiente.', 'As. Servicio', 1),
(12, 12, 'Firma y Aclaración del cliente al frente de la OR autorizando los trabajos (fecha, DNI, parentesco). Al ingreso de la unidad.', 'As. Servicio', 1),
(13, 13, 'Firma del cliente por aceptación o rechazo de Ampliaciones de Trabajos.', 'As. Servicio', 1),
(14, 14, 'Firma y aclaración del cliente al dorso de la OR dando conformidad (fecha, DNI, parentesco). Al retiro de la unidad.', 'As. Servicio', 1),
(15, 15, 'Forma de pago y tipo de factura solicitados por el cliente aclarados en el frente de la OR.', 'As. Servicio', 1),
(16, 16, 'Afectaciones de la unidad a campañas asentadas al frente de la OR (SI / NO).', 'As. Servicio', 1),
(17, 17, 'Factura adjunta a la orden de reparación con detalle de mano de obra, repuestos y  trabajos no aceptados por el cliente.', 'Cajero', 1),
(18, 18, 'Fichada del Técnico legible, completa y encuadrada en el campo correspondiente, al dorso de la OR.', 'Jefe de Taller', 1),
(19, 19, 'Identificación del Técnico (legajo, nombre o apellido) , item y asentamiento de horas aplicadas al costado de la fichada.', 'Jefe de Taller', 1),
(20, 20, 'Descripción completa del diagnóstico realizado por el Técnico en el cuadro superior del dorso de la OR (división por items).', 'Jefe de Taller', 1),
(21, 21, 'Descripción completa de los trabajos realizados por el Técnico en el cuadro inferior del dorso de la OR (división por items).', 'Jefe de Taller', 1),
(22, 22, 'Tabla de mantenimiento (extraída del ELSA) llenada en forma completa y correcta por el Técnico, con numero de OR y dominio asentado en forma manual adjunta a OR.', 'Jefe de Taller', 1),
(23, 23, 'Asentamiento manual de fecha de cierre en OR.', 'Gerente PostVenta', 1),
(24, 24, 'Firmas y Fechas del Jefe de Taller y el Técnico en Tabla de mantenimiento.', 'Jefe de Taller', 1),
(25, 25, 'Vale/s de repuestos solicitados en ventanilla adjuntos a la OR.', 'Jefe de Taller', 1),
(26, 26, 'Firmas en vale/s de repuestos de: Jefe de Taller, Técnico y Ventanillero.', 'Jefe de Taller', 1),
(27, 27, 'Recorrido de prueba firmado al dorso de la OR por el Jefe de Taller con el kilometraje previo a la entrega especificado.', 'Jefe de Taller', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `order_type` enum('cliente','garantia','interna') NOT NULL,
  `auditor` varchar(100) DEFAULT NULL,
  `week_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `audits`
--
ALTER TABLE `audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indices de la tabla `audit_answers`
--
ALTER TABLE `audit_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_audit_item` (`audit_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indices de la tabla `checklist_items`
--
ALTER TABLE `checklist_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_item_order` (`item_order`);

--
-- Indices de la tabla `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `audits`
--
ALTER TABLE `audits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `audit_answers`
--
ALTER TABLE `audit_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT de la tabla `checklist_items`
--
ALTER TABLE `checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `audits`
--
ALTER TABLE `audits`
  ADD CONSTRAINT `audits_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `audit_answers`
--
ALTER TABLE `audit_answers`
  ADD CONSTRAINT `audit_answers_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `audits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `audit_answers_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `checklist_items` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
