<?php
global $_database;

require_once __DIR__ . '/eventcalendar-functions.php';
eventcalendar_ensure_schema($_database);

safe_query("INSERT IGNORE INTO plugins_eventcalendar_events
    (id, title, event_type, starts_at, ends_at, opponent, location, notes, is_featured, status, sort_order)
VALUES
    (1, 'Heimspiel gegen FC Blau-Weiss', 'match', DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 18 HOUR, NULL, 'FC Blau-Weiss', 'Sportpark Musterstadt', 'Kreisliga A / Heimspiel', 1, 'scheduled', 10),
    (2, 'Sommerturnier U13', 'tournament', DATE_ADD(CURDATE(), INTERVAL 21 DAY) + INTERVAL 10 HOUR, NULL, '', 'Sportpark Musterstadt', 'Jugendturnier mit Gaesteteams aus der Region.', 0, 'scheduled', 20),
    (3, 'Clanwar Training Match', 'clanwar', DATE_ADD(CURDATE(), INTERVAL 28 DAY) + INTERVAL 20 HOUR, NULL, 'NX Rivals', 'Online', 'Best of 3 Vorbereitungsspiel.', 0, 'scheduled', 30),
    (4, 'Sponsorengespraech', 'business', DATE_ADD(CURDATE(), INTERVAL 35 DAY) + INTERVAL 17 HOUR, NULL, '', 'Vereinsheim', 'Termin mit lokalen Partnern.', 0, 'scheduled', 40)");

safe_query("
    INSERT IGNORE INTO settings_plugins
        (pluginID, modulname, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar)
    VALUES
        ('', 'eventcalendar', 'admin_eventcalendar', 1, 'T-Seven', 'https://www.nexpell.de', 'eventcalendar', '', '1.0.0', 'includes/plugins/eventcalendar/', 1, 1, 1, 1, 'deactivated')
");

safe_query("
    INSERT INTO settings_widgets
        (widget_key, title, modulname, plugin, description, allowed_zones, active, version, created_at)
    VALUES
        ('widget_eventcalendar_content', 'Eventkalender Spielplan Widget', 'eventcalendar', 'eventcalendar', 'Grosses Spielplan-Widget fuer Veranstaltungen, Turniere, Clanwars und Termine.', 'maintop,mainbottom', 1, '1.0.0', NOW())
    ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        modulname = VALUES(modulname),
        plugin = VALUES(plugin),
        description = VALUES(description),
        allowed_zones = VALUES(allowed_zones),
        active = VALUES(active),
        version = VALUES(version)
");

safe_query("
    INSERT IGNORE INTO settings_plugins_lang
        (content_key, language, content, modulname, updated_at)
    VALUES
        ('plugin_name_eventcalendar', 'de', 'Eventkalender', 'eventcalendar', NOW()),
        ('plugin_name_eventcalendar', 'en', 'Event calendar', 'eventcalendar', NOW()),
        ('plugin_name_eventcalendar', 'it', 'Calendario eventi', 'eventcalendar', NOW()),
        ('plugin_info_eventcalendar', 'de', 'Kalender fuer Veranstaltungen, Turniere, Clanwars und Businesstermine mit Spielplan-Widget.', 'eventcalendar', NOW()),
        ('plugin_info_eventcalendar', 'en', 'Calendar for events, tournaments, clan wars and business appointments with schedule widget.', 'eventcalendar', NOW()),
        ('plugin_info_eventcalendar', 'it', 'Calendario per eventi, tornei, clan war e appuntamenti business con widget programma.', 'eventcalendar', NOW())
    ON DUPLICATE KEY UPDATE
        content = VALUES(content),
        modulname = VALUES(modulname),
        updated_at = VALUES(updated_at)
");

safe_query("
    INSERT IGNORE INTO settings_plugins_installed
        (name, modulname, description, version, author, url, folder, installed_date)
    VALUES
        ('Eventkalender', 'eventcalendar', 'Kalender fuer Veranstaltungen, Turniere, Clanwars und Businesstermine.', '1.0.0', 'nexpell-team', 'https://www.nexpell.de', 'eventcalendar', NOW())
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        description = VALUES(description),
        version = VALUES(version),
        author = VALUES(author),
        url = VALUES(url),
        folder = VALUES(folder),
        installed_date = NOW()
");

$linkID = 0;
$linkRes = safe_query("
    SELECT linkID FROM navigation_dashboard_links
    WHERE modulname = 'eventcalendar' AND url = 'admincenter.php?site=admin_eventcalendar'
    ORDER BY linkID ASC LIMIT 1
");
if ($linkRes && ($linkRow = mysqli_fetch_assoc($linkRes))) {
    $linkID = (int)($linkRow['linkID'] ?? 0);
} else {
    safe_query("
        INSERT IGNORE INTO navigation_dashboard_links
            (catID, modulname, url, sort)
        VALUES
            (8, 'eventcalendar', 'admincenter.php?site=admin_eventcalendar', 1)
    ");
    $linkID = (int)mysqli_insert_id($_database);
}

if ($linkID > 0) {
    safe_query("
        INSERT IGNORE INTO navigation_dashboard_lang
            (content_key, language, content, modulname, updated_at)
        VALUES
            ('nav_link_{$linkID}', 'de', 'Eventkalender', 'eventcalendar', NOW()),
            ('nav_link_{$linkID}', 'en', 'Event calendar', 'eventcalendar', NOW()),
            ('nav_link_{$linkID}', 'it', 'Calendario eventi', 'eventcalendar', NOW())
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            modulname = VALUES(modulname),
            updated_at = VALUES(updated_at)
    ");
}

$snavID = 0;
$snavRes = safe_query("
    SELECT snavID FROM navigation_website_sub
    WHERE modulname = 'eventcalendar' AND url = 'index.php?site=eventcalendar'
    ORDER BY snavID ASC LIMIT 1
");
if ($snavRes && ($snavRow = mysqli_fetch_assoc($snavRes))) {
    $snavID = (int)($snavRow['snavID'] ?? 0);
} else {
    safe_query("
        INSERT IGNORE INTO navigation_website_sub
            (mnavID, modulname, url, sort, indropdown, last_modified)
        VALUES
            (1, 'eventcalendar', 'index.php?site=eventcalendar', 1, 1, NOW())
    ");
    $snavID = (int)mysqli_insert_id($_database);
}

if ($snavID > 0) {
    safe_query("
        INSERT IGNORE INTO navigation_website_lang
            (content_key, language, content, modulname, updated_at)
        VALUES
            ('nav_sub_{$snavID}', 'de', 'Termine', 'eventcalendar', NOW()),
            ('nav_sub_{$snavID}', 'en', 'Events', 'eventcalendar', NOW()),
            ('nav_sub_{$snavID}', 'it', 'Eventi', 'eventcalendar', NOW())
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            modulname = VALUES(modulname),
            updated_at = VALUES(updated_at)
    ");
}

safe_query("INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
VALUES ('', 1, 'link', 'eventcalendar')");
?>
