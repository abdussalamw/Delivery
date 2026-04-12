<?php
header('Content-Type: application/json');
$action = $_GET['action'] ?? 'status';

// مسار البوت — غيّره إن لزم الأمر
$BOT_NAME = 'delivery-bot';

function run($cmd) {
    if (strpos($cmd, 'pm2') !== false && getenv('BOT_URL')) {
        return "Command '$cmd' skipped in Docker environment.";
    }
    $output = shell_exec($cmd . ' 2>&1');
    return $output ?: '';
}

if (getenv('BOT_URL')) {
    // Docker Environment Response Logic
    switch($action) {
        case 'start':
        case 'stop':
        case 'restart':
            echo json_encode(['status' => 'online', 'output' => "ℹ️ النظام يعمل داخل Docker.\nيتم التحكم في الحاويات تلقائياً عبر Docker Engine.\nالحالة الحالية: يعمل (Online)"]);
            break;
        case 'status':
        default:
            echo json_encode(['status' => 'online', 'output' => "✅ النظام يعمل بنظام Docker Containers\nاسم الحاوية: delivery_bot\nالحالة: Online (Docker Managed)\nنظام المهام: Docker Restart Policy"]);
    }
    exit;
}

// Local Environment (Windows/XAMPP) Logic
switch($action) {
    case 'start':
        $out = run("pm2 start C:\\xampp\\htdocs\\Delivery\\bot\\bot.js --name $BOT_NAME");
        echo json_encode(['status' => 'online', 'output' => $out]);
        break;

    case 'stop':
        $out = run("pm2 stop $BOT_NAME");
        echo json_encode(['status' => 'stopped', 'output' => $out]);
        break;

    case 'restart':
        $out = run("pm2 restart $BOT_NAME");
        echo json_encode(['status' => 'online', 'output' => $out]);
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
                $out_lines = ['❌ PM2 غير مُثبَّت على هذا الخادم.'];
                $st = 'not_installed';
            } else {
                $out_lines = ["PM2 مُثبَّت (v".trim($ver).")", "❌ عملية '$BOT_NAME' غير موجودة"];
                $st = 'not_found';
            }
        }
        echo json_encode(['status' => $st, 'output' => implode("\n", $out_lines)]);
        break;
}
