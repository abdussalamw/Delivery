<?php
header('Content-Type: application/json');
$action = $_GET['action'] ?? 'status';

// مسار البوت — غيّره إن لزم الأمر
$BOT_NAME = 'delivery-bot';

function run($cmd) {
    $output = shell_exec($cmd . ' 2>&1');
    return $output ?: '';
}

switch($action) {
    case 'start':
        $out = run("pm2 start C:\\xampp\\htdocs\\Delivery\\bot\\bot.js --name $BOT_NAME");
        $status = run("pm2 jlist");
        $list = json_decode($status, true);
        $online = false;
        if(is_array($list)) {
            foreach($list as $proc) {
                if($proc['name'] === $BOT_NAME && $proc['pm2_env']['status'] === 'online') {
                    $online = true; break;
                }
            }
        }
        echo json_encode(['status' => $online ? 'online' : 'stopped', 'output' => $out]);
        break;

    case 'stop':
        $out = run("pm2 stop $BOT_NAME");
        echo json_encode(['status' => 'stopped', 'output' => $out]);
        break;

    case 'restart':
        $out = run("pm2 restart $BOT_NAME");
        $status_raw = run("pm2 jlist");
        $list = json_decode($status_raw, true);
        $online = false;
        if(is_array($list)) {
            foreach($list as $proc) {
                if($proc['name'] === $BOT_NAME && $proc['pm2_env']['status'] === 'online') {
                    $online = true; break;
                }
            }
        }
        echo json_encode(['status' => $online ? 'online' : 'stopped', 'output' => $out]);
        break;

    case 'status':
    default:
        $status_raw = run("pm2 jlist");
        $list = json_decode($status_raw, true);
        $found = false;
        $st = 'stopped';
        $out_lines = [];
        if(is_array($list)) {
            foreach($list as $proc) {
                if($proc['name'] === $BOT_NAME) {
                    $found = true;
                    $st = $proc['pm2_env']['status'];
                    $out_lines[] = "اسم العملية: " . $proc['name'];
                    $out_lines[] = "الحالة: " . $st;
                    $out_lines[] = "PID: " . ($proc['pid'] ?? '—');
                    $out_lines[] = "وقت الإعادة: " . ($proc['pm2_env']['restart_time'] ?? 0) . " مرة";
                    $out_lines[] = "ذاكرة: " . round(($proc['monit']['memory'] ?? 0)/1024/1024, 1) . " MB";
                    $out_lines[] = "CPU: " . ($proc['monit']['cpu'] ?? 0) . "%";
                    break;
                }
            }
        }
        if(!$found) {
            $ver = run("pm2 --version");
            if(empty(trim($ver)) || str_contains(strtolower($ver), 'not recognized')) {
                $out_lines = ['❌ PM2 غير مُثبَّت على هذا الخادم.', 'قم بتشغيل: npm install -g pm2'];
                $st = 'not_installed';
            } else {
                $out_lines = ["PM2 مُثبَّت (v".trim($ver).")", "❌ عملية '$BOT_NAME' غير موجودة أو متوقفة"];
                $st = 'not_found';
            }
        }
        echo json_encode(['status' => $st, 'output' => implode("\n", $out_lines)]);
        break;
}
?>
