-- -----------------------------------------------------------------------------------------------
-- game encounters
-- -----------------------------------------------------------------------------------------------
CREATE TABLE `{TABLE_PREFIX}_game_encounters` (
    `Id` int(10) UNSIGNED NOT NULL,
    `PathId` int(11) UNSIGNED NOT NULL,
    `Home` text COLLATE utf8_unicode_ci NOT NULL,
    `Guest` text COLLATE utf8_unicode_ci NOT NULL,
    `Changeable` enum('0','1') COLLATE utf8_unicode_ci NOT NULL,
    `StartDate` date DEFAULT NULL,
    `StartTime` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `{TABLE_PREFIX}_game_encounters`
    ADD PRIMARY KEY (`Id`);

ALTER TABLE `{TABLE_PREFIX}_game_encounters`
    MODIFY `Id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- -----------------------------------------------------------------------------------------------
-- one time appontments
-- -----------------------------------------------------------------------------------------------
CREATE TABLE `{TABLE_PREFIX}_one_time_appointments` (
    `Id` int(10) UNSIGNED NOT NULL,
    `PathId` int(11) UNSIGNED NOT NULL,
    `Description` text COLLATE utf8_unicode_ci NOT NULL,
    `Location` text COLLATE utf8_unicode_ci NOT NULL,
    `Changeable` enum('0','1') COLLATE utf8_unicode_ci NOT NULL,
    `StartDate` date DEFAULT NULL,
    `StartTime` time DEFAULT NULL,
    `EndDate` date DEFAULT NULL,
    `EndTime` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `{TABLE_PREFIX}_one_time_appointments`
    ADD PRIMARY KEY (`Id`);

ALTER TABLE `{TABLE_PREFIX}_one_time_appointments`
    MODIFY `Id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- -----------------------------------------------------------------------------------------------
-- recurring appointments
-- -----------------------------------------------------------------------------------------------
CREATE TABLE `{TABLE_PREFIX}_recurring_appointments` (
    `Id` int(10) UNSIGNED NOT NULL,
    `PathId` int(11) UNSIGNED NOT NULL,
    `Description` text COLLATE utf8_unicode_ci NOT NULL,
    `Day` tinyint(4) DEFAULT NULL,
    `StartTime` time DEFAULT NULL,
`EndTime` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `{TABLE_PREFIX}_recurring_appointments`
    ADD PRIMARY KEY (`Id`);

ALTER TABLE `{TABLE_PREFIX}_recurring_appointments`
    MODIFY `Id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;