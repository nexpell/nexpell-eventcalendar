<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

global $_database, $tpl, $languageService;

require_once __DIR__ . '/eventcalendar-functions.php';
eventcalendar_ensure_schema($_database);
$calendarMode = eventcalendar_calendar_mode($_database);
$contextLabels = eventcalendar_context_labels($calendarMode);

echo '<link rel="stylesheet" href="/includes/plugins/eventcalendar/css/eventcalendar.css">';

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars((string)($config['selected_style'] ?? ''), ENT_QUOTES, 'UTF-8');
echo $tpl->loadTemplate("eventcalendar", "head", [
    'class' => $class,
    'title' => 'Eventkalender',
    'subtitle' => 'Termine'
], 'plugin');

function eventcalendar_frontend_url(array $query): string
{
    $url = 'index.php?' . http_build_query($query);
    if (class_exists('\\nexpell\\SeoUrlHandler')) {
        return \nexpell\SeoUrlHandler::convertToSeoUrl($url);
    }
    return '/' . $url;
}

function eventcalendar_detail_url(int $id): string
{
    return eventcalendar_frontend_url([
        'site' => 'eventcalendar',
        'action' => 'show',
        'id' => $id,
    ]);
}

function eventcalendar_page_url(string $action = '', array $params = []): string
{
    $query = ['site' => 'eventcalendar'];
    if ($action !== '') {
        $query['action'] = $action;
    }
    foreach ($params as $key => $value) {
        if ($value !== '' && $value !== null) {
            $query[$key] = $value;
        }
    }
    return eventcalendar_frontend_url($query);
}

function eventcalendar_load_event(mysqli $database, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $database->prepare('SELECT * FROM plugins_eventcalendar_events WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function eventcalendar_request_path_parts(): array
{
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $parts = array_values(array_filter(explode('/', trim((string)$path, '/')), static fn($part) => $part !== ''));
    return array_map(static fn($part) => strtolower(rawurldecode($part)), $parts);
}

function eventcalendar_route_action(): string
{
    $action = trim((string)($_GET['action'] ?? ''));
    if ($action !== '') {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $action);
    }

    $parts = eventcalendar_request_path_parts();
    $pos = array_search('eventcalendar', $parts, true);
    if ($pos !== false && isset($parts[$pos + 1])) {
        $candidate = (string)$parts[$pos + 1];
        if (in_array($candidate, ['show', 'calendar'], true)) {
            return $candidate;
        }
        if (ctype_digit($candidate)) {
            return 'show';
        }
    }

    return '';
}

function eventcalendar_route_id(): int
{
    foreach (['id', 'event_id', 'termin', 'event', 'amp;id', 'amp;event_id'] as $key) {
        if (isset($_GET[$key]) && (int)$_GET[$key] > 0) {
            return (int)$_GET[$key];
        }
    }

    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if (preg_match('~(?:[?&](?:id|event_id|termin|event)=|/show/|/eventcalendar/)(\d+)~i', $uri, $match)) {
        return (int)$match[1];
    }

    $parts = eventcalendar_request_path_parts();
    if (in_array('eventcalendar', $parts, true)) {
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            if (ctype_digit($parts[$i])) {
                return (int)$parts[$i];
            }
        }
    }

    return 0;
}

