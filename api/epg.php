<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');
$start = "$date 00:00:00";
$end = "$date 23:59:59";

$channels = $pdo->query("SELECT id, name, category, stream_url, logo_url FROM channels WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$programStmt = $pdo->prepare("SELECT channel_id, title, description, start_time, end_time FROM epg_programs WHERE channel_id = ? AND start_time BETWEEN ? AND ? ORDER BY start_time");

foreach ($channels as &$ch) {
    $programStmt->execute([$ch['id'], $start, $end]);
    $programs = [];
    foreach ($programStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $programs[] = [
            'title' => $p['title'],
            'description' => $p['description'],
            'start' => $p['start_time'],
            'end' => $p['end_time'],
            'start_time' => date('H:i', strtotime($p['start_time'])),
            'end_time' => date('H:i', strtotime($p['end_time']))
        ];
    }
    if (empty($programs)) {
        $programs[] = [
            'title' => 'No EPG data',
            'description' => '',
            'start' => $start,
            'end' => $end,
            'start_time' => '00:00',
            'end_time' => '23:59'
        ];
    }
    $ch['programs'] = $programs;
}

echo json_encode(['channels' => $channels]);
