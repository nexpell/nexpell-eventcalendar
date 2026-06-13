<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;

global $_database;

AccessControl::checkAdminAccess('eventcalendar');
require_once __DIR__ . '/../eventcalendar-functions.php';
eventcalendar_ensure_schema($_database);

echo '<link rel="stylesheet" href="/includes/plugins/eventcalendar/css/eventcalendar.css">';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function eventcalendar_admin_url(string $action = '', int $id = 0): string
{
    $parts = ['site=admin_eventcalendar'];
    if ($action !== '') {
        $parts[] = 'action=' . rawurlencode($action);
    }
    if ($id > 0) {
        $parts[] = 'id=' . $id;
    }
    return 'admincenter.php?' . implode('&', $parts);
}

function eventcalendar_post(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function eventcalendar_admin_event(mysqli $database, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $database->prepare('SELECT * FROM plugins_eventcalendar_events WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
        nx_redirect(eventcalendar_admin_url(), 'danger', 'transaction_invalid', false);
    }

    if (isset($_POST['save_calendar_settings'])) {
        $calendarMode = eventcalendar_valid_mode(eventcalendar_post('calendar_mode'));
        eventcalendar_set_setting($_database, 'calendar_mode', $calendarMode);
        nx_redirect(eventcalendar_admin_url(), 'success', 'alert_saved', false);
    }

    if (isset($_POST['delete_event'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $_database->prepare('DELETE FROM plugins_eventcalendar_events WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            nx_audit_delete('admin_eventcalendar', (string)$id, (string)$id, eventcalendar_admin_url());
        }
        nx_redirect(eventcalendar_admin_url(), 'success', 'alert_deleted', false);
    }

    if (isset($_POST['save_event'])) {
        $id = (int)($_POST['id'] ?? 0);
        $title = eventcalendar_post('title');
        $eventType = eventcalendar_post('event_type');
        $allowedTypes = array_keys(eventcalendar_type_labels(eventcalendar_calendar_mode($_database)));
        if (!in_array($eventType, $allowedTypes, true)) {
            $eventType = 'event';
        }
        $date = eventcalendar_post('event_date');
        $time = eventcalendar_post('event_time');
        $startsAt = trim($date . ' ' . ($time !== '' ? $time : '00:00')) . ':00';
        $endsAtRaw = eventcalendar_post('ends_at');
        $endsAt = $endsAtRaw !== '' ? str_replace('T', ' ', $endsAtRaw) . ':00' : null;
        $opponent = eventcalendar_post('opponent');
        $location = eventcalendar_post('location');
        $linkUrl = eventcalendar_post('link_url');
        $notes = eventcalendar_post('notes');
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $status = eventcalendar_post('status');
        if (!in_array($status, ['scheduled', 'cancelled', 'finished'], true)) {
            $status = 'scheduled';
        }
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($title === '' || $date === '') {
            nx_redirect($id > 0 ? eventcalendar_admin_url('edit', $id) : eventcalendar_admin_url('new'), 'danger', 'alert_missing_fields', false);
        }

        if ($id > 0) {
            $stmt = $_database->prepare("UPDATE plugins_eventcalendar_events SET title = ?, event_type = ?, starts_at = ?, ends_at = ?, opponent = ?, location = ?, link_url = ?, notes = ?, is_featured = ?, status = ?, sort_order = ? WHERE id = ?");
            $stmt->bind_param('ssssssssisii', $title, $eventType, $startsAt, $endsAt, $opponent, $location, $linkUrl, $notes, $isFeatured, $status, $sortOrder, $id);
            $stmt->execute();
            $stmt->close();
            nx_audit_update('admin_eventcalendar', (string)$id, true, $title, eventcalendar_admin_url('edit', $id));
            nx_redirect(eventcalendar_admin_url('edit', $id), 'success', 'alert_saved', false);
        }

        $stmt = $_database->prepare("INSERT INTO plugins_eventcalendar_events (title, event_type, starts_at, ends_at, opponent, location, link_url, notes, is_featured, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssssssisi', $title, $eventType, $startsAt, $endsAt, $opponent, $location, $linkUrl, $notes, $isFeatured, $status, $sortOrder);
        $stmt->execute();
        $newId = (int)$_database->insert_id;
        $stmt->close();
        nx_audit_create('admin_eventcalendar', (string)$newId, $title, eventcalendar_admin_url('edit', $newId));
        nx_redirect(eventcalendar_admin_url('edit', $newId), 'success', 'alert_saved', false);
    }
}

$action = trim((string)($_GET['action'] ?? ''));
$id = (int)($_GET['id'] ?? 0);
$selected = eventcalendar_admin_event($_database, $id);
$calendarMode = eventcalendar_calendar_mode($_database);
$contextLabels = eventcalendar_context_labels($calendarMode);

$events = [];
$res = $_database->query("SELECT * FROM plugins_eventcalendar_events ORDER BY starts_at DESC, sort_order ASC, id DESC LIMIT 200");
while ($res && ($row = $res->fetch_assoc())) {
    $events[] = $row;
}

$isForm = ($action === 'new') || ($action === 'edit' && $selected !== null);
$formEvent = $selected ?: [
    'id' => 0,
    'title' => '',
    'event_type' => 'event',
    'starts_at' => date('Y-m-d H:i:00'),
    'ends_at' => '',
    'opponent' => '',
    'location' => '',
    'link_url' => '',
    'notes' => '',
    'is_featured' => 0,
    'status' => 'scheduled',
    'sort_order' => 0,
];
$startTs = strtotime((string)$formEvent['starts_at']) ?: time();
?>
<div class="card shadow-sm mt-4 eventcalendar-admin">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
      <div class="card-title mb-0"><i class="bi bi-calendar-event"></i> Eventkalender</div>
      <div class="d-flex gap-2">
        <?php if ($isForm): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?= eventcalendar_h(eventcalendar_admin_url()) ?>">Zurueck</a>
        <?php endif; ?>
        <a class="btn btn-primary btn-sm" href="<?= eventcalendar_h(eventcalendar_admin_url('new')) ?>">Termin eintragen</a>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="post" class="border rounded-3 p-3 mb-4 bg-light-subtle">
      <input type="hidden" name="csrf_token" value="<?= eventcalendar_h((string)$_SESSION['csrf_token']) ?>">
      <input type="hidden" name="save_calendar_settings" value="1">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-md-6 col-lg-4">
          <label class="form-label">Kalender verwenden f&uuml;r</label>
          <select class="form-select" name="calendar_mode">
            <?php foreach (eventcalendar_mode_labels() as $mode => $label): ?>
              <option value="<?= eventcalendar_h($mode) ?>" <?= $mode === $calendarMode ? 'selected' : '' ?>><?= eventcalendar_h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-auto">
          <button class="btn btn-primary d-inline-flex align-items-center gap-1 w-auto">
            <i class="bi bi-check2"></i> Einstellung speichern
          </button>
        </div>
        <div class="col-12 col-lg">
          <div class="form-text mb-1">Passt Begriffe wie Art, Kontakt, Ort und Beschreibung an den Einsatzzweck an.</div>
        </div>
      </div>
    </form>

    <?php if ($isForm): ?>
      <form method="post" class="border rounded-3 p-3 p-lg-4 bg-light-subtle">
        <input type="hidden" name="csrf_token" value="<?= eventcalendar_h((string)$_SESSION['csrf_token']) ?>">
        <input type="hidden" name="save_event" value="1">
        <input type="hidden" name="id" value="<?= (int)$formEvent['id'] ?>">
        <div class="row g-3">
          <div class="col-12 col-lg-6">
            <label class="form-label">Titel</label>
            <input class="form-control" name="title" value="<?= eventcalendar_h((string)$formEvent['title']) ?>" required>
          </div>
          <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label">Art</label>
            <select class="form-select" name="event_type">
              <?php foreach (eventcalendar_type_labels($calendarMode) as $type => $label): ?>
                <option value="<?= eventcalendar_h($type) ?>" <?= $type === (string)$formEvent['event_type'] ? 'selected' : '' ?>><?= eventcalendar_h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <?php foreach (eventcalendar_status_labels() as $status => $label): ?>
                <option value="<?= eventcalendar_h($status) ?>" <?= $status === (string)$formEvent['status'] ? 'selected' : '' ?>><?= eventcalendar_h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Datum</label>
            <input class="form-control" type="date" name="event_date" value="<?= date('Y-m-d', $startTs) ?>" required>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Uhrzeit</label>
            <input class="form-control" type="time" name="event_time" value="<?= date('H:i', $startTs) ?>">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Ende optional</label>
            <input class="form-control" type="datetime-local" name="ends_at" value="<?= !empty($formEvent['ends_at']) ? eventcalendar_h(date('Y-m-d\TH:i', strtotime((string)$formEvent['ends_at']))) : '' ?>">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label"><?= eventcalendar_h($contextLabels['opponent']) ?></label>
            <input class="form-control" name="opponent" value="<?= eventcalendar_h((string)$formEvent['opponent']) ?>">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label"><?= eventcalendar_h($contextLabels['location']) ?></label>
            <input class="form-control" name="location" value="<?= eventcalendar_h((string)$formEvent['location']) ?>">
          </div>
          <div class="col-12 col-md-8">
            <label class="form-label">Link</label>
            <input class="form-control" name="link_url" value="<?= eventcalendar_h((string)$formEvent['link_url']) ?>" placeholder="index.php?site=news oder https://...">
          </div>
          <div class="col-12 col-md-2">
            <label class="form-label">Sortierung</label>
            <input class="form-control" type="number" name="sort_order" value="<?= (int)$formEvent['sort_order'] ?>">
          </div>
          <div class="col-12 col-md-2 d-flex align-items-end">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" <?= !empty($formEvent['is_featured']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="is_featured">Als Highlight anzeigen</label>
              <div class="form-text">Hebt den Termin in Listen und Spielplan-Widgets optisch hervor.</div>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label"><?= eventcalendar_h($contextLabels['notes']) ?></label>
            <textarea class="form-control" name="notes" rows="4"><?= eventcalendar_h((string)$formEvent['notes']) ?></textarea>
          </div>
        </div>
        <div class="text-end mt-4">
          <button class="btn btn-primary">Speichern</button>
        </div>
      </form>
    <?php else: ?>
      <?php if (empty($events)): ?>
        <div class="alert alert-info mb-0">Noch keine Termine eingetragen.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Datum</th><th>Titel</th><th>Art</th><th><?= eventcalendar_h($contextLabels['location']) ?></th><th>Status</th><th class="text-end">Aktionen</th></tr></thead>
            <tbody>
              <?php foreach ($events as $event): ?>
                <tr>
                  <td><?= eventcalendar_h(eventcalendar_format_date((string)$event['starts_at'])) ?><br><span class="text-muted small"><?= eventcalendar_h(eventcalendar_format_time((string)$event['starts_at'])) ?> Uhr</span></td>
                  <td><strong><?= eventcalendar_h((string)$event['title']) ?></strong><?php if (!empty($event['opponent'])): ?><br><span class="text-muted small"><?= eventcalendar_h((string)$event['opponent']) ?></span><?php endif; ?></td>
                  <td><span class="badge bg-secondary"><?= eventcalendar_h(eventcalendar_type_label((string)$event['event_type'], $calendarMode)) ?></span></td>
                  <td><?= eventcalendar_h((string)$event['location']) ?></td>
                  <td><?= eventcalendar_h(eventcalendar_status_label((string)$event['status'])) ?></td>
                  <td class="text-end">
                    <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                      <a class="btn btn-warning btn-sm d-inline-flex align-items-center gap-1 w-auto" href="<?= eventcalendar_h(eventcalendar_admin_url('edit', (int)$event['id'])) ?>">
                        <i class="bi bi-pencil-square"></i> &Auml;ndern
                      </a>
                      <form method="post" class="d-inline" onsubmit="return confirm('Termin wirklich löschen?');">
                        <input type="hidden" name="csrf_token" value="<?= eventcalendar_h((string)$_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="delete_event" value="1">
                        <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
                        <button class="btn btn-danger btn-sm d-inline-flex align-items-center gap-1 w-auto">
                          <i class="bi bi-trash3"></i> L&ouml;schen
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
