<?php
// modules/holidays/includes/api/export_ics.php
// Export du voyage au format iCalendar (.ics) : le voyage + une entrée par étape datée.
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT id, title, start_date, end_date, notes FROM pf_holidays WHERE id = ?");
$st->execute([$id]);
$h = $st->fetch(PDO::FETCH_ASSOC);
if (!$h || empty($h['start_date'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Voyage introuvable ou sans dates.";
    exit;
}

$esc = fn($s) => str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\;", "\\,", "\\n", "\\n", ""], (string)$s);
$day = fn($d) => date('Ymd', strtotime($d));
$dayAfter = fn($d) => date('Ymd', strtotime($d . ' +1 day')); // DTEND est exclusif pour les journées entières
$stamp = gmdate('Ymd\THis\Z');
$host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'househub');

$events = [];
$events[] = ['uid' => "trip-{$h['id']}@$host", 'start' => $day($h['start_date']), 'end' => $dayAfter($h['end_date'] ?: $h['start_date']), 'summary' => '✈️ ' . $h['title'], 'desc' => $h['notes'] ?? '', 'loc' => '', 'geo' => null];

$sti = $pdo->prepare("SELECT sort_order, location_name, MIN(lat) AS lat, MIN(lng) AS lng, MIN(step_start_date) AS sd, MAX(step_end_date) AS ed, MIN(step_type) AS step_type
                      FROM pf_holidays_items WHERE holiday_id = ? AND location_name IS NOT NULL GROUP BY sort_order, location_name ORDER BY sort_order");
$sti->execute([$id]);
foreach ($sti->fetchAll(PDO::FETCH_ASSOC) as $s) {
    if (empty($s['sd'])) continue;
    $prefix = $s['step_type'] === 'origin' ? '🛫 ' : ($s['step_type'] === 'destination' ? '🛬 ' : '📍 ');
    $events[] = ['uid' => "trip-{$h['id']}-step-{$s['sort_order']}@$host", 'start' => $day($s['sd']), 'end' => $dayAfter($s['ed'] ?: $s['sd']), 'summary' => $prefix . $s['location_name'], 'desc' => $h['title'], 'loc' => $s['location_name'], 'geo' => ($s['lat'] && $s['lng']) ? ((float)$s['lat'] . ';' . (float)$s['lng']) : null];
}

$out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//HouseHub//Voyages//FR\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n";
foreach ($events as $e) {
    $out .= "BEGIN:VEVENT\r\nUID:{$e['uid']}\r\nDTSTAMP:$stamp\r\nDTSTART;VALUE=DATE:{$e['start']}\r\nDTEND;VALUE=DATE:{$e['end']}\r\nSUMMARY:" . $esc($e['summary']) . "\r\n";
    if ($e['desc'] !== '') $out .= "DESCRIPTION:" . $esc($e['desc']) . "\r\n";
    if ($e['loc'] !== '')  $out .= "LOCATION:" . $esc($e['loc']) . "\r\n";
    if ($e['geo'])         $out .= "GEO:{$e['geo']}\r\n";
    $out .= "TRANSP:TRANSPARENT\r\nEND:VEVENT\r\n";
}
$out .= "END:VCALENDAR\r\n";

$file = preg_replace('/[^A-Za-z0-9_-]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $h['title']) ?: 'voyage');
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . trim($file, '_') . '.ics"');
echo $out;
