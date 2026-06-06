<?php
declare(strict_types=1);

if (!function_exists('eventcalendar_ensure_schema')) {
    function eventcalendar_ensure_schema(mysqli $database): void
    {
        static $done = false;
        if ($done) {
            return;
        }

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

        safe_query("INSERT IGNORE INTO plugins_eventcalendar_settings (setting_key, setting_value) VALUES ('calendar_mode', 'club')");

        $done = true;
    }
}

if (!function_exists('eventcalendar_h')) {
    function eventcalendar_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('eventcalendar_mode_labels')) {
    function eventcalendar_mode_labels(): array
    {
        return [
            'club' => 'Verein / Sport',
            'business' => 'Business',
            'gaming' => 'Gaming / eSports',
            'general' => 'Allgemein',
        ];
    }
}

if (!function_exists('eventcalendar_valid_mode')) {
    function eventcalendar_valid_mode(string $mode): string
    {
        return array_key_exists($mode, eventcalendar_mode_labels()) ? $mode : 'club';
    }
}

if (!function_exists('eventcalendar_get_setting')) {
    function eventcalendar_get_setting(mysqli $database, string $key, string $fallback = ''): string
    {
        eventcalendar_ensure_schema($database);
        $stmt = $database->prepare('SELECT setting_value FROM plugins_eventcalendar_settings WHERE setting_key = ? LIMIT 1');
        if (!$stmt) {
            return $fallback;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->bind_result($value);
        $found = $stmt->fetch();
        $stmt->close();
        return $found ? (string)$value : $fallback;
    }
}

if (!function_exists('eventcalendar_set_setting')) {
    function eventcalendar_set_setting(mysqli $database, string $key, string $value): void
    {
        eventcalendar_ensure_schema($database);
        $stmt = $database->prepare(
            'INSERT INTO plugins_eventcalendar_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('eventcalendar_calendar_mode')) {
    function eventcalendar_calendar_mode(?mysqli $database = null): string
    {
        if ($database === null) {
            $database = $GLOBALS['_database'] ?? null;
        }
        if (!($database instanceof mysqli)) {
            return 'club';
        }
        return eventcalendar_valid_mode(eventcalendar_get_setting($database, 'calendar_mode', 'club'));
    }
}

if (!function_exists('eventcalendar_context_labels')) {
    function eventcalendar_context_labels(?string $mode = null): array
    {
        $mode = eventcalendar_valid_mode($mode ?? eventcalendar_calendar_mode());
        $labels = [
            'club' => [
                'headline' => 'Veranstaltungen, Turniere und Termine',
                'intro' => 'Alle geplanten Spiele, Events, Turniere und Termine auf einen Blick.',
                'opponent' => 'Gegner / Ansprechpartner',
                'opponent_detail' => 'Gegner / Kontakt',
                'location' => 'Ort / Sportanlage',
                'notes' => 'Notizen',
                'widget_subline' => 'Verein',
            ],
            'business' => [
                'headline' => 'Meetings, Workshops und Termine',
                'intro' => 'Alle geplanten Kundentermine, Workshops und Business-Events auf einen Blick.',
                'opponent' => 'Kunde / Ansprechpartner',
                'opponent_detail' => 'Kunde / Kontakt',
                'location' => 'Ort / Meetingraum',
                'notes' => 'Agenda / Notizen',
                'widget_subline' => 'Business',
            ],
            'gaming' => [
                'headline' => 'Matches, Turniere und Community-Termine',
                'intro' => 'Alle geplanten Clanwars, Trainings, Turniere und Gaming-Events auf einen Blick.',
                'opponent' => 'Gegner / Team / Kontakt',
                'opponent_detail' => 'Gegner / Team',
                'location' => 'Server / Plattform / Ort',
                'notes' => 'Match-Infos / Notizen',
                'widget_subline' => 'Gaming',
            ],
            'general' => [
                'headline' => 'Termine und Veranstaltungen',
                'intro' => 'Alle geplanten Termine und Veranstaltungen auf einen Blick.',
                'opponent' => 'Kontakt / Bezug',
                'opponent_detail' => 'Kontakt / Bezug',
                'location' => 'Ort',
                'notes' => 'Notizen',
                'widget_subline' => 'Termine',
            ],
        ];
        return $labels[$mode];
    }
}

if (!function_exists('eventcalendar_type_labels')) {
    function eventcalendar_type_labels(?string $mode = null): array
    {
        $mode = eventcalendar_valid_mode($mode ?? eventcalendar_calendar_mode());
        if ($mode === 'business') {
            return [
                'event' => 'Veranstaltung',
                'tournament' => 'Workshop / Reihe',
                'clanwar' => 'Online-Event',
                'business' => 'Businesstermin',
                'training' => 'Schulung',
                'match' => 'Kundentermin',
            ];
        }
        if ($mode === 'gaming') {
            return [
                'event' => 'Community-Event',
                'tournament' => 'Turnier',
                'clanwar' => 'Clanwar / War',
                'business' => 'Partnertermin',
                'training' => 'Training',
                'match' => 'Match',
            ];
        }
        if ($mode === 'general') {
            return [
                'event' => 'Veranstaltung',
                'tournament' => 'Terminreihe',
                'clanwar' => 'Online-Termin',
                'business' => 'Besprechung',
                'training' => 'Schulung',
                'match' => 'Termin',
            ];
        }
        return [
            'event' => 'Veranstaltung',
            'tournament' => 'Turnier',
            'clanwar' => 'Clanwar / War',
            'business' => 'Sponsorentermin',
            'training' => 'Training',
            'match' => 'Spiel',
        ];
    }
}

if (!function_exists('eventcalendar_type_label')) {
    function eventcalendar_type_label(string $type, ?string $mode = null): string
    {
        $labels = eventcalendar_type_labels($mode);
        return $labels[$type] ?? $labels['event'];
    }
}

if (!function_exists('eventcalendar_status_labels')) {
    function eventcalendar_status_labels(): array
    {
        return [
            'scheduled' => 'Geplant',
            'cancelled' => 'Abgesagt',
            'finished' => 'Beendet',
        ];
    }
}

if (!function_exists('eventcalendar_status_label')) {
    function eventcalendar_status_label(string $status): string
    {
        $labels = eventcalendar_status_labels();
        return $labels[$status] ?? $labels['scheduled'];
    }
}

if (!function_exists('eventcalendar_type_badge_class')) {
    function eventcalendar_type_badge_class(string $type): string
    {
        return match ($type) {
            'tournament' => 'ec-badge--tournament',
            'clanwar' => 'ec-badge--clanwar',
            'business' => 'ec-badge--business',
            'training' => 'ec-badge--training',
            'match' => 'ec-badge--match',
            default => 'ec-badge--event',
        };
    }
}

if (!function_exists('eventcalendar_fetch_events')) {
    function eventcalendar_fetch_events(mysqli $database, int $limit = 30, bool $upcomingOnly = true): array
    {
        eventcalendar_ensure_schema($database);
        $limit = max(1, min(100, $limit));
        $where = $upcomingOnly ? "WHERE starts_at >= NOW() AND status = 'scheduled'" : '';
        $sql = "SELECT * FROM plugins_eventcalendar_events {$where} ORDER BY is_featured DESC, starts_at ASC, sort_order ASC, id ASC LIMIT {$limit}";
        $rows = [];
        $res = $database->query($sql);
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('eventcalendar_group_by_month')) {
    function eventcalendar_group_by_month(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $ts = strtotime((string)($row['starts_at'] ?? ''));
            if (!$ts) {
                continue;
            }
            $key = date('Y-m', $ts);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'label' => eventcalendar_month_label((int)date('n', $ts)),
                    'year' => date('Y', $ts),
                    'items' => [],
                ];
            }
            $groups[$key]['items'][] = $row;
        }
        return $groups;
    }
}

if (!function_exists('eventcalendar_month_label')) {
    function eventcalendar_month_label(int $month): string
    {
        $labels = [
            1 => 'JANUAR',
            2 => 'FEBRUAR',
            3 => 'MAERZ',
            4 => 'APRIL',
            5 => 'MAI',
            6 => 'JUNI',
            7 => 'JULI',
            8 => 'AUGUST',
            9 => 'SEPTEMBER',
            10 => 'OKTOBER',
            11 => 'NOVEMBER',
            12 => 'DEZEMBER',
        ];
        return $labels[$month] ?? '';
    }
}

if (!function_exists('eventcalendar_format_time')) {
    function eventcalendar_format_time(string $datetime): string
    {
        $ts = strtotime($datetime);
        return $ts ? date('H:i', $ts) : '';
    }
}

if (!function_exists('eventcalendar_format_date')) {
    function eventcalendar_format_date(string $datetime): string
    {
        $ts = strtotime($datetime);
        return $ts ? date('d.m.Y', $ts) : '';
    }
}
