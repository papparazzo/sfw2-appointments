
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE `sfw2_recurring_appointments` (
  `Id` int(10) UNSIGNED NOT NULL,
  `PathId` int(11) UNSIGNED NOT NULL,
  `Description` text COLLATE utf8_unicode_ci NOT NULL,
  `Day` tinyint(4) DEFAULT NULL,
  `StartTime` time DEFAULT NULL,
  `EndTime` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;



ALTER TABLE `sfw2_recurring_appointments`
  ADD PRIMARY KEY (`Id`);


ALTER TABLE `sfw2_recurring_appointments`
  MODIFY `Id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;