<?php

if (!function_exists('safe_query')) {
    die('Access denied');
}

global $plugin;

PluginInstallerHelper::install([

    'modulname'  => 'eventcalendar',
    'name'       => 'Eventkalender',
    'version'    => (string)($plugin['version'] ?? '1.0.0'),
    'author'     => 'T-Seven',
    'website'    => 'https://www.nexpell.de',
    'path'       => 'includes/plugins/eventcalendar/',

    'admin_file' => 'admin_eventcalendar',
    'index_link' => 'eventcalendar',
    'sidebar'    => 'deactivated',

    'languages' => [
        'plugin_info_eventcalendar' => [
            'de' => 'Kalender für Veranstaltungen, Turniere, Clanwars und Businesstermine mit Spielplan-Widget.',
            'en' => 'Calendar for events, tournaments, clan wars and business appointments with schedule widget.',
            'it' => 'Calendario per eventi, tornei, clan war e appuntamenti business con widget programma.'
        ]
    ],

    'permissions' => [
        'eventcalendar'
    ],

    'widgets' => [
        [
            'widget_key'    => 'widget_eventcalendar_content',
            'title'         => 'Eventkalender Spielplan Widget',
            'description'   => 'Großes Spielplan-Widget für Veranstaltungen, Turniere, Clanwars und Termine.',
            'allowed_zones' => 'maintop,mainbottom'
        ]
    ],

    'admin_navigation' => [
        [
            'url'   => 'admincenter.php?site=admin_eventcalendar',
            'catID' => 8,
            'sort'  => 1,
            'labels' => [
                'de' => 'Eventkalender',
                'en' => 'Event calendar',
                'it' => 'Calendario eventi'
            ]
        ]
    ],

    'website_navigation' => [
        [
            'url'        => 'index.php?site=eventcalendar',
            'mnavID'     => 1,
            'sort'       => 1,
            'indropdown' => 1,
            'labels' => [
                'de' => 'Termine',
                'en' => 'Events',
                'it' => 'Eventi'
            ]
        ]
    ]

]);

safe_query("CREATE TABLE IF NOT EXISTS plugins_eventcalendar_events (
  id INT(11) NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  event_type ENUM('event','tournament','clanwar','business','training','match') NOT NULL DEFAULT 'event',
  starts_at DATETIME NOT NULL,
  ends_at DATETIME DEFAULT NULL,
  opponent VARCHAR(190) NOT NULL DEFAULT '',
  location VARCHAR(190) NOT NULL DEFAULT '',
  link_url VARCHAR(255) NOT NULL DEFAULT '',
  notes TEXT DEFAULT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('scheduled','cancelled','finished') NOT NULL DEFAULT 'scheduled',
  sort_order INT(11) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_eventcalendar_starts_at (starts_at),
  KEY idx_eventcalendar_type (event_type),
  KEY idx_eventcalendar_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_eventcalendar_settings (
  setting_key VARCHAR(80) NOT NULL,
  setting_value TEXT DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("INSERT IGNORE INTO plugins_eventcalendar_settings (setting_key, setting_value) VALUES
('calendar_mode', 'club')");

safe_query("INSERT IGNORE INTO plugins_eventcalendar_events
    (id, title, event_type, starts_at, ends_at, opponent, location, notes, is_featured, status, sort_order)
VALUES
    (1, 'Heimspiel gegen FC Blau-Weiss', 'match', DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 18 HOUR, NULL, 'FC Blau-Weiss', 'Sportpark Musterstadt', 'Kreisliga A / Heimspiel', 1, 'scheduled', 10),
    (2, 'Sommerturnier U13', 'tournament', DATE_ADD(CURDATE(), INTERVAL 21 DAY) + INTERVAL 10 HOUR, NULL, '', 'Sportpark Musterstadt', 'Jugendturnier mit Gästeteams aus der Region.', 0, 'scheduled', 20),
    (3, 'Clanwar Training Match', 'clanwar', DATE_ADD(CURDATE(), INTERVAL 28 DAY) + INTERVAL 20 HOUR, NULL, 'NX Rivals', 'Online', 'Best of 3 Vorbereitungsspiel.', 0, 'scheduled', 30),
    (4, 'Sponsorengespräch', 'business', DATE_ADD(CURDATE(), INTERVAL 35 DAY) + INTERVAL 17 HOUR, NULL, '', 'Vereinsheim', 'Termin mit lokalen Partnern.', 0, 'scheduled', 40)");
