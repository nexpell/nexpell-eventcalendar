<?php
if (!function_exists('safe_query')) {
    die('Access denied');
}

global $_database, $plugin;

$version = isset($plugin['version']) ? (string)$plugin['version'] : ($version ?? '1.0.0');
$pluginPath = 'includes/plugins/eventcalendar/';

if (!function_exists('eventcalendar_sql')) {
    function eventcalendar_sql($value): string
    {
        return escape((string)$value);
    }
}

require_once __DIR__ . '/eventcalendar-functions.php';
eventcalendar_ensure_schema($_database);

safe_query("INSERT IGNORE INTO plugins_eventcalendar_events
    (id, title, event_type, starts_at, ends_at, opponent, location, notes, is_featured, status, sort_order)
VALUES
    (1, 'Heimspiel gegen FC Blau-Weiss', 'match', DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 18 HOUR, NULL, 'FC Blau-Weiss', 'Sportpark Musterstadt', 'Kreisliga A / Heimspiel', 1, 'scheduled', 10),
    (2, 'Sommerturnier U13', 'tournament', DATE_ADD(CURDATE(), INTERVAL 21 DAY) + INTERVAL 10 HOUR, NULL, '', 'Sportpark Musterstadt', 'Jugendturnier mit Gästeteams aus der Region.', 0, 'scheduled', 20),
    (3, 'Clanwar Training Match', 'clanwar', DATE_ADD(CURDATE(), INTERVAL 28 DAY) + INTERVAL 20 HOUR, NULL, 'NX Rivals', 'Online', 'Best of 3 Vorbereitungsspiel.', 0, 'scheduled', 30),
    (4, 'Sponsorengespräch', 'business', DATE_ADD(CURDATE(), INTERVAL 35 DAY) + INTERVAL 17 HOUR, NULL, '', 'Vereinsheim', 'Termin mit lokalen Partnern.', 0, 'scheduled', 40)");

PluginInstallerHelper::registerPlugin([
    'modulname'   => 'eventcalendar',
    'name'        => 'Eventkalender',
    'version'     => $version,
    'admin_file'  => 'admin_eventcalendar',
    'path'        => $pluginPath,
    'author'      => 'T-Seven',
    'website'     => 'https://www.nexpell.de',
    'index_link'  => 'eventcalendar',
    'hiddenfiles' => '',
    'sidebar'     => 'deactivated'
]);

safe_query("
    INSERT INTO settings_widgets
        (widget_key, title, modulname, plugin, description, allowed_zones, active, version, created_at)
    VALUES
        ('widget_eventcalendar_content', 'Eventkalender Spielplan Widget', 'eventcalendar', 'eventcalendar', 'Großes Spielplan-Widget für Veranstaltungen, Turniere, Clanwars und Termine.', 'maintop,mainbottom', 1, '" . eventcalendar_sql($version) . "', NOW())
    ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        modulname = VALUES(modulname),
        plugin = VALUES(plugin),
        description = VALUES(description),
        allowed_zones = VALUES(allowed_zones),
        active = VALUES(active),
        version = VALUES(version)
");

PluginInstallerHelper::registerPluginTranslation('eventcalendar', [
    'de' => 'Eventkalender',
    'en' => 'Event calendar',
    'it' => 'Calendario eventi'
]);

PluginInstallerHelper::addLanguageItem('plugin_info_eventcalendar', 'eventcalendar', [
    'de' => 'Kalender für Veranstaltungen, Turniere, Clanwars und Businesstermine mit Spielplan-Widget.',
    'en' => 'Calendar for events, tournaments, clan wars and business appointments with schedule widget.',
    'it' => 'Calendario per eventi, tornei, clan war e appuntamenti business con widget programma.'
]);

PluginInstallerHelper::registerAdminNavigation([
    'modulname' => 'eventcalendar',
    'url'       => 'admincenter.php?site=admin_eventcalendar',
    'catID'     => 8,
    'sort'      => 1,
    'labels'    => [
        'de' => 'Eventkalender',
        'en' => 'Event calendar',
        'it' => 'Calendario eventi'
    ]
]);

PluginInstallerHelper::registerWebsiteNavigation([
    'modulname'  => 'eventcalendar',
    'url'        => 'index.php?site=eventcalendar',
    'mnavID'     => 1,
    'sort'       => 1,
    'indropdown' => 1,
    'labels'     => [
        'de' => 'Termine',
        'en' => 'Events',
        'it' => 'Eventi'
    ]
]);

PluginInstallerHelper::registerAdminRight('eventcalendar');