function eventcalendar_route_month(): string
{
    $month = trim((string)($_GET['month'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}$/', $month)) {
        return $month;
    }

    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if (preg_match('~/calendar/(\d{4}-\d{2})~i', $uri, $match)) {
        return $match[1];
    }

    return date('Y-m');
}

$action = eventcalendar_route_action();
?>
<section class="eventcalendar-page container my-4">
  <div class="eventcalendar-page__head">
    <span>Kalender</span>
    <h2><?= eventcalendar_h($contextLabels['headline']) ?></h2>
    <p><?= eventcalendar_h($contextLabels['intro']) ?></p>
    <div class="eventcalendar-page__actions">
      <a href="<?= eventcalendar_h(eventcalendar_page_url()) ?>">Liste</a>
      <a href="<?= eventcalendar_h(eventcalendar_page_url('calendar')) ?>">Monatsansicht</a>
    </div>
  </div>

  <?php if ($action === 'show'):
    $event = eventcalendar_load_event($_database, eventcalendar_route_id());
    if ($event === null): ?>
      <div class="alert alert-warning">Dieser Termin wurde nicht gefunden.</div>
    <?php else:
        $externalUrl = trim((string)$event['link_url']);
        if ($externalUrl !== '' && !preg_match('~^(?:https?://|/|index\.php\?)~i', $externalUrl)) {
            $externalUrl = 'https://' . $externalUrl;
        }
    ?>
      <article class="eventcalendar-detail">
        <a class="eventcalendar-backlink" href="<?= eventcalendar_h(eventcalendar_page_url()) ?>">Zurueck zur Liste</a>
        <div class="eventcalendar-detail__top">
          <div class="eventcalendar-list-date">
            <strong><?= eventcalendar_h(date('d', strtotime((string)$event['starts_at']))) ?></strong>
            <span><?= eventcalendar_h(substr(eventcalendar_month_label((int)date('n', strtotime((string)$event['starts_at']))), 0, 3)) ?></span>
          </div>
          <div>
            <span class="ec-badge <?= eventcalendar_h(eventcalendar_type_badge_class((string)$event['event_type'])) ?>"><?= eventcalendar_h(eventcalendar_type_label((string)$event['event_type'], $calendarMode)) ?></span>
            <h2><?= eventcalendar_h((string)$event['title']) ?></h2>
            <p><?= eventcalendar_h(eventcalendar_format_date((string)$event['starts_at'])) ?>, <?= eventcalendar_h(eventcalendar_format_time((string)$event['starts_at'])) ?> Uhr</p>
          </div>
        </div>
        <dl class="eventcalendar-detail__facts">
          <?php if (!empty($event['opponent'])): ?><div><dt><?= eventcalendar_h($contextLabels['opponent_detail']) ?></dt><dd><?= eventcalendar_h((string)$event['opponent']) ?></dd></div><?php endif; ?>
          <?php if (!empty($event['location'])): ?><div><dt><?= eventcalendar_h($contextLabels['location']) ?></dt><dd><?= eventcalendar_h((string)$event['location']) ?></dd></div><?php endif; ?>
          <?php if (!empty($event['ends_at'])): ?><div><dt>Ende</dt><dd><?= eventcalendar_h(eventcalendar_format_date((string)$event['ends_at'])) ?>, <?= eventcalendar_h(eventcalendar_format_time((string)$event['ends_at'])) ?> Uhr</dd></div><?php endif; ?>
          <div><dt>Status</dt><dd><?= eventcalendar_h(eventcalendar_status_label((string)$event['status'])) ?></dd></div>
        </dl>
        <?php if (!empty($event['notes'])): ?>
          <div class="eventcalendar-detail__notes"><?= nl2br(eventcalendar_h((string)$event['notes'])) ?></div>
        <?php endif; ?>
        <?php if ($externalUrl !== ''): ?>
          <a class="eventcalendar-list-link" href="<?= eventcalendar_h($externalUrl) ?>">Weiterführender Link</a>
        <?php endif; ?>
      </article>
    <?php endif; ?>

  <?php elseif ($action === 'calendar'):
    $monthRaw = eventcalendar_route_month();
    if (!preg_match('/^\d{4}-\d{2}$/', $monthRaw)) {
        $monthRaw = date('Y-m');
    }
    $monthStart = strtotime($monthRaw . '-01 00:00:00') ?: strtotime(date('Y-m-01 00:00:00'));
    $monthEnd = strtotime('+1 month', $monthStart);
    $calendarEvents = [];
    $stmt = $_database->prepare("SELECT * FROM plugins_eventcalendar_events WHERE starts_at >= ? AND starts_at < ? ORDER BY is_featured DESC, starts_at ASC, sort_order ASC, id ASC");
    $from = date('Y-m-d H:i:s', $monthStart);
    $to = date('Y-m-d H:i:s', $monthEnd);
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $day = (int)date('j', strtotime((string)$row['starts_at']));
        $calendarEvents[$day][] = $row;
    }
    $stmt->close();
    $daysInMonth = (int)date('t', $monthStart);
    $firstWeekday = (int)date('N', $monthStart);
    $prevMonth = date('Y-m', strtotime('-1 month', $monthStart));
    $nextMonth = date('Y-m', strtotime('+1 month', $monthStart));
    $todayKey = date('Y-m-d');
  ?>
    <div class="eventcalendar-month">
      <div class="eventcalendar-month__bar">
        <a href="<?= eventcalendar_h(eventcalendar_page_url('calendar', ['month' => $prevMonth])) ?>">Zurueck</a>
        <h3><?= eventcalendar_h(eventcalendar_month_label((int)date('n', $monthStart))) ?> <?= eventcalendar_h(date('Y', $monthStart)) ?></h3>
        <a href="<?= eventcalendar_h(eventcalendar_page_url('calendar', ['month' => $nextMonth])) ?>">Weiter</a>
      </div>
      <div class="eventcalendar-month__weekdays">
        <span>Mo</span><span>Di</span><span>Mi</span><span>Do</span><span>Fr</span><span>Sa</span><span>So</span>
      </div>
      <div class="eventcalendar-month__grid">
        <?php for ($blank = 1; $blank < $firstWeekday; $blank++): ?><div class="eventcalendar-month__day is-empty"></div><?php endfor; ?>
        <?php for ($day = 1; $day <= $daysInMonth; $day++):
          $dateKey = date('Y-m-', $monthStart) . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
          $isToday = $dateKey === $todayKey;
        ?>
          <div class="eventcalendar-month__day<?= $isToday ? ' is-today' : '' ?>">
            <strong><?= $day ?></strong>
            <?php foreach (($calendarEvents[$day] ?? []) as $event): ?>
              <a class="eventcalendar-month__event<?= !empty($event['is_featured']) ? ' is-featured' : '' ?>" href="<?= eventcalendar_h(eventcalendar_detail_url((int)$event['id'])) ?>">
                <span><?= eventcalendar_h(eventcalendar_format_time((string)$event['starts_at'])) ?></span>
                <?= eventcalendar_h((string)$event['title']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>

  <?php else:
    $rows = eventcalendar_fetch_events($_database, 80, false);
    if (empty($rows)): ?>
      <div class="alert alert-info">Noch keine Termine vorhanden.</div>
    <?php else: ?>
      <div class="eventcalendar-list">
        <?php foreach ($rows as $event):
          $url = eventcalendar_detail_url((int)$event['id']);
        ?>
          <article class="eventcalendar-list-item<?= !empty($event['is_featured']) ? ' is-featured' : '' ?>">
            <div class="eventcalendar-list-date">
              <strong><?= eventcalendar_h(date('d', strtotime((string)$event['starts_at']))) ?></strong>
              <span><?= eventcalendar_h(substr(eventcalendar_month_label((int)date('n', strtotime((string)$event['starts_at']))), 0, 3)) ?></span>
            </div>
            <div>
              <span class="ec-badge <?= eventcalendar_h(eventcalendar_type_badge_class((string)$event['event_type'])) ?>"><?= eventcalendar_h(eventcalendar_type_label((string)$event['event_type'], $calendarMode)) ?></span>
              <?php if (!empty($event['is_featured'])): ?><span class="ec-badge ec-badge--featured">Highlight</span><?php endif; ?>
              <h3><?= eventcalendar_h((string)$event['title']) ?></h3>
              <p><?= eventcalendar_h(eventcalendar_format_date((string)$event['starts_at'])) ?>, <?= eventcalendar_h(eventcalendar_format_time((string)$event['starts_at'])) ?> Uhr<?php if (!empty($event['location'])): ?> / <?= eventcalendar_h((string)$event['location']) ?><?php endif; ?></p>
              <?php if (!empty($event['notes'])): ?><p class="eventcalendar-list-note"><?= eventcalendar_h((string)$event['notes']) ?></p><?php endif; ?>
            </div>
            <a class="eventcalendar-list-link" href="<?= eventcalendar_h($url) ?>">Details</a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
