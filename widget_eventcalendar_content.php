<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

global $_database;

require_once __DIR__ . '/eventcalendar-functions.php';
eventcalendar_ensure_schema($_database);
$calendarMode = eventcalendar_calendar_mode($_database);
$contextLabels = eventcalendar_context_labels($calendarMode);

$cssPath = __DIR__ . '/css/eventcalendar.css';
$cssVersion = is_readable($cssPath) ? (string)filemtime($cssPath) : '1';
echo '<link rel="stylesheet" href="/includes/plugins/eventcalendar/css/eventcalendar.css?v=' . eventcalendar_h($cssVersion) . '">';

if (!isset($cfg)) {
    $cfg = [];
} elseif ($cfg instanceof stdClass) {
    $cfg = (array)$cfg;
} elseif (!is_array($cfg)) {
    $cfg = [];
}

$limit = isset($cfg['limit']) ? max(1, min(24, (int)$cfg['limit'])) : 12;
$headline = isset($cfg['headline']) && trim((string)$cfg['headline']) !== '' ? trim((string)$cfg['headline']) : 'Spielplan';
$subline = isset($cfg['subline']) && trim((string)$cfg['subline']) !== '' ? trim((string)$cfg['subline']) : $contextLabels['widget_subline'];

$rows = eventcalendar_fetch_events($_database, $limit, true);
$groups = eventcalendar_group_by_month($rows);
$groupCount = count($groups);
$gridClass = 'eventcalendar-poster__grid';
if ($groupCount === 1) {
    $gridClass .= ' eventcalendar-poster__grid--one';
} elseif ($groupCount === 2) {
    $gridClass .= ' eventcalendar-poster__grid--two';
} elseif ($groupCount >= 3) {
    $gridClass .= ' eventcalendar-poster__grid--three';
}
?>
<section class="eventcalendar-poster" aria-label="Eventkalender Spielplan">
  <div class="eventcalendar-poster__content">
    <header class="eventcalendar-poster__header">
      <div class="eventcalendar-poster__title">
        <span><?= eventcalendar_h(strtoupper($subline)) ?></span>
        <strong><?= eventcalendar_h($headline) ?></strong>
      </div>
    </header>
    <?php if (empty($groups)): ?>
      <div class="eventcalendar-poster__empty">Noch keine kommenden Termine.</div>
    <?php else: ?>
      <div class="<?= eventcalendar_h($gridClass) ?>">
        <?php foreach ($groups as $group): ?>
          <div class="eventcalendar-poster__month">
            <h3><?= eventcalendar_h((string)$group['label']) ?></h3>
            <?php foreach ($group['items'] as $event):
              $ts = strtotime((string)$event['starts_at']);
              $type = (string)$event['event_type'];
              $label = $type === 'match'
                  ? (!empty($event['opponent']) && $calendarMode === 'club' ? (stripos((string)$event['location'], 'sportpark') !== false ? 'Heimspiel' : 'Auswaertsspiel') : eventcalendar_type_label($type, $calendarMode))
                  : eventcalendar_type_label($type, $calendarMode);
            ?>
              <div class="eventcalendar-poster__item<?= !empty($event['is_featured']) ? ' is-featured' : '' ?>">
                <span class="eventcalendar-poster__day"><?= $ts ? eventcalendar_h(date('d', $ts)) : '--' ?></span>
                <span class="eventcalendar-poster__meta">
                  <strong><?= eventcalendar_h(strtoupper($label)) ?><?= !empty($event['is_featured']) ? ' / HIGHLIGHT' : '' ?></strong>
                  <b><?= eventcalendar_h((string)$event['title']) ?></b>
                  <em><?= eventcalendar_h(eventcalendar_format_time((string)$event['starts_at'])) ?> Uhr<?php if (!empty($event['location'])): ?> / <?= eventcalendar_h((string)$event['location']) ?><?php endif; ?></em>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
