
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE `sfw2_game_encounters` (
  `Id` int(10) UNSIGNED NOT NULL,
  `PathId` int(11) UNSIGNED NOT NULL,
  `Home` text COLLATE utf8_unicode_ci NOT NULL,
  `Guest` text COLLATE utf8_unicode_ci NOT NULL,
  `Changeable` enum('0','1') COLLATE utf8_unicode_ci NOT NULL,
  `StartDate` date DEFAULT NULL,
  `StartTime` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `sfw2_game_encounters`
  ADD PRIMARY KEY (`Id`);

ALTER TABLE `sfw2_game_encounters`
  MODIFY `Id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
