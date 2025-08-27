-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 28-08-2025 a las 00:41:49
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

--
-- Volcado de datos para la tabla `audits`
--

INSERT INTO `audits` (`id`, `order_id`, `audited_at`, `total_items`, `errors_count`, `not_applicable_count`, `error_percentage`) VALUES
(3, 3, '2025-08-26 21:15:29', 27, 5, 3, 20.83),
(4, 4, '2025-08-26 21:25:58', 27, 3, 3, 12.50);

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
(1, 1, 'Datos de cliente llenados en forma completa (email, teléfonos, dirección, nombre, DNI).', 'As. Citas', 1),
(2, 2, 'Datos de la unidad llenados en forma completa (VIN, ID, chasis, N° de motor, modelo, dominio, color).', 'As. Citas', 1),
(3, 3, 'Kilometraje de la unidad asentado en la OR.', 'As. Servicio', 1),
(4, 4, 'Fecha y hora de entrega pactada con el cliente asentada en la OR.', 'As. Servicio', 1),
(5, 5, '¿Coincide la fecha de entrega de la OR con la informada en el sistema ELSA?', 'As. Citas', 1),
(6, 6, 'Asentamiento manual de la fecha de cierre en la OR.', 'Gerente PostVenta', 1),
(7, 7, 'Firma del Asesor de Servicio al frente de la OR en el campo correspondiente.', 'As. Servicio', 1),
(8, 8, 'Afectaciones de la unidad a campañas asentadas al frente de la OR (SI / NO).', 'As. Servicio', 1),
(9, 9, 'Forma de pago y tipo de factura solicitados por el cliente aclarados en el frente de la OR.', 'As. Servicio', 1),
(10, 10, 'Trabajos solicitados por el cliente asentados en la OR (división por ítems; claro, sin interpretaciones ni diagnósticos).', 'As. Servicio', 1),
(11, 11, 'Presupuestos e importes de trabajos/servicios solicitados por el cliente asentados en el frente de la OR.', 'As. Servicio', 1),
(12, 12, 'Recepción de unidad con tablet o planilla de llenado manual alternativa (adjunta a la OR).', 'As. Servicio', 1),
(13, 13, 'Registro de daños, faltantes y firma del cliente mediante tablet o planilla de llenado manual alternativa.', 'As. Servicio', 1),
(14, 14, 'Firma y aclaración del cliente al frente de la OR autorizando los trabajos (fecha, DNI, parentesco). Al ingreso de la unidad.', 'As. Servicio', 1),
(15, 15, 'Fichada del Técnico legible, completa y encuadrada en el campo correspondiente, al dorso de la OR.', 'Jefe de Taller', 1),
(16, 16, 'Identificación del Técnico (legajo, nombre o apellido), ítem y asentamiento de horas aplicadas al costado de la fichada.', 'Jefe de Taller', 1),
(17, 17, 'Descripción completa del diagnóstico realizado por el Técnico en el cuadro superior del dorso de la OR (división por ítems).', 'Jefe de Taller', 1),
(18, 18, 'Descripción completa de los trabajos realizados por el Técnico en el cuadro inferior del dorso de la OR (división por ítems).', 'Jefe de Taller', 1),
(19, 19, 'Ampliaciones de trabajos asentadas en la OR.', 'As. Servicio', 1),
(20, 20, 'Ampliaciones correctamente documentadas y firmadas.', 'As. Servicio', 1),
(21, 21, 'Recorrido de prueba firmado al dorso de la OR por el Jefe de Taller, con el kilometraje previo a la entrega especificado.', 'Jefe de Taller', 1),
(22, 22, 'Firma y aclaración del cliente al dorso de la OR dando conformidad (fecha, DNI, parentesco). Al retiro de la unidad.', 'As. Servicio', 1),
(23, 23, 'Factura adjunta a la OR.', 'Cajero', 1),
(24, 24, 'Detalle de mano de obra, repuestos y trabajos no aceptados por el cliente documentados en la factura adjunta.', 'Cajero', 1),
(25, 25, 'Tabla de mantenimiento (extraída de ELSA) completada en forma correcta por el Técnico, con número de OR y dominio asentados manualmente; adjunta a la OR.', 'Jefe de Taller', 1),
(26, 26, 'Tabla de mantenimiento completa y correctamente confeccionada.', 'Jefe de Taller', 1),
(27, 27, 'Firmas y fechas en la tabla de mantenimiento (Jefe de Taller y Técnico).', 'Jefe de Taller', 1),
(28, 28, 'Vale/s de repuestos solicitados en ventanilla adjuntos a la OR.', 'Jefe de Taller', 1),
(29, 29, 'Firmas en vale/s de repuestos: Jefe de Taller, Técnico y Ventanillero.', 'Jefe de Taller', 1);

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
-- Volcado de datos para la tabla `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `order_type`, `auditor`, `week_date`, `notes`, `created_at`) VALUES
(3, '342343', 'cliente', NULL, '2025-08-25', NULL, '2025-08-27 00:15:29'),
(4, '62747', 'cliente', NULL, '2025-08-18', NULL, '2025-08-27 00:25:58');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `audit_answers`
--
ALTER TABLE `audit_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT de la tabla `checklist_items`
--
ALTER TABLE `checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de la tabla `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
