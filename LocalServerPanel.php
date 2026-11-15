<?php
// Универсальная серверная панель управления для Termux: LocalServerPanel (LSP Universal)
$prefix = getenv('PREFIX') ?: '/data/data/com.termux/files/usr';
$apache_conf = $prefix . '/etc/apache2/httpd.conf';
$nginx_conf  = $prefix . '/etc/nginx/nginx.conf';
$php_ini     = $prefix . '/lib/php.ini';
$error_log_path  = $prefix . '/var/log/apache2/error_log';
$access_log_path = $prefix . '/var/log/apache2/access_log';

if (!file_exists($error_log_path) && file_exists($prefix . '/var/log/nginx/error.log')) {
    $error_log_path = $prefix . '/var/log/nginx/error.log';
}
if (!file_exists($access_log_path) && file_exists($prefix . '/var/log/nginx/access.log')) {
    $access_log_path = $prefix . '/var/log/nginx/access.log';
}

// Настройки подключений к БД по умолчанию
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$default_sqlite_db = __DIR__ . '/lsp_database.db';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

session_start();
$base_dir = '/storage/emulated/0/Server/public';
if (!is_dir($base_dir)) {
    $base_dir = __DIR__;
}
if (!isset($_SESSION['doc_root'])) {
    $_SESSION['doc_root'] = $base_dir;
}
$current_doc_root = realpath($_SESSION['doc_root']) ?: $base_dir;

if (!isset($_SESSION['term_cwd']) || !is_dir($_SESSION['term_cwd'])) {
    $_SESSION['term_cwd'] = $current_doc_root;
}
if (!isset($_SESSION['term_history'])) {
    $_SESSION['term_history'] = [];
}

$projects_file = __DIR__ . '/projects.json';
if (!file_exists($projects_file)) {
    @file_put_contents($projects_file, json_encode([]));
}

// Вспомогательные функции
function format_size($bytes) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object))
                    rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                else
                    @unlink($dir . DIRECTORY_SEPARATOR . $object);
            }
        }
        @rmdir($dir);
    }
}

function copy_recursive($src, $dst) {
    if (is_dir($src)) {
        @mkdir($dst, 0755, true);
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                copy_recursive("$src/$file", "$dst/$file");
            }
        }
    } elseif (file_exists($src)) {
        @copy($src, $dst);
    }
}

function zip_add_recursive($zip, $path, $base_len) {
    $rel_path = substr($path, $base_len);
    if (is_dir($path)) {
        if ($rel_path !== '') $zip->addEmptyDir($rel_path);
        foreach (scandir($path) as $file) {
            if ($file !== '.' && $file !== '..') {
                zip_add_recursive($zip, $path . '/' . $file, $base_len);
            }
        }
    } else {
        $zip->addFile($path, $rel_path);
    }
}

$allowed_root = is_dir('/storage/emulated/0') ? '/storage/emulated/0' : dirname(__DIR__);
function safe_path($path, $fallback, $allowed_root) {
    $rp = realpath($path);
    if ($rp && (strpos($rp, $allowed_root) === 0 || strpos($rp, __DIR__) === 0)) {
        return $rp;
    }
    return $fallback;
}

// Детектор установленных и запущенных сервисов в Termux
function cmd_exists($bin) {
    global $prefix;
    if (file_exists("$prefix/bin/$bin") || file_exists("$prefix/sbin/$bin")) return true;
    $check = trim((string)@shell_exec("command -v " . escapeshellarg($bin) . " 2>/dev/null"));
    return $check !== '';
}

function proc_running($patterns) {
    foreach ((array)$patterns as $p) {
        $out = trim((string)@shell_exec("pgrep -f " . escapeshellarg($p) . " 2>/dev/null"));
        if ($out !== '') return true;
    }
    return false;
}

function get_services_list($prefix) {
    return [
        'apache' => [
            'name' => 'Apache',
            'installed' => cmd_exists('httpd') || cmd_exists('apachectl'),
            'running' => proc_running(['httpd']),
            'start_cmd' => 'apachectl start > /dev/null 2>&1 &',
            'stop_cmd' => 'apachectl stop > /dev/null 2>&1',
            'install_cmd' => 'pkg update -y && pkg install apache2 php-apache -y'
        ],
        'nginx' => [
            'name' => 'Nginx',
            'installed' => cmd_exists('nginx'),
            'running' => proc_running(['nginx:']),
            'start_cmd' => 'nginx > /dev/null 2>&1 &',
            'stop_cmd' => 'nginx -s stop > /dev/null 2>&1 || pkill -f nginx',
            'install_cmd' => 'pkg update -y && pkg install nginx -y'
        ],
        'php_fpm' => [
            'name' => 'PHP-FPM',
            'installed' => cmd_exists('php-fpm'),
            'running' => proc_running(['php-fpm']),
            'start_cmd' => 'php-fpm -D > /dev/null 2>&1 &',
            'stop_cmd' => 'pkill -f php-fpm',
            'install_cmd' => 'pkg update -y && pkg install php-fpm -y'
        ],
        'mariadb' => [
            'name' => 'MariaDB / MySQL',
            'installed' => cmd_exists('mariadbd') || cmd_exists('mysqld'),
            'running' => proc_running(['mariadbd', 'mysqld']),
            'start_cmd' => "mysqld_safe --datadir=$prefix/var/mysql > /dev/null 2>&1 &",
            'stop_cmd' => 'pkill -f mariadbd; pkill -f mysqld',
            'install_cmd' => 'pkg update -y && pkg install mariadb -y && mysql_install_db'
        ],
        'sqlite' => [
            'name' => 'SQLite 3',
            'installed' => cmd_exists('sqlite3') || extension_loaded('pdo_sqlite') || extension_loaded('sqlite3'),
            'running' => extension_loaded('pdo_sqlite') || extension_loaded('sqlite3'),
            'is_embedded' => true,
            'install_cmd' => 'pkg update -y && pkg install sqlite -y'
        ],
        'postgresql' => [
            'name' => 'PostgreSQL',
            'installed' => cmd_exists('postgres') || cmd_exists('pg_ctl'),
            'running' => proc_running(['postgres']),
            'start_cmd' => "pg_ctl -D $prefix/var/lib/postgresql start > /dev/null 2>&1 &",
            'stop_cmd' => "pg_ctl -D $prefix/var/lib/postgresql stop > /dev/null 2>&1",
            'install_cmd' => "pkg update -y && pkg install postgresql -y && mkdir -p $prefix/var/lib/postgresql && initdb $prefix/var/lib/postgresql"
        ],
        'mongodb' => [
            'name' => 'MongoDB',
            'installed' => cmd_exists('mongod'),
            'running' => proc_running(['mongod']),
            'start_cmd' => "mkdir -p $prefix/var/lib/mongodb && mongod --dbpath $prefix/var/lib/mongodb --fork --logpath $prefix/var/log/mongodb.log > /dev/null 2>&1 &",
            'stop_cmd' => 'pkill -f mongod',
            'install_cmd' => 'pkg update -y && pkg install tur-repo -y && pkg install mongodb -y'
        ],
        'redis' => [
            'name' => 'Redis',
            'installed' => cmd_exists('redis-server'),
            'running' => proc_running(['redis-server']),
            'start_cmd' => 'redis-server --daemonize yes > /dev/null 2>&1',
            'stop_cmd' => 'redis-cli shutdown > /dev/null 2>&1 || pkill -f redis-server',
            'install_cmd' => 'pkg update -y && pkg install redis -y'
        ],
        'python' => [
            'name' => 'Python (HTTP)',
            'installed' => cmd_exists('python') || cmd_exists('python3'),
            'running' => proc_running(['http.server', 'gunicorn', 'uvicorn', 'flask', 'manage.py']),
            'start_cmd' => 'python3 -m http.server 8081 > /dev/null 2>&1 &',
            'stop_cmd' => 'pkill -f "http.server"',
            'install_cmd' => 'pkg update -y && pkg install python -y'
        ],
        'nodejs' => [
            'name' => 'Node.js',
            'installed' => cmd_exists('node'),
            'running' => proc_running(['node ']),
            'start_cmd' => 'npx -y http-server -p 8082 > /dev/null 2>&1 &',
            'stop_cmd' => 'pkill -f "node"',
            'install_cmd' => 'pkg update -y && pkg install nodejs -y'
        ],
        'vnc' => [
            'name' => 'TigerVNC (:1)',
            'installed' => cmd_exists('vncserver') || cmd_exists('Xtigervnc'),
            'running' => proc_running(['Xtigervnc', 'Xvnc', 'vncserver']),
            'start_cmd' => 'vncserver :1 > /dev/null 2>&1 &',
            'stop_cmd' => 'vncserver -kill :1 > /dev/null 2>&1',
            'install_cmd' => 'pkg update -y && pkg install x11-repo -y && pkg install tigervnc -y'
        ]
    ];
}

// Автоопределение доступных типов баз данных
function detect_available_dbs($services, $default_sqlite_db, $base_dir) {
    $available = [];
    // 1. SQLite
    if (extension_loaded('pdo_sqlite') || extension_loaded('sqlite3') || $services['sqlite']['installed']) {
        $sqlite_files = [];
        foreach ([$default_sqlite_db] as $df) {
            if (!file_exists($df)) @touch($df);
            if (file_exists($df)) $sqlite_files[realpath($df)] = basename($df) . ' (LSP)';
        }
        foreach ([$base_dir, __DIR__] as $scan_d) {
            if (is_dir($scan_d)) {
                foreach (@scandir($scan_d) ?: [] as $f) {
                    if (preg_match('/\.(db|sqlite|sqlite3)$/i', $f)) {
                        $rp = realpath($scan_d . '/' . $f);
                        if ($rp && !isset($sqlite_files[$rp])) {
                            $sqlite_files[$rp] = $f;
                        }
                    }
                }
            }
        }
        $available['sqlite'] = [
            'label' => 'SQLite 3',
            'ready' => extension_loaded('pdo_sqlite'),
            'files' => $sqlite_files
        ];
    }
    // 2. MariaDB / MySQL
    if ($services['mariadb']['installed'] || $services['mariadb']['running'] || extension_loaded('mysqli') || extension_loaded('pdo_mysql')) {
        $available['mysql'] = [
            'label' => 'MariaDB / MySQL',
            'ready' => $services['mariadb']['running'] && (extension_loaded('mysqli') || extension_loaded('pdo_mysql'))
        ];
    }
    // 3. PostgreSQL
    if ($services['postgresql']['installed'] || $services['postgresql']['running'] || extension_loaded('pdo_pgsql') || extension_loaded('pgsql')) {
        $available['pgsql'] = [
            'label' => 'PostgreSQL',
            'ready' => $services['postgresql']['running'] && (extension_loaded('pdo_pgsql') || cmd_exists('psql'))
        ];
    }
    // 4. MongoDB
    if ($services['mongodb']['installed'] || $services['mongodb']['running'] || extension_loaded('mongodb')) {
        $available['mongodb'] = [
            'label' => 'MongoDB',
            'ready' => $services['mongodb']['running'] && (class_exists('MongoDB\Driver\Manager') || cmd_exists('mongosh') || cmd_exists('mongo'))
        ];
    }
    // 5. Redis
    if ($services['redis']['installed'] || $services['redis']['running'] || extension_loaded('redis')) {
        $available['redis'] = [
            'label' => 'Redis',
            'ready' => $services['redis']['running'] && (class_exists('Redis') || cmd_exists('redis-cli'))
        ];
    }
    return $available;
}

$services = get_services_list($prefix);
$available_dbs = detect_available_dbs($services, $default_sqlite_db, $base_dir);

$current_dir = isset($_GET['dir']) ? safe_path($_GET['dir'], $current_doc_root, $allowed_root) : $current_doc_root;
$split_mode = isset($_GET['split']) && $_GET['split'] == '1';
$current_dir_2 = isset($_GET['dir2']) ? safe_path($_GET['dir2'], $current_doc_root, $allowed_root) : $current_doc_root;

$status_msg = "";
$sql_result = null;
$sql_msg = "";

// Просмотр изображений и скачивание файлов
if (isset($_GET['view_img'])) {
    $img_path = safe_path($_GET['view_img'], '', $allowed_root);
    if ($img_path && is_file($img_path)) {
        $mime = mime_content_type($img_path) ?: 'image/png';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($img_path));
        readfile($img_path);
        exit;
    }
}

if (isset($_GET['download'])) {
    $dl_path = safe_path($_GET['download'], '', $allowed_root);
    if ($dl_path && is_file($dl_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($dl_path) . '"');
        header('Content-Length: ' . filesize($dl_path));
        readfile($dl_path);
        exit;
    }
}

// Экспорт БД (SQLite или MySQL)
if (isset($_GET['db_export'])) {
    $etype = $_GET['db_type'] ?? 'sqlite';
    if ($etype === 'sqlite') {
        $sfile = safe_path($_GET['sqlite_file'] ?? $default_sqlite_db, $default_sqlite_db, $allowed_root);
        if (file_exists($sfile)) {
            header('Content-Type: application/x-sqlite3');
            header('Content-Disposition: attachment; filename="' . basename($sfile) . '"');
            header('Content-Length: ' . filesize($sfile));
            readfile($sfile);
            exit;
        }
    } elseif ($etype === 'mysql' && class_exists('mysqli')) {
        $exp_db = $_GET['db_export'];
        $exp_tbl = $_GET['tbl_export'] ?? '';
        $conn_exp = @new mysqli($db_host, $db_user, $db_pass, $exp_db);
        if (!$conn_exp->connect_error) {
            $conn_exp->set_charset("utf8mb4");
            $tables = [];
            if ($exp_tbl !== '') {
                $tables[] = $exp_tbl;
            } else {
                $res = $conn_exp->query("SHOW TABLES");
                while ($row = $res->fetch_row()) $tables[] = $row[0];
            }
            $dump = "-- LocalServerPanel SQL Dump\n-- Database: `$exp_db`\n-- Date: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
            foreach ($tables as $t) {
                $t_esc = $conn_exp->real_escape_string($t);
                $c_res = $conn_exp->query("SHOW CREATE TABLE `$t_esc`")->fetch_row();
                $dump .= "DROP TABLE IF EXISTS `$t_esc`;\n" . $c_res[1] . ";\n\n";
                $rows = $conn_exp->query("SELECT * FROM `$t_esc`");
                while ($r = $rows->fetch_assoc()) {
                    $vals = array_map(function($v) use ($conn_exp) {
                        return $v === null ? "NULL" : "'" . $conn_exp->real_escape_string($v) . "'";
                    }, array_values($r));
                    $cols = array_map(function($c) { return "`$c`"; }, array_keys($r));
                    $dump .= "INSERT INTO `$t_esc` (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ");\n";
                }
                $dump .= "\n";
            }
            $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
            $fname = $exp_tbl ? "{$exp_db}_{$exp_tbl}.sql" : "{$exp_db}.sql";
            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $fname . '"');
            echo $dump;
            $conn_exp->close();
            exit;
        }
    }
}

// AJAX статистика ресурсов
if (isset($_GET['ajax_stats'])) {
    header('Content-Type: application/json');
    $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0,0,0];
    $cpu_usage = min(100, round($load[0] * 25, 1));
    
    $free_memory = 0;
    $total_memory = 512;
    if (is_readable('/proc/meminfo')) {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $m_total);
        preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $m_avail);
        if (isset($m_total[1])) $total_memory = round($m_total[1] / 1024);
        if (isset($m_avail[1])) $free_memory = round($m_avail[1] / 1024);
    }
    $used_memory = $total_memory - $free_memory;
    $ram_usage_pct = $total_memory > 0 ? round(($used_memory / $total_memory) * 100, 1) : 0;

    echo json_encode([
        'cpu' => $cpu_usage,
        'ram_pct' => $ram_usage_pct,
        'ram_used' => $used_memory,
        'ram_total' => $total_memory
    ]);
    exit;
}

// Функция выполнения команды в веб-терминале
function execute_terminal_command($cmd, $current_doc_root) {
    $cmd = trim($cmd);
    if ($cmd === 'clear') {
        $_SESSION['term_history'] = [];
        return;
    }
    if (empty($cmd)) return;

    $cwd = $_SESSION['term_cwd'];
    if (preg_match('/^cd\s*(.*)$/', $cmd, $matches)) {
        $target = trim($matches[1]);
        if ($target === '' || $target === '~') {
            $new_dir = $current_doc_root;
        } elseif ($target[0] === '/') {
            $new_dir = realpath($target);
        } else {
            $new_dir = realpath($cwd . '/' . $target);
        }
        if ($new_dir && is_dir($new_dir)) {
            $_SESSION['term_cwd'] = $new_dir;
            $out = "";
        } else {
            $out = "bash: cd: $target: Нет такого файла или каталога\n";
        }
    } else {
        $full_cmd = "cd " . escapeshellarg($cwd) . " && " . $cmd . " 2>&1";
        $out = (string)@shell_exec($full_cmd);
    }
    $_SESSION['term_history'][] = [
        'cwd' => $cwd,
        'cmd' => $cmd,
        'out' => $out
    ];
    if (count($_SESSION['term_history']) > 40) {
        array_shift($_SESSION['term_history']);
    }
}

// Обработка POST-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Управление службами (вкл / выкл / установка через терминал)
    if (isset($_POST['service_action']) && isset($_POST['service_key'])) {
        $skey = $_POST['service_key'];
        $sact = $_POST['service_action'];
        if (isset($services[$skey])) {
            $srv = $services[$skey];
            if ($sact === 'start' && !empty($srv['start_cmd'])) {
                @shell_exec($srv['start_cmd']);
                usleep(400000);
                $status_msg = $srv['name'] . ": команда запуска отправлена.";
            } elseif ($sact === 'stop' && !empty($srv['stop_cmd'])) {
                @shell_exec($srv['stop_cmd']);
                usleep(400000);
                $status_msg = $srv['name'] . ": служба остановлена.";
            } elseif ($sact === 'install' && !empty($srv['install_cmd'])) {
                execute_terminal_command($srv['install_cmd'], $current_doc_root);
                header("Location: ?tab=terminal");
                exit;
            }
            $services = get_services_list($prefix);
            $available_dbs = detect_available_dbs($services, $default_sqlite_db, $base_dir);
        }
    }

    if (isset($_POST['term_cmd'])) {
        execute_terminal_command($_POST['term_cmd'], $current_doc_root);
    }

    if (isset($_POST['update_docroot'])) {
        $new_root = trim($_POST['new_docroot']);
        if (is_dir($new_root)) {
            $_SESSION['doc_root'] = realpath($new_root);
            $current_doc_root = $_SESSION['doc_root'];
            $current_dir = $current_doc_root;
            if (file_exists($apache_conf)) {
                $conf_content = file_get_contents($apache_conf);
                $conf_content = preg_replace('/DocumentRoot\s+"[^"]+?"/', 'DocumentRoot "' . $new_root . '"', $conf_content);
                $conf_content = preg_replace('/<Directory\s+"[^"]+?">/', '<Directory "' . $new_root . '">', $conf_content, 1);
                @file_put_contents($apache_conf, $conf_content);
            }
            $status_msg = "Корневая директория изменена.";
        } else {
            $status_msg = "Ошибка: папка не существует!";
        }
    }

    if (isset($_POST['php_version_update'])) {
        $php_ver = $_POST['php_version'];
        if (file_exists($apache_conf)) {
            $conf_content = file_get_contents($apache_conf);
            $conf_content = preg_replace('/LoadModule php_module\s+.*/', 'LoadModule php_module libexec/apache2/libphp_' . $php_ver . '.so', $conf_content);
            @file_put_contents($apache_conf, $conf_content);
        }
        $status_msg = "Версия PHP переключена на $php_ver.";
    }
    if (isset($_POST['toggle_module'])) {
        $module = $_POST['toggle_module'];
        $state = $_POST['module_state'];
        if (file_exists($php_ini)) {
            $ini_content = file_get_contents($php_ini);
            if ($state == '1') {
                $ini_content = str_replace(";extension=$module", "extension=$module", $ini_content);
            } else {
                $ini_content = str_replace("extension=$module", ";extension=$module", $ini_content);
            }
            @file_put_contents($php_ini, $ini_content);
            $status_msg = "Состояние модуля $module изменено.";
        }
    }

    if (isset($_POST['project_action'])) {
        $p_act = $_POST['project_action'];
        $projects = json_decode(file_get_contents($projects_file), true) ?: [];

        if ($p_act === 'create') {
            $p_name = trim($_POST['p_name']);
            $p_root = trim($_POST['p_root']);
            if (empty($p_root)) {
                $p_root = $base_dir . '/' . $p_name;
            }
            $p_php = $_POST['p_php'] ?? '8.2';
            if (!empty($p_name)) {
                if (!is_dir($p_root)) @mkdir($p_root, 0755, true);
                $projects[] = [
                    'id' => uniqid('proj_'),
                    'name' => $p_name,
                    'root' => realpath($p_root) ?: $p_root,
                    'php_version' => $p_php,
                    'github' => '',
                    'vhost_port' => '8080',
                    'tasks' => []
                ];
                file_put_contents($projects_file, json_encode($projects, JSON_UNESCAPED_UNICODE));
                $status_msg = "Проект '$p_name' создан!";
            }
        } elseif ($p_act === 'update_settings') {
            $pid = $_POST['project_id'];
            foreach ($projects as &$pr) {
                if ($pr['id'] === $pid) {
                    $pr['php_version'] = $_POST['p_php'];
                    $pr['github'] = trim($_POST['p_github']);
                    $pr['vhost_port'] = trim($_POST['p_vhost_port'] ?? '8080');
                    $custom_root = trim($_POST['p_root_edit'] ?? '');
                    if (!empty($custom_root)) {
                        if (!is_dir($custom_root)) @mkdir($custom_root, 0755, true);
                        $pr['root'] = realpath($custom_root) ?: $custom_root;
                    }
                    
                    if (isset($_POST['gen_ht']) && !empty($pr['root']) && is_dir($pr['root'])) {
                        $ht_rules = "RewriteEngine On\n\n";
                        if (!empty($_POST['ht_https'])) {
                            $ht_rules .= "RewriteCond %{HTTPS} off\nRewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n\n";
                        }
                        if (!empty($_POST['ht_seo'])) {
                            $ht_rules .= "RewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^(.*)$ index.php [QSA,L]\n\n";
                        }
                        if (!empty($_POST['ht_index'])) {
                            $ht_rules .= "Options -Indexes\n\n";
                        }
                        if (!empty($_POST['ht_cache'])) {
                            $ht_rules .= "<IfModule mod_expires.c>\nExpiresActive On\nExpiresDefault \"access plus 1 month\"\n</IfModule>\n";
                        }
                        file_put_contents($pr['root'] . '/.htaccess', $ht_rules);
                        $status_msg = ".htaccess успешно сгенерирован в корне проекта!";
                    } else {
                        $status_msg = "Настройки проекта сохранены.";
                    }

                    if (isset($_FILES['p_favicon']) && $_FILES['p_favicon']['error'] === UPLOAD_ERR_OK) {
                        @move_uploaded_file($_FILES['p_favicon']['tmp_name'], $pr['root'] . '/favicon.ico');
                        $status_msg = "Фавикон загружен!";
                    }
                }
            }
            unset($pr);
            file_put_contents($projects_file, json_encode($projects, JSON_UNESCAPED_UNICODE));
        } elseif ($p_act === 'add_project_task') {
            $pid = $_POST['project_id'];
            $t_name = trim($_POST['t_name']);
            $t_script = trim($_POST['t_script']);
            if (!empty($t_name) && !empty($t_script)) {
                foreach ($projects as &$pr) {
                    if ($pr['id'] === $pid) {
                        if (!isset($pr['tasks'])) $pr['tasks'] = [];
                        $pr['tasks'][] = ['id' => uniqid(), 'name' => $t_name, 'script' => $t_script];
                    }
                }
                unset($pr);
                file_put_contents($projects_file, json_encode($projects, JSON_UNESCAPED_UNICODE));
                $status_msg = "Задача добавлена в проект.";
            }
        } elseif ($p_act === 'git_action') {
            $pid = $_POST['project_id'];
            $g_cmd = $_POST['g_cmd'];
            foreach ($projects as $pr) {
                $r = $pr['root'] ?? ($base_dir . '/' . $pr['name']);
                if ($pr['id'] === $pid && is_dir($r)) {
                    if ($g_cmd === 'pull') {
                        $out = shell_exec("cd " . escapeshellarg($r) . " && git pull 2>&1");
                        $status_msg = "Git Pull: " . trim((string)$out);
                    } elseif ($g_cmd === 'status') {
                        $out = shell_exec("cd " . escapeshellarg($r) . " && git status -s 2>&1");
                        $status_msg = "Git Status: " . (trim((string)$out) ?: "Чисто (нет изменений)");
                    }
                }
            }
        } elseif ($p_act === 'composer_action') {
            $pid = $_POST['project_id'];
            $c_cmd = $_POST['c_cmd'];
            foreach ($projects as $pr) {
                $r = $pr['root'] ?? ($base_dir . '/' . $pr['name']);
                if ($pr['id'] === $pid && is_dir($r)) {
                    $out = shell_exec("cd " . escapeshellarg($r) . " && composer " . escapeshellarg($c_cmd) . " 2>&1");
                    $status_msg = "Composer $c_cmd: " . mb_strimwidth(trim((string)$out), 0, 150, '...');
                }
            }
        } elseif ($p_act === 'cms_fix_perms') {
            $pid = $_POST['project_id'];
            foreach ($projects as $pr) {
                $r = $pr['root'] ?? ($base_dir . '/' . $pr['name']);
                if ($pr['id'] === $pid && is_dir($r)) {
                    foreach (['storage', 'uploads', 'wp-content', 'cache', 'tmp'] as $sub) {
                        if (is_dir("$r/$sub")) {
                            shell_exec("chmod -R 777 " . escapeshellarg("$r/$sub") . " > /dev/null 2>&1");
                        }
                    }
                    $status_msg = "Права на запись (777) установлены для рабочих папок CMS.";
                }
            }
        }
    }

    if (isset($_POST['fm_action'])) {
        $fm_act = $_POST['fm_action'];
        $target_dir = isset($_POST['target_dir']) ? safe_path($_POST['target_dir'], $current_dir, $allowed_root) : $current_dir;

        if ($fm_act === 'create_folder' && !empty($_POST['new_name'])) {
            if (@mkdir($target_dir . '/' . basename(trim($_POST['new_name'])), 0755)) $status_msg = "Папка создана.";
        } elseif ($fm_act === 'create_file' && !empty($_POST['new_name'])) {
            if (@file_put_contents($target_dir . '/' . basename(trim($_POST['new_name'])), '') !== false) $status_msg = "Файл создан.";
        } elseif ($fm_act === 'chmod' && !empty($_POST['target_path']) && !empty($_POST['chmod_val'])) {
            $tpath = safe_path($_POST['target_path'], '', $allowed_root);
            $val = preg_replace('/[^0-7]/', '', trim($_POST['chmod_val']));
            if ($tpath && strlen($val) >= 3 && @chmod($tpath, octdec($val))) {
                $status_msg = "Права изменены на $val.";
            } else {
                $status_msg = "Не удалось изменить права (ограничение файловой системы Android/FAT32).";
            }
        } elseif ($fm_act === 'rename' && !empty($_POST['old_path']) && !empty($_POST['new_name'])) {
            $old = safe_path($_POST['old_path'], '', $allowed_root);
            if ($old) {
                $new = dirname($old) . '/' . basename(trim($_POST['new_name']));
                if (@rename($old, $new)) $status_msg = "Переименовано.";
            }
        } elseif ($fm_act === 'copy_move' && !empty($_POST['src_path']) && !empty($_POST['dst_dir'])) {
            $src = safe_path($_POST['src_path'], '', $allowed_root);
            $dst = safe_path($_POST['dst_dir'], '', $allowed_root);
            if ($src && $dst && is_dir($dst)) {
                $dest = $dst . '/' . basename($src);
                if (($_POST['op_mode'] ?? 'copy') === 'move') {
                    if (@rename($src, $dest)) $status_msg = "Перемещено.";
                } else {
                    copy_recursive($src, $dest);
                    $status_msg = "Скопировано.";
                }
            }
        } elseif ($fm_act === 'zip' && !empty($_POST['target_path'])) {
            $tpath = safe_path($_POST['target_path'], '', $allowed_root);
            if ($tpath && class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($tpath . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                    zip_add_recursive($zip, $tpath, strlen(dirname($tpath)) + 1);
                    $zip->close();
                    $status_msg = "Архив создан.";
                }
            }
        } elseif ($fm_act === 'unzip' && !empty($_POST['target_path'])) {
            $tpath = safe_path($_POST['target_path'], '', $allowed_root);
            if ($tpath && class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($tpath) === TRUE) {
                    $zip->extractTo(dirname($tpath));
                    $zip->close();
                    $status_msg = "Архив распакован.";
                }
            }
        }
    }

    if (isset($_POST['save_file']) && !empty($_POST['file_path'])) {
        $fpath = safe_path($_POST['file_path'], '', $allowed_root);
        if ($fpath && is_file($fpath)) {
            file_put_contents($fpath, $_POST['file_content']);
            $status_msg = "Файл сохранен.";
        }
    }

    if (isset($_FILES['uploaded_files'])) {
        $target_dir = isset($_POST['target_dir']) ? safe_path($_POST['target_dir'], $current_dir, $allowed_root) : $current_dir;
        $c = 0;
        foreach ($_FILES['uploaded_files']['name'] as $idx => $name) {
            if ($_FILES['uploaded_files']['error'][$idx] === UPLOAD_ERR_OK) {
                if (move_uploaded_file($_FILES['uploaded_files']['tmp_name'][$idx], $target_dir . '/' . basename($name))) $c++;
            }
        }
        if ($c > 0) $status_msg = "Загружено файлов: $c.";
    }

    if (isset($_POST['clear_logs'])) {
        if (file_exists($error_log_path)) @file_put_contents($error_log_path, '');
        if (file_exists($access_log_path)) @file_put_contents($access_log_path, '');
        $status_msg = "Логи очищены.";
    }
}

if (isset($_GET['del_project'])) {
    $pid = $_GET['del_project'];
    $projects = json_decode(file_get_contents($projects_file), true) ?: [];
    $projects = array_filter($projects, function($p) use ($pid) { return $p['id'] !== $pid; });
    file_put_contents($projects_file, json_encode(array_values($projects), JSON_UNESCAPED_UNICODE));
    header("Location: ?tab=projects");
    exit;
}

if (isset($_GET['del_project_task']) && isset($_GET['pid'])) {
    $pid = $_GET['pid'];
    $tid = $_GET['del_project_task'];
    $projects = json_decode(file_get_contents($projects_file), true) ?: [];
    foreach ($projects as &$pr) {
        if ($pr['id'] === $pid && isset($pr['tasks'])) {
            $pr['tasks'] = array_values(array_filter($pr['tasks'], function($t) use ($tid) { return $t['id'] !== $tid; }));
        }
    }
    unset($pr);
    file_put_contents($projects_file, json_encode($projects, JSON_UNESCAPED_UNICODE));
    header("Location: ?tab=projects");
    exit;
}

if (isset($_GET['backup_project'])) {
    $pid = $_GET['backup_project'];
    $projects = json_decode(file_get_contents($projects_file), true) ?: [];
    foreach ($projects as $pr) {
        $p_root = !empty($pr['root']) ? $pr['root'] : ($base_dir . '/' . $pr['name']);
        if ($pr['id'] === $pid && is_dir($p_root) && class_exists('ZipArchive')) {
            $zip_name = sys_get_temp_dir() . '/' . $pr['name'] . '_backup.zip';
            $zip = new ZipArchive();
            if ($zip->open($zip_name, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                zip_add_recursive($zip, $p_root, strlen(dirname($p_root)) + 1);
                $zip->close();
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($zip_name) . '"');
                readfile($zip_name);
                @unlink($zip_name);
                exit;
            }
        }
    }
}

if (isset($_GET['make_apk'])) {
    $pid = $_GET['make_apk'];
    $projects = json_decode(file_get_contents($projects_file), true) ?: [];
    foreach ($projects as $pr) {
        if ($pr['id'] === $pid) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="build_' . $pr['name'] . '.sh"');
            echo "#!/bin/bash\n# LocalServerPanel APK Wrapper Builder for: " . $pr['name'] . "\necho 'Building WebView APK...'\n";
            exit;
        }
    }
}

if (isset($_GET['delete'])) {
    $dpath = safe_path($_GET['delete'], '', $allowed_root);
    if ($dpath && $dpath !== $current_doc_root && $dpath !== $allowed_root) {
        if (is_dir($dpath)) rrmdir($dpath); else @unlink($dpath);
        $status_msg = "Объект удален.";
    }
}

$tab = $_GET['tab'] ?? 'dashboard';
$edit_file = isset($_GET['edit']) ? safe_path($_GET['edit'], '', $allowed_root) : null;
$img_preview = isset($_GET['preview_img']) ? safe_path($_GET['preview_img'], '', $allowed_root) : null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LocalServerPanel (LSP Universal)</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #121212; color: #e0e0e0; margin: 0; font-size: 14px; display: flex; flex-direction: column; min-height: 100vh; }
        
        .topbar { display: flex; justify-content: space-between; align-items: center; background: #1a1a24; border-bottom: 1px solid #333; padding: 10px 16px; width: 100%; position: sticky; top: 0; z-index: 50; box-shadow: 0 2px 8px rgba(0,0,0,0.4); }
        .brand-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; font-weight: 800; font-size: 1.25em; letter-spacing: 0.5px; }
        .brand-logo svg { width: 28px; height: 28px; fill: #03dac6; }
        
        .nav-icons { display: flex; gap: 8px; align-items: center; }
        .nav-icons a { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; background: #242432; color: #bb86fc; border-radius: 8px; text-decoration: none; font-size: 1.35em; transition: all 0.2s; border: 1px solid #333; }
        .nav-icons a:hover { background: #2f2f42; transform: translateY(-1px); }
        .nav-icons a.active { background: #bb86fc; color: #121212; border-color: #bb86fc; box-shadow: 0 0 10px rgba(187,134,252,0.4); }

        .main-container { padding: 12px; flex: 1; max-width: 1400px; width: 100%; margin: 0 auto; }
        
        .subnav { display: flex; gap: 4px; margin: 8px 0 12px 0; flex-wrap: wrap; background: #181818; padding: 6px; border-radius: 4px; border: 1px solid #2c2c2c; }
        .subnav a { color: #03dac6; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.85em; font-weight: 600; }
        .subnav a.active { background: #03dac6; color: #000; }
        
        .card { background: #1e1e1e; padding: 14px; border-radius: 8px; margin-bottom: 14px; box-shadow: 0 2px 6px rgba(0,0,0,0.35); border: 1px solid #2a2a2a; }
        h2 { color: #bb86fc; margin-top: 0; font-size: 1.1em; border-bottom: 1px solid #333; padding-bottom: 6px; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        h3 { color: #03dac6; font-size: 0.95em; margin: 12px 0 6px 0; }
        
        .btn { background: #6200ee; color: white; border: none; padding: 6px 11px; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 4px; margin: 2px; font-size: 0.83em; font-weight: 500; }
        .btn:hover { background: #3700b3; }
        .btn-sm { padding: 4px 8px; font-size: 0.78em; }
        .btn-icon { width: 32px; height: 30px; padding: 0; font-size: 1.05em; }
        .btn-teal { background: #03dac6; color: #000; font-weight: bold; }
        .btn-teal:hover { background: #01b8a6; }
        .btn-red { background: #b00020; color: #fff; }
        .btn-red:hover { background: #cf6679; }
        .btn-gray { background: #333; color: #e0e0e0; }
        .btn-gray:hover { background: #444; }
        .btn-install { background: #ff9800; color: #000; font-weight: bold; }
        .btn-install:hover { background: #fb8c00; }
        
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; display: inline-block; }
        .badge-green { background: #03dac6; color: #000; }
        .badge-red { background: #cf6679; color: #000; }
        .badge-gray { background: #3a3a45; color: #aaa; }
        
        .alert { background: #2c2400; border-left: 4px solid #ffb300; padding: 10px; margin-bottom: 12px; border-radius: 4px; font-size: 0.9em; }
        .service-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 8px; }
        .service-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; background: #181820; border: 1px solid #2c2c38; border-radius: 6px; }
        .service-info { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .service-name { font-weight: bold; color: #fff; }
        
        .table-wrap { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { text-align: left; padding: 7px 8px; border-bottom: 1px solid #333; font-size: 0.84em; white-space: nowrap; }
        th { color: #bb86fc; background: #252525; }
        tr:hover td { background: #242424; }
        
        textarea, pre.logbox { width: 100%; background: #161616; color: #f8f8f2; border: 1px solid #444; padding: 10px; font-family: 'Courier New', monospace; border-radius: 4px; }
        textarea { height: 320px; font-size: 13px; line-height: 1.4; }
        pre.logbox { height: 170px; overflow-y: auto; font-size: 0.8em; margin-top: 4px; }
        
        input[type="text"], input[type="number"], select { background: #2b2b2b; color: #fff; border: 1px solid #444; padding: 6px 8px; border-radius: 4px; font-size: 0.85em; }
        .input-full { width: 100%; }
        .flex-bar { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin-bottom: 8px; }
        .icon-toolbar { display: inline-flex; gap: 4px; align-items: center; flex-wrap: nowrap; }
        .breadcrumbs { background: #252525; padding: 6px 10px; border-radius: 4px; font-size: 0.82em; margin-bottom: 8px; word-break: break-all; }

        .ecg-grid { display: flex; gap: 12px; flex-wrap: wrap; }
        .ecg-box { background: #16161d; flex: 1; min-width: 160px; padding: 10px; border-radius: 6px; border: 1px solid #333; }
        .ecg-header { display: flex; justify-content: space-between; font-size: 0.85em; margin-bottom: 6px; color: #bbb; }
        canvas.ecg-canvas { width: 100%; height: 48px; background: #0d0d12; border-radius: 4px; display: block; }

        .project-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(325px, 1fr)); gap: 12px; margin-top: 10px; }
        .project-card { background: #23232e; border: 1px solid #38384a; border-radius: 8px; padding: 12px; display: flex; flex-direction: column; justify-content: space-between; }
        .project-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 8px; margin-bottom: 8px; }
        .project-title { font-size: 1.05em; font-weight: bold; color: #03dac6; }
        .project-section { background: #1a1a22; padding: 8px; border-radius: 6px; margin-top: 8px; border: 1px solid #2d2d3a; }

        .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); align-items: center; justify-content: center; }
        .modal-content { background: #1e1e26; border: 1px solid #bb86fc; padding: 18px; border-radius: 8px; width: 92%; max-width: 480px; box-shadow: 0 6px 20px rgba(0,0,0,0.6); }

        .fm-split { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        @media (max-width: 768px) { .fm-split { grid-template-columns: 1fr; } }
        .fm-pane { background: #181818; padding: 8px; border-radius: 6px; border: 1px solid #333; overflow-x: auto; }

        .classic-terminal { background: #0c0c0c; color: #00ff66; font-family: 'Courier New', Courier, monospace; padding: 12px; border-radius: 6px; border: 1px solid #333; height: 350px; overflow-y: auto; display: flex; flex-direction: column; justify-content: space-between; }
        .term-history { flex: 1; overflow-y: auto; margin-bottom: 8px; font-size: 13px; line-height: 1.4; }
        .term-entry { margin-bottom: 8px; }
        .term-prompt { color: #03dac6; font-weight: bold; }
        .term-out { color: #e0e0e0; white-space: pre-wrap; word-break: break-all; margin: 2px 0 0 0; }
        .term-input-line { display: flex; align-items: center; gap: 6px; border-top: 1px solid #222; padding-top: 8px; }
        .term-input-line input { background: transparent; border: none; color: #00ff66; font-family: inherit; font-size: 13px; flex: 1; outline: none; }

        footer { background: #1a1a24; border-top: 1px solid #333; text-align: center; padding: 12px; font-size: 0.85em; color: #888; margin-top: auto; }
    </style>
</head>
<body>

    <header class="topbar">
        <a href="?tab=dashboard" class="brand-logo" title="Главная панель LSP">
            <svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm0 2v4h16V6H4zm2 1h2v2H6V7zm4 0h2v2h-2V7zM4 14h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2zm0 2v4h16v-4H4zm2 1h2v2H6v-2zm4 0h2v2h-2v-2z"/></svg>
            <span>LSP</span>
        </a>
        <nav class="nav-icons">
            <a href="?tab=projects" class="<?php echo $tab === 'projects' ? 'active' : ''; ?>" title="Менеджер проектов">🚀</a>
            <a href="?tab=files" class="<?php echo $tab === 'files' ? 'active' : ''; ?>" title="Файловый менеджер">📂</a>
            <a href="?tab=database" class="<?php echo $tab === 'database' ? 'active' : ''; ?>" title="Универсальный менеджер БД">🗄️</a>
            <a href="?tab=terminal" class="<?php echo $tab === 'terminal' ? 'active' : ''; ?>" title="Терминал и логи">💻</a>
        </nav>
    </header>

    <div class="main-container">

    <?php if (!empty($status_msg)): ?>
        <div class="alert"><?php echo htmlspecialchars($status_msg); ?></div>
    <?php endif; ?>

    <?php if ($tab === 'dashboard'): ?>
        <div class="card">
            <h2>📊 Мониторинг ресурсов</h2>
            <div class="ecg-grid">
                <div class="ecg-box">
                    <div class="ecg-header"><span>CPU Load</span><strong id="cpu-txt">0%</strong></div>
                    <canvas id="cpu-canvas" class="ecg-canvas" width="260" height="48"></canvas>
                </div>
                <div class="ecg-box">
                    <div class="ecg-header"><span>RAM Usage</span><strong id="ram-txt">0%</strong></div>
                    <canvas id="ram-canvas" class="ecg-canvas" width="260" height="48"></canvas>
                </div>
            </div>
        </div>

        <script>
        const cpuData = new Array(35).fill(0);
        const ramData = new Array(35).fill(0);

        function drawEcg(canvasId, data, color) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.strokeStyle = color;
            ctx.lineWidth = 2;
            ctx.beginPath();
            const step = canvas.width / (data.length - 1);
            for (let i = 0; i < data.length; i++) {
                const x = i * step;
                const y = canvas.height - (data[i] / 100) * (canvas.height - 8) - 4;
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            }
            ctx.stroke();
        }

        function updateStats() {
            fetch('?ajax_stats=1')
                .then(res => res.json())
                .then(data => {
                    document.getElementById('cpu-txt').innerText = data.cpu + '%';
                    document.getElementById('ram-txt').innerText = data.ram_pct + '% (' + data.ram_used + 'MB)';
                    cpuData.shift(); cpuData.push(data.cpu);
                    ramData.shift(); ramData.push(data.ram_pct);
                    drawEcg('cpu-canvas', cpuData, '#bb86fc');
                    drawEcg('ram-canvas', ramData, '#03dac6');
                }).catch(e => {});
        }
        setInterval(updateStats, 1000);
        window.onload = updateStats;
        </script>

        <div class="card">
            <h2>⚙️ Службы, серверы и окружение (Автоопределение)</h2>
            <div class="service-grid">
                <?php foreach ($services as $skey => $srv): ?>
                    <div class="service-row">
                        <div class="service-info">
                            <span class="service-name"><?php echo htmlspecialchars($srv['name']); ?></span>
                            <?php if (!$srv['installed']): ?>
                                <span class="badge badge-gray">Не установлен</span>
                            <?php elseif (!empty($srv['is_embedded'])): ?>
                                <span class="badge badge-green">Готов (Встроен)</span>
                            <?php elseif ($srv['running']): ?>
                                <span class="badge badge-green">Запущен</span>
                            <?php else: ?>
                                <span class="badge badge-red">Выключен</span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" style="margin:0; display:flex; align-items:center; gap:4px;">
                            <input type="hidden" name="service_key" value="<?php echo htmlspecialchars($skey); ?>">
                            <?php if (!$srv['installed']): ?>
                                <button type="submit" name="service_action" value="install" class="btn btn-sm btn-install" title="Установить через терминал">⬇ Установить</button>
                            <?php elseif (empty($srv['is_embedded'])): ?>
                                <?php if ($srv['running']): ?>
                                    <button type="submit" name="service_action" value="stop" class="btn btn-sm btn-red">Выкл</button>
                                    <?php if ($skey === 'vnc'): ?>
                                        <a href="vnc://127.0.0.1:5901" class="btn btn-teal btn-sm" title="Открыть VNC">🖥</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button type="submit" name="service_action" value="start" class="btn btn-sm btn-teal">Вкл</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h2>📂 Корневая директория сервера (DocumentRoot)</h2>
            <form method="POST" class="flex-bar" style="margin:0;">
                <input type="text" name="new_docroot" style="flex:1; min-width:200px;" value="<?php echo htmlspecialchars($_SESSION['doc_root']); ?>">
                <button type="submit" name="update_docroot" class="btn">Сохранить</button>
                <a href="?tab=files&dir=<?php echo urlencode($_SESSION['doc_root']); ?>" class="btn btn-teal">📂 Перейти</a>
            </form>
        </div>

        <div class="card">
            <h2>🐘 Управление PHP и модулями (v<?php echo PHP_VERSION; ?>)</h2>
            <form method="POST" class="flex-bar" style="margin-bottom: 10px;">
                <label>Модуль Apache PHP:</label>
                <select name="php_version">
                    <option value="8.4">PHP 8.4</option>
                    <option value="8.3">PHP 8.3</option>
                    <option value="8.2" selected>PHP 8.2</option>
                    <option value="8.1">PHP 8.1</option>
                </select>
                <button type="submit" name="php_version_update" class="btn btn-sm btn-teal">Применить</button>
            </form>

            <div class="table-wrap">
                <table>
                    <?php
                    $modules = ['sqlite3', 'pdo_sqlite', 'mysqli', 'pdo_mysql', 'pdo_pgsql', 'mongodb', 'redis', 'curl', 'gd', 'mbstring', 'openssl', 'zip'];
                    foreach ($modules as $mod) {
                        $loaded = extension_loaded($mod);
                        echo "<tr><td><strong>$mod</strong></td><td>" . ($loaded ? '<span class="badge badge-green">Вкл</span>' : '<span class="badge badge-red">Выкл</span>') . "</td><td>";
                        echo "<form method='POST' style='margin:0;'><input type='hidden' name='toggle_module' value='$mod'>";
                        if ($loaded) {
                            echo "<input type='hidden' name='module_state' value='0'><button type='submit' class='btn btn-sm btn-red'>Откл</button>";
                        } else {
                            echo "<input type='hidden' name='module_state' value='1'><button type='submit' class='btn btn-sm'>Вкл</button>";
                        }
                        echo "</form></td></tr>";
                    }
                    ?>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'projects'): ?>
        <div class="card">
            <h2>
                <span>🚀 Менеджер проектов</span>
                <button type="button" class="btn btn-teal" onclick="document.getElementById('modal_new_proj').style.display='flex'">+ Новый проект</button>
            </h2>

            <div id="modal_new_proj" class="modal">
                <div class="modal-content">
                    <h3>Создать новый проект</h3>
                    <form method="POST">
                        <input type="hidden" name="project_action" value="create">
                        <div style="margin-bottom:8px;">
                            <label style="display:block; margin-bottom:4px;">Название проекта:</label>
                            <input type="text" id="new_p_name" name="p_name" class="input-full" placeholder="например my_site" required oninput="updateAutoPath()">
                        </div>
                        <div style="margin-bottom:8px;">
                            <label style="display:block; margin-bottom:4px;">Корневая директория:</label>
                            <input type="text" id="new_p_root" name="p_root" class="input-full" value="/storage/emulated/0/Server/public/my_site" required>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label style="display:block; margin-bottom:4px;">Версия PHP:</label>
                            <select name="p_php" class="input-full">
                                <option value="8.4">PHP 8.4</option>
                                <option value="8.3">PHP 8.3</option>
                                <option value="8.2" selected>PHP 8.2</option>
                                <option value="8.1">PHP 8.1</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-teal">Создать проект</button>
                        <button type="button" class="btn btn-gray" onclick="document.getElementById('modal_new_proj').style.display='none'">Отмена</button>
                    </form>
                </div>
            </div>

            <script>
            function updateAutoPath() {
                let name = document.getElementById('new_p_name').value.trim().replace(/[^a-zA-Z0-9_-]/g, '_');
                document.getElementById('new_p_root').value = '/storage/emulated/0/Server/public/' + (name || 'my_site');
            }
            function pingProject(url, btnId) {
                let btn = document.getElementById(btnId);
                btn.innerText = "⏳";
                fetch(url, {mode: 'no-cors'}).then(() => {
                    btn.innerText = "🟢";
                    setTimeout(() => btn.innerText = "⚡", 3000);
                }).catch(() => {
                    btn.innerText = "🔴";
                    setTimeout(() => btn.innerText = "⚡", 3000);
                });
            }
            </script>

            <?php
            $projects = json_decode(file_get_contents($projects_file), true) ?: [];
            if (empty($projects)):
            ?>
                <p style="color:#888; text-align:center; padding: 20px 0;">Проектов пока нет. Нажмите «+ Новый проект» для создания.</p>
            <?php else: ?>
                <div class="project-grid">
                    <?php foreach ($projects as $pr): 
                        $p_name = $pr['name'] ?? 'Проект';
                        $p_root = !empty($pr['root']) ? $pr['root'] : ($base_dir . '/' . $p_name);
                        $vport = !empty($pr['vhost_port']) ? $pr['vhost_port'] : '8080';
                        $local_url = "http://127.0.0.1:" . $vport;
                    ?>
                        <div class="project-card">
                            <div>
                                <div class="project-header">
                                    <span class="project-title">🌐 <?php echo htmlspecialchars($p_name); ?></span>
                                    <div class="icon-toolbar">
                                        <button type="button" id="ping_<?php echo $pr['id']; ?>" class="btn btn-sm btn-gray" onclick="pingProject('<?php echo $local_url; ?>', 'ping_<?php echo $pr['id']; ?>')" title="Пинг">⚡</button>
                                        <a href="?tab=projects&backup_project=<?php echo $pr['id']; ?>" class="btn btn-sm btn-teal" title="ZIP бекап">📦</a>
                                        <a href="?tab=projects&make_apk=<?php echo $pr['id']; ?>" class="btn btn-sm btn-gray" title="APK скрипт">📱</a>
                                        <a href="?tab=projects&del_project=<?php echo $pr['id']; ?>" onclick="return confirm('Удалить проект?');" class="btn btn-sm btn-red" title="Удалить">✕</a>
                                    </div>
                                </div>

                                <div class="project-section">
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="project_action" value="update_settings">
                                        <input type="hidden" name="project_id" value="<?php echo $pr['id']; ?>">
                                        
                                        <label style="font-size:0.8em; color:#aaa;">Корневая директория:</label>
                                        <div class="flex-bar" style="margin-top:3px;">
                                            <input type="text" name="p_root_edit" value="<?php echo htmlspecialchars($p_root); ?>" style="flex:1; min-width:120px; font-size:0.8em;">
                                            <a href="?tab=files&dir=<?php echo urlencode($p_root); ?>" class="btn btn-sm btn-teal" title="Открыть папку">📂</a>
                                        </div>

                                        <div class="flex-bar" style="margin-top:6px;">
                                            <select name="p_php" style="width:82px;">
                                                <option value="8.4" <?php echo (($pr['php_version'] ?? '') === '8.4') ? 'selected' : ''; ?>>PHP 8.4</option>
                                                <option value="8.3" <?php echo (($pr['php_version'] ?? '') === '8.3') ? 'selected' : ''; ?>>PHP 8.3</option>
                                                <option value="8.2" <?php echo (($pr['php_version'] ?? '') === '8.2') ? 'selected' : ''; ?>>PHP 8.2</option>
                                                <option value="8.1" <?php echo (($pr['php_version'] ?? '') === '8.1') ? 'selected' : ''; ?>>PHP 8.1</option>
                                            </select>
                                            <input type="text" name="p_github" value="<?php echo htmlspecialchars($pr['github'] ?? ''); ?>" placeholder="GitHub URL..." style="flex:1; min-width:100px;">
                                            <input type="text" name="p_vhost_port" value="<?php echo htmlspecialchars($vport); ?>" placeholder="Порт" style="width:55px;" title="Порт">
                                        </div>

                                        <div class="flex-bar" style="margin-top:6px; justify-content:space-between;">
                                            <label style="font-size:0.8em; cursor:pointer;" class="btn btn-sm btn-gray">
                                                🖼 Фавикон <input type="file" name="p_favicon" accept=".ico,.png" style="display:none;">
                                            </label>
                                            <button type="button" class="btn btn-sm btn-gray" onclick="openHtModal('<?php echo $pr['id']; ?>')">⚙️ .htaccess</button>
                                            <button type="submit" class="btn btn-sm btn-teal">Сохранить</button>
                                        </div>

                                        <div id="modal_ht_<?php echo $pr['id']; ?>" class="modal">
                                            <div class="modal-content">
                                                <h3>Генератор .htaccess (<?php echo htmlspecialchars($p_name); ?>)</h3>
                                                <p style="font-size:0.85em; color:#ccc;">Отметьте нужные директивы:</p>
                                                <label style="display:block; margin-bottom:6px;"><input type="checkbox" name="ht_https" value="1"> Редирект с HTTP на HTTPS</label>
                                                <label style="display:block; margin-bottom:6px;"><input type="checkbox" name="ht_seo" value="1" checked> ЧПУ (перенаправление на index.php)</label>
                                                <label style="display:block; margin-bottom:6px;"><input type="checkbox" name="ht_index" value="1" checked> Запрет просмотра списка файлов (Options -Indexes)</label>
                                                <label style="display:block; margin-bottom:12px;"><input type="checkbox" name="ht_cache" value="1" checked> Кэширование статики в браузере</label>
                                                <input type="hidden" name="gen_ht" value="1">
                                                <button type="submit" class="btn btn-teal">Сгенерировать файл</button>
                                                <button type="button" class="btn btn-gray" onclick="closeHtModal('<?php echo $pr['id']; ?>')">Закрыть</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="project-section">
                                    <span style="font-size:0.8em; color:#bb86fc; font-weight:bold;">🛠 Инструменты (Git / Composer / CMS):</span>
                                    <div class="flex-bar" style="margin-top:4px; margin-bottom:0;">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="project_action" value="git_action">
                                            <input type="hidden" name="project_id" value="<?php echo $pr['id']; ?>">
                                            <input type="hidden" name="g_cmd" value="pull">
                                            <button type="submit" class="btn btn-sm btn-gray">Git Pull</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="project_action" value="git_action">
                                            <input type="hidden" name="project_id" value="<?php echo $pr['id']; ?>">
                                            <input type="hidden" name="g_cmd" value="status">
                                            <button type="submit" class="btn btn-sm btn-gray">Git Status</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="project_action" value="composer_action">
                                            <input type="hidden" name="project_id" value="<?php echo $pr['id']; ?>">
                                            <input type="hidden" name="c_cmd" value="install">
                                            <button type="submit" class="btn btn-sm btn-gray">Composer</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="project_action" value="cms_fix_perms">
                                            <input type="hidden" name="project_id" value="<?php echo $pr['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-gray" title="Сбросить права 777 для storage/uploads">🔑 CMS</button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="project-section">
                                <span style="font-size:0.8em; color:#bb86fc; font-weight:bold;">🕒 Планировщик задач (Cron):</span>
                                <form method="POST" class="flex-bar" style="margin:4px 0;">
                                    <input type="hidden" name="project_action" value="add_project_task">
                                    <input type="hidden" name="project_id" value="<?php echo $pr['id']; ?>">
                                    <input type="text" name="t_name" placeholder="Название" style="width:85px; font-size:0.8em;" required>
                                    <input type="text" name="t_script" placeholder="cron.php" style="flex:1; min-width:90px; font-size:0.8em;" required>
                                    <button type="submit" class="btn btn-sm btn-teal">+</button>
                                </form>
                                <?php if (!empty($pr['tasks'])): ?>
                                    <table style="margin:0;">
                                        <?php foreach ($pr['tasks'] as $ptask): ?>
                                            <tr>
                                                <td style="padding:3px;"><?php echo htmlspecialchars($ptask['name']); ?></td>
                                                <td style="padding:3px;"><code><?php echo htmlspecialchars($ptask['script']); ?></code></td>
                                                <td style="padding:3px; text-align:right;"><a href="?tab=projects&pid=<?php echo $pr['id']; ?>&del_project_task=<?php echo $ptask['id']; ?>" class="btn btn-sm btn-red" style="padding:1px 5px;">✕</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <script>
        function openHtModal(id) { document.getElementById('modal_ht_' + id).style.display = 'flex'; }
        function closeHtModal(id) { document.getElementById('modal_ht_' + id).style.display = 'none'; }
        </script>

    <?php elseif ($tab === 'files'): ?>
        <div class="card">
            <h2>
                <span>📂 Файловый менеджер</span>
                <div class="icon-toolbar">
                    <a href="?tab=files&dir=<?php echo urlencode($current_dir); ?>&split=<?php echo $split_mode ? '0' : '1'; ?>&dir2=<?php echo urlencode($current_dir_2); ?>" class="btn btn-icon <?php echo $split_mode ? 'btn-teal' : 'btn-gray'; ?>" title="<?php echo $split_mode ? 'Одна панель' : 'Две панели (Split)'; ?>">◫</a>
                    <button type="button" class="btn btn-icon btn-gray" onclick="document.getElementById('modal_search').style.display='flex'" title="Поиск текста в файлах">🔍</button>
                    <button type="button" class="btn btn-icon btn-gray" onclick="document.getElementById('modal_diff').style.display='flex'" title="Сравнить два файла">⚖️</button>
                </div>
            </h2>

            <div id="modal_search" class="modal">
                <div class="modal-content">
                    <h3>🔍 Поиск текста в файлах (текущая папка)</h3>
                    <form method="GET">
                        <input type="hidden" name="tab" value="files">
                        <input type="hidden" name="dir" value="<?php echo htmlspecialchars($current_dir); ?>">
                        <input type="text" name="grep_query" class="input-full" placeholder="Введите строку для поиска..." required>
                        <div style="margin-top:10px;">
                            <button type="submit" class="btn btn-teal">Найти</button>
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('modal_search').style.display='none'">Закрыть</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="modal_diff" class="modal">
                <div class="modal-content">
                    <h3>⚖️ Сравнение двух файлов</h3>
                    <form method="GET">
                        <input type="hidden" name="tab" value="files">
                        <input type="hidden" name="dir" value="<?php echo htmlspecialchars($current_dir); ?>">
                        <label style="font-size:0.82em;">Путь к файлу 1:</label>
                        <input type="text" name="diff1" class="input-full" value="<?php echo htmlspecialchars($current_dir . '/'); ?>" required style="margin-bottom:6px;">
                        <label style="font-size:0.82em;">Путь к файлу 2:</label>
                        <input type="text" name="diff2" class="input-full" value="<?php echo htmlspecialchars($current_dir . '/'); ?>" required>
                        <div style="margin-top:10px;">
                            <button type="submit" class="btn btn-teal">Сравнить</button>
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('modal_diff').style.display='none'">Закрыть</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($_GET['grep_query'])): $gq = $_GET['grep_query']; ?>
                <div style="background:#252525; padding:10px; border-radius:6px; margin-bottom:10px; border:1px solid #03dac6;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong>Результаты поиска «<?php echo htmlspecialchars($gq); ?>»:</strong>
                        <a href="?tab=files&dir=<?php echo urlencode($current_dir); ?>" class="btn btn-sm btn-red">✕</a>
                    </div>
                    <div style="max-height:200px; overflow-y:auto; margin-top:6px; font-family:monospace; font-size:0.82em;">
                        <?php
                        $found_any = false;
                        foreach (scandir($current_dir) as $f) {
                            $fp = $current_dir . '/' . $f;
                            if (is_file($fp) && filesize($fp) < 1048576) {
                                $lines = @file($fp);
                                if ($lines) {
                                    foreach ($lines as $lnum => $ltext) {
                                        if (stripos($ltext, $gq) !== false) {
                                            $found_any = true;
                                            echo "<div style='border-bottom:1px solid #333; padding:3px 0;'>";
                                            echo "<a href='?tab=files&dir=" . urlencode($current_dir) . "&edit=" . urlencode($fp) . "' style='color:#03dac6;'>" . htmlspecialchars($f) . ":" . ($lnum + 1) . "</a> — " . htmlspecialchars(trim($ltext));
                                            echo "</div>";
                                        }
                                    }
                                }
                            }
                        }
                        if (!$found_any) echo "<p style='color:#888;'>Совпадений не найдено.</p>";
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['diff1']) && !empty($_GET['diff2'])):
                $f1 = safe_path($_GET['diff1'], '', $allowed_root);
                $f2 = safe_path($_GET['diff2'], '', $allowed_root);
            ?>
                <div style="background:#252525; padding:10px; border-radius:6px; margin-bottom:10px; border:1px solid #bb86fc;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong>Сравнение: <?php echo htmlspecialchars(basename((string)$f1)); ?> vs <?php echo htmlspecialchars(basename((string)$f2)); ?></strong>
                        <a href="?tab=files&dir=<?php echo urlencode($current_dir); ?>" class="btn btn-sm btn-red">✕</a>
                    </div>
                    <?php if ($f1 && $f2 && is_file($f1) && is_file($f2)):
                        $l1 = file($f1); $l2 = file($f2);
                        $max_l = max(count($l1), count($l2));
                    ?>
                        <div style="max-height:250px; overflow-y:auto; margin-top:6px; font-family:monospace; font-size:0.8em;">
                            <table>
                                <tr><th>#</th><th><?php echo htmlspecialchars(basename($f1)); ?></th><th><?php echo htmlspecialchars(basename($f2)); ?></th></tr>
                                <?php for ($i = 0; $i < $max_l; $i++):
                                    $s1 = $l1[$i] ?? ''; $s2 = $l2[$i] ?? '';
                                    $diff_bg = ($s1 !== $s2) ? 'background:#3d1f24;' : '';
                                ?>
                                    <tr style="<?php echo $diff_bg; ?>">
                                        <td><?php echo $i + 1; ?></td>
                                        <td style="white-space:pre-wrap;"><?php echo htmlspecialchars($s1); ?></td>
                                        <td style="white-space:pre-wrap;"><?php echo htmlspecialchars($s2); ?></td>
                                    </tr>
                                <?php endfor; ?>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color:#cf6679;">Один или оба файла не найдены.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($img_preview && is_file($img_preview)): ?>
                <div style="background:#161616; padding:12px; border-radius:6px; text-align:center; margin-bottom:12px; border:1px solid #03dac6;">
                    <h3>🖼 Просмотр: <?php echo htmlspecialchars(basename($img_preview)); ?></h3>
                    <img src="?view_img=<?php echo urlencode($img_preview); ?>" style="max-width:100%; max-height:400px; border-radius:4px; border:1px solid #333;"><br>
                    <a href="?tab=files&dir=<?php echo urlencode(dirname($img_preview)); ?>" class="btn btn-red" style="margin-top:8px;">Закрыть</a>
                </div>
            <?php endif; ?>

            <?php if ($edit_file && is_file($edit_file)): ?>
                <h3>✏️ Редактирование: <?php echo htmlspecialchars(basename($edit_file)); ?></h3>
                <form method="POST">
                    <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($edit_file); ?>">
                    <textarea name="file_content"><?php echo htmlspecialchars(file_get_contents($edit_file)); ?></textarea><br>
                    <button type="submit" name="save_file" class="btn btn-teal">💾 Сохранить</button>
                    <a href="?tab=files&dir=<?php echo urlencode(dirname($edit_file)); ?>" class="btn btn-red">Закрыть</a>
                </form>
            <?php else:
                $render_pane = function($pane_dir, $pane_id) use ($allowed_root, $split_mode, $current_dir, $current_dir_2) {
                    $other_param = ($pane_id === 1) ? "&dir2=" . urlencode($current_dir_2) : "&dir=" . urlencode($current_dir);
                    $dir_key = ($pane_id === 1) ? "dir" : "dir2";
                    $split_str = $split_mode ? "&split=1" : "";
            ?>
                <div class="fm-pane">
                    <div class="breadcrumbs">
                        📍 <strong><?php echo htmlspecialchars($pane_dir); ?></strong>
                    </div>
                    <div class="flex-bar" style="background:#222; padding:6px; border-radius:4px;">
                        <form method="POST" style="display:flex; gap:4px; flex-wrap:nowrap; align-items:center;">
                            <input type="hidden" name="target_dir" value="<?php echo htmlspecialchars($pane_dir); ?>">
                            <input type="text" name="new_name" placeholder="Имя..." required style="width:110px;">
                            <button type="submit" name="fm_action" value="create_folder" class="btn btn-icon" title="Создать папку">📁+</button>
                            <button type="submit" name="fm_action" value="create_file" class="btn btn-icon btn-teal" title="Создать файл">📄+</button>
                        </form>
                        <form method="POST" enctype="multipart/form-data" style="display:flex; gap:4px; align-items:center;">
                            <input type="hidden" name="target_dir" value="<?php echo htmlspecialchars($pane_dir); ?>">
                            <input type="file" name="uploaded_files[]" multiple style="font-size:0.75em; max-width:130px;">
                            <button type="submit" class="btn btn-icon" title="Загрузить файлы">⬆️</button>
                        </form>
                    </div>

                    <table>
                        <tr><th>Имя</th><th>Размер</th><th>Права</th><th>Действия</th></tr>
                        <?php if ($pane_dir !== $allowed_root && $pane_dir !== '/'): ?>
                            <tr><td colspan="4"><a href="?tab=files&<?php echo $dir_key; ?>=<?php echo urlencode(dirname($pane_dir)) . $other_param . $split_str; ?>" style="color:#03dac6; font-weight:bold; text-decoration:none;">📁 .. (Наверх)</a></td></tr>
                        <?php endif; ?>
                        <?php
                        $items = @scandir($pane_dir) ?: [];
                        $dirs = []; $files = [];
                        foreach ($items as $it) {
                            if ($it === '.' || $it === '..') continue;
                            if (is_dir($pane_dir . '/' . $it)) $dirs[] = $it; else $files[] = $it;
                        }
                        sort($dirs); sort($files);
                        foreach (array_merge($dirs, $files) as $item):
                            $full_path = $pane_dir . '/' . $item;
                            $is_dir = is_dir($full_path);
                            $perms = substr(sprintf('%o', @fileperms($full_path)), -3);
                            $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
                            $is_img = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico']);
                            $enc_path = urlencode($full_path);
                        ?>
                            <tr>
                                <td>
                                    <?php if ($is_dir): ?>
                                        📁 <a href="?tab=files&<?php echo $dir_key; ?>=<?php echo $enc_path . $other_param . $split_str; ?>" style="color:#bb86fc; font-weight:bold; text-decoration:none;"><?php echo htmlspecialchars($item); ?></a>
                                    <?php elseif ($is_img): ?>
                                        🖼 <a href="?tab=files&dir=<?php echo urlencode($current_dir); ?>&preview_img=<?php echo $enc_path; ?>" style="color:#03dac6; text-decoration:none;"><?php echo htmlspecialchars($item); ?></a>
                                    <?php else: ?>
                                        📄 <?php echo htmlspecialchars($item); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $is_dir ? '-' : format_size(@filesize($full_path)); ?></td>
                                <td>
                                    <form method="POST" style="display:inline-flex; gap:2px; align-items:center; margin:0;">
                                        <input type="hidden" name="target_path" value="<?php echo htmlspecialchars($full_path); ?>">
                                        <input type="text" name="chmod_val" value="<?php echo $perms; ?>" style="width:42px; text-align:center; font-size:0.75em; padding:2px;">
                                        <button type="submit" name="fm_action" value="chmod" class="btn btn-sm btn-gray" style="padding:2px 5px;" title="Chmod">🔑</button>
                                    </form>
                                </td>
                                <td>
                                    <div class="icon-toolbar">
                                        <?php if (!$is_dir): ?>
                                            <?php if ($is_img): ?>
                                                <a href="?tab=files&dir=<?php echo urlencode($current_dir); ?>&preview_img=<?php echo $enc_path; ?>" class="btn btn-sm btn-teal" title="Просмотр">👁</a>
                                            <?php else: ?>
                                                <a href="?tab=files&dir=<?php echo urlencode($current_dir); ?>&edit=<?php echo $enc_path; ?>" class="btn btn-sm" title="Редактировать">✏️</a>
                                            <?php endif; ?>
                                            <a href="?download=<?php echo $enc_path; ?>" class="btn btn-sm btn-gray" title="Скачать">⬇️</a>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline-flex; gap:2px; margin:0;">
                                            <input type="hidden" name="target_path" value="<?php echo htmlspecialchars($full_path); ?>">
                                            <button type="submit" name="fm_action" value="zip" class="btn btn-sm btn-gray" title="Упаковать в ZIP">📦</button>
                                            <?php if (!$is_dir && $ext === 'zip'): ?>
                                                <button type="submit" name="fm_action" value="unzip" class="btn btn-sm btn-teal" title="Распаковать">📂</button>
                                            <?php endif; ?>
                                        </form>
                                        <a href="?tab=files&dir=<?php echo urlencode($current_dir) . $other_param . $split_str; ?>&delete=<?php echo $enc_path; ?>" onclick="return confirm('Удалить?');" class="btn btn-sm btn-red" title="Удалить">✕</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php }; ?>

            <?php if ($split_mode): ?>
                <div class="fm-split">
                    <?php $render_pane($current_dir, 1); ?>
                    <?php $render_pane($current_dir_2, 2); ?>
                </div>
            <?php else: ?>
                <?php $render_pane($current_dir, 1); ?>
            <?php endif; ?>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'database'): ?>
        <div class="card">
            <h2>
                <span>🗄️ Универсальный менеджер баз данных</span>
                <div class="icon-toolbar">
                    <?php
                    $db_keys = array_keys($available_dbs);
                    $default_type = in_array('mysql', $db_keys) && $available_dbs['mysql']['ready'] ? 'mysql' : ($db_keys[0] ?? 'sqlite');
                    $active_db_type = isset($_GET['db_type']) && isset($available_dbs[$_GET['db_type']]) ? $_GET['db_type'] : $default_type;
                    ?>
                    <form method="GET" style="margin:0; display:inline-flex; gap:4px; align-items:center;">
                        <input type="hidden" name="tab" value="database">
                        <select name="db_type" onchange="this.form.submit()" style="border-color:#03dac6; font-weight:bold;">
                            <?php foreach ($available_dbs as $k => $info): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $active_db_type === $k ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($info['label']) . ($info['ready'] ? ' 🟢' : ' 🔴'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </h2>

            <?php if (empty($available_dbs)): ?>
                <div class="alert">Не обнаружено ни одной установленной СУБД. Установите SQLite, MariaDB, PostgreSQL или MongoDB на главной странице.</div>

            <?php elseif ($active_db_type === 'sqlite'): ?>
                <?php
                $sqlite_files = $available_dbs['sqlite']['files'];
                $sel_sqlite = isset($_GET['sqlite_file']) && is_file($_GET['sqlite_file']) ? realpath($_GET['sqlite_file']) : (array_key_first($sqlite_files) ?: $default_sqlite_db);
                $sel_tbl = $_GET['tbl'] ?? '';
                $db_sub = $_GET['db_sub'] ?? ($sel_tbl ? 'browse' : 'tables');

                if (isset($_POST['create_sqlite_file']) && !empty($_POST['new_sqlite_name'])) {
                    $fn = preg_replace('/[^a-zA-Z0-9_.-]/', '_', trim($_POST['new_sqlite_name']));
                    if (!preg_match('/\.(db|sqlite|sqlite3)$/i', $fn)) $fn .= '.db';
                    $new_p = $base_dir . '/' . $fn;
                    @touch($new_p);
                    if (file_exists($new_p)) {
                        $sel_sqlite = realpath($new_p);
                        $sql_msg = "Создан файл БД: $fn";
                    }
                }

                $pdo = null;
                try {
                    $pdo = new PDO('sqlite:' . $sel_sqlite);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                } catch (Exception $e) {
                    $sql_msg = "Ошибка SQLite: " . $e->getMessage();
                }

                if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pma_action'])) {
                    $pma_act = $_POST['pma_action'];
                    try {
                        if ($pma_act === 'create_table' && !empty($_POST['new_tbl_name'])) {
                            $ntbl = trim($_POST['new_tbl_name']);
                            $cols_sql = [];
                            foreach ($_POST['col_name'] as $i => $cname) {
                                $cname = trim($cname);
                                if ($cname === '') continue;
                                $ctype = $_POST['col_type'][$i];
                                $cai = isset($_POST['col_ai'][$i]) ? " PRIMARY KEY AUTOINCREMENT" : "";
                                $cols_sql[] = "\"$cname\" $ctype$cai";
                            }
                            if (!empty($cols_sql)) {
                                $pdo->exec("CREATE TABLE \"$ntbl\" (" . implode(", ", $cols_sql) . ");");
                                $sql_msg = "Таблица `$ntbl` создана.";
                            }
                        } elseif ($pma_act === 'drop_table' && !empty($_POST['target_tbl'])) {
                            $pdo->exec("DROP TABLE \"" . $_POST['target_tbl'] . "\";");
                            $sql_msg = "Таблица удалена.";
                            $sel_tbl = ''; $db_sub = 'tables';
                        } elseif ($pma_act === 'insert_row' && $sel_tbl) {
                            $cols = []; $vals = []; $params = [];
                            foreach ($_POST['row_data'] as $k => $v) {
                                if ($v !== '') {
                                    $cols[] = "\"$k\"";
                                    $vals[] = "?";
                                    $params[] = $v;
                                }
                            }
                            if (!empty($cols)) {
                                $stmt = $pdo->prepare("INSERT INTO \"$sel_tbl\" (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ")");
                                $stmt->execute($params);
                                $sql_msg = "Запись добавлена.";
                                $db_sub = 'browse';
                            }
                        } elseif ($pma_act === 'delete_row' && $sel_tbl && isset($_POST['row_id'])) {
                            $pdo->exec("DELETE FROM \"$sel_tbl\" WHERE rowid = " . intval($_POST['row_id']));
                            $sql_msg = "Запись удалена.";
                        } elseif ($pma_act === 'run_sql' && !empty(trim($_POST['sql_query']))) {
                            $qstr = trim($_POST['sql_query']);
                            if (preg_match('/^(SELECT|PRAGMA|WITH|EXPLAIN)/i', $qstr)) {
                                $sql_result = $pdo->query($qstr)->fetchAll(PDO::FETCH_ASSOC);
                                $sql_msg = "Запрос выполнен.";
                            } else {
                                $aff = $pdo->exec($qstr);
                                $sql_msg = "Выполнено. Затронуто строк: $aff";
                            }
                        }
                    } catch (Exception $e) {
                        $sql_msg = "Ошибка SQLite: " . $e->getMessage();
                    }
                }
                ?>

                <?php if (!empty($sql_msg)): ?><div class="alert"><?php echo htmlspecialchars($sql_msg); ?></div><?php endif; ?>

                <div class="flex-bar" style="background:#252525; padding:8px; border-radius:4px;">
                    <form method="GET" style="display:inline-flex; gap:4px; align-items:center;">
                        <input type="hidden" name="tab" value="database">
                        <input type="hidden" name="db_type" value="sqlite">
                        <select name="sqlite_file" onchange="this.form.submit()">
                            <?php foreach ($sqlite_files as $path => $label): ?>
                                <option value="<?php echo htmlspecialchars($path); ?>" <?php echo $path === $sel_sqlite ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <form method="POST" style="display:inline-flex; gap:4px; align-items:center;">
                        <input type="text" name="new_sqlite_name" placeholder="Новая БД (.db)" style="width:130px;">
                        <button type="submit" name="create_sqlite_file" class="btn btn-sm btn-teal">+ Файл БД</button>
                    </form>
                    <a href="?db_export=1&db_type=sqlite&sqlite_file=<?php echo urlencode($sel_sqlite); ?>" class="btn btn-sm btn-gray">📥 Скачать .db</a>
                </div>

                <div class="subnav">
                    <a href="?tab=database&db_type=sqlite&sqlite_file=<?php echo urlencode($sel_sqlite); ?>&db_sub=tables" class="<?php echo $db_sub === 'tables' ? 'active' : ''; ?>">Таблицы</a>
                    <a href="?tab=database&db_type=sqlite&sqlite_file=<?php echo urlencode($sel_sqlite); ?>&db_sub=sql" class="<?php echo $db_sub === 'sql' ? 'active' : ''; ?>">SQL</a>
                </div>

                <?php if ($pdo && $db_sub === 'tables' && $sel_tbl === ''): ?>
                    <div class="table-wrap">
                        <table>
                            <tr><th>Таблица</th><th>Действия</th></tr>
                            <?php
                            $tbls = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($tbls as $tname):
                            ?>
                                <tr>
                                    <td><a href="?tab=database&db_type=sqlite&sqlite_file=<?php echo urlencode($sel_sqlite); ?>&tbl=<?php echo urlencode($tname); ?>&db_sub=browse" style="color:#03dac6; font-weight:bold; text-decoration:none;">📋 <?php echo htmlspecialchars($tname); ?></a></td>
                                    <td>
                                        <a href="?tab=database&db_type=sqlite&sqlite_file=<?php echo urlencode($sel_sqlite); ?>&tbl=<?php echo urlencode($tname); ?>&db_sub=browse" class="btn btn-sm">Обзор</a>
                                        <a href="?tab=database&db_type=sqlite&sqlite_file=<?php echo urlencode($sel_sqlite); ?>&tbl=<?php echo urlencode($tname); ?>&db_sub=structure" class="btn btn-sm btn-gray">Структура</a>
                                        <a href="?tab=database&db_type=sqlite&sqlite_file=<?php echo urlencode($sel_sqlite); ?>&tbl=<?php echo urlencode($tname); ?>&db_sub=insert" class="btn btn-sm btn-teal">Вставить</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить таблицу?');">
                                            <input type="hidden" name="target_tbl" value="<?php echo htmlspecialchars($tname); ?>">
                                            <button type="submit" name="pma_action" value="drop_table" class="btn btn-sm btn-red">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <h3>Создать таблицу</h3>
                    <form method="POST" style="background:#252525; padding:10px; border-radius:4px;">
                        <input type="text" name="new_tbl_name" placeholder="Название таблицы" required style="width:200px; margin-bottom:8px;">
                        <div class="table-wrap">
                            <table>
                                <tr><th>Столбец</th><th>Тип</th><th>PK / AutoInc</th></tr>
                                <?php for ($c = 0; $c < 4; $c++): ?>
                                    <tr>
                                        <td><input type="text" name="col_name[]" placeholder="<?php echo $c === 0 ? 'id' : 'col_' . $c; ?>"></td>
                                        <td>
                                            <select name="col_type[]">
                                                <option value="INTEGER" <?php echo $c === 0 ? 'selected' : ''; ?>>INTEGER</option>
                                                <option value="TEXT" <?php echo $c > 0 ? 'selected' : ''; ?>>TEXT</option>
                                                <option value="REAL">REAL</option>
                                                <option value="BLOB">BLOB</option>
                                            </select>
                                        </td>
                                        <td><input type="checkbox" name="col_ai[<?php echo $c; ?>]" <?php echo $c === 0 ? 'checked' : ''; ?>></td>
                                    </tr>
                                <?php endfor; ?>
                            </table>
                        </div>
                        <button type="submit" name="pma_action" value="create_table" class="btn btn-teal" style="margin-top:8px;">Создать таблицу</button>
                    </form>

                <?php elseif ($pdo && $db_sub === 'browse' && $sel_tbl !== ''): ?>
                    <div class="flex-bar">
                        <span>Таблица: <strong><?php echo htmlspecialchars($sel_tbl); ?></strong></span>
                        <a href="?tab=database&db_type=sqlite&sqlite_file=<?php echo urlencode($sel_sqlite); ?>&db_sub=tables" class="btn btn-sm btn-gray">&laquo; Таблицы</a>
                        <a href="?tab=database&db_type=sqlite&sqlite_file=<?php echo urlencode($sel_sqlite); ?>&tbl=<?php echo urlencode($sel_tbl); ?>&db_sub=insert" class="btn btn-sm btn-teal">+ Запись</a>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <tr>
                                <th>Действия</th>
                                <?php
                                $cols_meta = $pdo->query("PRAGMA table_info(\"$sel_tbl\")")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($cols_meta as $cm) echo "<th>" . htmlspecialchars($cm['name']) . "</th>";
                                ?>
                            </tr>
                            <?php
                            $rows = $pdo->query("SELECT rowid, * FROM \"$sel_tbl\" LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($rows as $row):
                                $rid = $row['rowid'];
                            ?>
                                <tr>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить?');">
                                            <input type="hidden" name="row_id" value="<?php echo $rid; ?>">
                                            <button type="submit" name="pma_action" value="delete_row" class="btn btn-sm btn-red">✕</button>
                                        </form>
                                    </td>
                                    <?php foreach ($cols_meta as $cm): $val = $row[$cm['name']] ?? null; ?>
                                        <td><?php echo $val === null ? '<em style="color:#888;">NULL</em>' : htmlspecialchars(mb_strimwidth((string)$val, 0, 60, '...')); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                <?php elseif ($pdo && $db_sub === 'structure' && $sel_tbl !== ''): ?>
                    <div class="flex-bar">
                        <span>Структура: <strong><?php echo htmlspecialchars($sel_tbl); ?></strong></span>
                        <a href="?tab=database&db_type=sqlite&sqlite_file=<?php echo urlencode($sel_sqlite); ?>&db_sub=tables" class="btn btn-sm btn-gray">&laquo; Таблицы</a>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <tr><th>CID</th><th>Имя</th><th>Тип</th><th>Not Null</th><th>PK</th></tr>
                            <?php foreach ($pdo->query("PRAGMA table_info(\"$sel_tbl\")")->fetchAll(PDO::FETCH_ASSOC) as $ci): ?>
                                <tr>
                                    <td><?php echo $ci['cid']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($ci['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ci['type']); ?></td>
                                    <td><?php echo $ci['notnull'] ? 'YES' : 'NO'; ?></td>
                                    <td><?php echo $ci['pk'] ? '✅' : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                <?php elseif ($pdo && $db_sub === 'insert' && $sel_tbl !== ''): ?>
                    <form method="POST">
                        <div class="table-wrap">
                            <table>
                                <tr><th>Столбец</th><th>Тип</th><th>Значение</th></tr>
                                <?php foreach ($pdo->query("PRAGMA table_info(\"$sel_tbl\")")->fetchAll(PDO::FETCH_ASSOC) as $ci): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($ci['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($ci['type']); ?></td>
                                        <td><input type="text" name="row_data[<?php echo htmlspecialchars($ci['name']); ?>]" style="width:100%;"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                        <button type="submit" name="pma_action" value="insert_row" class="btn btn-teal" style="margin-top:8px;">Вставить</button>
                    </form>

                <?php elseif ($pdo && $db_sub === 'sql'): ?>
                    <form method="POST">
                        <textarea name="sql_query" style="height:120px;"><?php echo isset($_POST['sql_query']) ? htmlspecialchars($_POST['sql_query']) : "SELECT name FROM sqlite_master WHERE type='table';"; ?></textarea>
                        <button type="submit" name="pma_action" value="run_sql" class="btn btn-teal" style="margin-top:6px;">Выполнить SQL</button>
                    </form>
                    <?php if (is_array($sql_result) && count($sql_result) > 0): ?>
                        <div class="table-wrap" style="margin-top:10px;">
                            <table>
                                <tr><?php foreach (array_keys($sql_result[0]) as $col) echo "<th>" . htmlspecialchars($col) . "</th>"; ?></tr>
                                <?php foreach ($sql_result as $row): ?>
                                    <tr><?php foreach ($row as $val) echo "<td>" . ($val === null ? '<em>NULL</em>' : htmlspecialchars((string)$val)) . "</td>"; ?></tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php elseif ($active_db_type === 'mysql'): ?>
                <?php
                $conn = class_exists('mysqli') ? @new mysqli($db_host, $db_user, $db_pass) : null;
                if (!$conn || $conn->connect_error):
                ?>
                    <div class="alert"><strong>MariaDB не запущена!</strong> Запустите её на главной странице.</div>
                <?php else:
                    $conn->set_charset("utf8mb4");
                    $sel_db = $_GET['db'] ?? '';
                    $sel_tbl = $_GET['tbl'] ?? '';
                    $db_sub = $_GET['db_sub'] ?? ($sel_tbl ? 'browse' : ($sel_db ? 'tables' : 'dbs'));

                    if ($sel_db !== '' && !$conn->select_db($sel_db)) {
                        $sel_db = ''; $sel_tbl = ''; $db_sub = 'dbs';
                    }

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pma_action'])) {
                        $pma_act = $_POST['pma_action'];
                        if ($pma_act === 'create_db' && !empty($_POST['new_db_name'])) {
                            $ndb = $conn->real_escape_string(trim($_POST['new_db_name']));
                            if ($conn->query("CREATE DATABASE `$ndb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) $sql_msg = "БД `$ndb` создана.";
                            else $sql_msg = "Ошибка: " . $conn->error;
                        } elseif ($pma_act === 'drop_db' && !empty($_POST['target_db'])) {
                            $tdb = $conn->real_escape_string($_POST['target_db']);
                            if ($conn->query("DROP DATABASE `$tdb`")) { $sql_msg = "БД `$tdb` удалена."; if ($sel_db === $tdb) { $sel_db = ''; $db_sub = 'dbs'; } }
                        } elseif ($pma_act === 'create_table' && $sel_db && !empty($_POST['new_tbl_name'])) {
                            $ntbl = $conn->real_escape_string(trim($_POST['new_tbl_name']));
                            $cols_sql = [];
                            foreach ($_POST['col_name'] as $i => $cname) {
                                $cname = trim($cname);
                                if ($cname === '') continue;
                                $ctype = $_POST['col_type'][$i];
                                $clen = trim($_POST['col_len'][$i]);
                                $cdef = $ctype . ($clen !== '' ? "($clen)" : '');
                                $cai = isset($_POST['col_ai'][$i]) ? " AUTO_INCREMENT PRIMARY KEY" : "";
                                $cnull = isset($_POST['col_null'][$i]) ? " NULL" : " NOT NULL";
                                $cols_sql[] = "`" . $conn->real_escape_string($cname) . "` $cdef$cnull$cai";
                            }
                            if (!empty($cols_sql)) {
                                if ($conn->query("CREATE TABLE `$ntbl` (" . implode(", ", $cols_sql) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;")) $sql_msg = "Таблица `$ntbl` создана.";
                                else $sql_msg = "Ошибка: " . $conn->error;
                            }
                        } elseif ($pma_act === 'tbl_op' && $sel_db && !empty($_POST['target_tbl'])) {
                            $ttbl = $conn->real_escape_string($_POST['target_tbl']);
                            if ($_POST['op_type'] === 'drop' && $conn->query("DROP TABLE `$ttbl`")) { $sql_msg = "Таблица удалена."; $sel_tbl = ''; $db_sub = 'tables'; }
                            elseif ($_POST['op_type'] === 'truncate' && $conn->query("TRUNCATE TABLE `$ttbl`")) $sql_msg = "Таблица очищена.";
                        } elseif ($pma_act === 'insert_row' && $sel_db && $sel_tbl) {
                            $cols = []; $vals = [];
                            foreach ($_POST['row_data'] as $k => $v) {
                                if ($v !== '') { $cols[] = "`" . $conn->real_escape_string($k) . "`"; $vals[] = "'" . $conn->real_escape_string($v) . "'"; }
                            }
                            if (!empty($cols) && $conn->query("INSERT INTO `$sel_tbl` (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ")")) {
                                $sql_msg = "Запись добавлена."; $db_sub = 'browse';
                            } else $sql_msg = "Ошибка: " . $conn->error;
                        } elseif ($pma_act === 'delete_row' && $sel_db && $sel_tbl && !empty($_POST['where_clause'])) {
                            $where = base64_decode($_POST['where_clause']);
                            if ($conn->query("DELETE FROM `$sel_tbl` WHERE $where LIMIT 1")) $sql_msg = "Запись удалена.";
                        } elseif ($pma_act === 'run_sql' && !empty(trim($_POST['sql_query']))) {
                            if ($conn->multi_query(trim($_POST['sql_query']))) {
                                $sql_msg = "Запрос выполнен.";
                                do {
                                    if ($res = $conn->store_result()) {
                                        $sql_result = [];
                                        while ($row = $res->fetch_assoc()) $sql_result[] = $row;
                                        $res->free();
                                    }
                                } while ($conn->more_results() && $conn->next_result());
                            } else $sql_msg = "Ошибка SQL: " . $conn->error;
                        }
                    }
                ?>

                <?php if (!empty($sql_msg)): ?><div class="alert"><?php echo htmlspecialchars($sql_msg); ?></div><?php endif; ?>

                <div class="flex-bar" style="background:#252525; padding:8px; border-radius:4px;">
                    <a href="?tab=database&db_type=mysql&db_sub=dbs" class="btn btn-sm btn-gray">🏠 Сервер</a>
                    <form method="GET" style="display:inline-flex; gap:4px; align-items:center;">
                        <input type="hidden" name="tab" value="database">
                        <input type="hidden" name="db_type" value="mysql">
                        <select name="db" onchange="this.form.submit()">
                            <option value="">-- Выберите БД --</option>
                            <?php
                            $dbs_res = $conn->query("SHOW DATABASES");
                            $db_list = [];
                            while ($r = $dbs_res->fetch_row()) {
                                $db_list[] = $r[0];
                                echo "<option value='" . htmlspecialchars($r[0]) . "' " . ($r[0] === $sel_db ? 'selected' : '') . ">" . htmlspecialchars($r[0]) . "</option>";
                            }
                            ?>
                        </select>
                    </form>
                    <?php if ($sel_db !== ''): ?>
                        <a href="?db_export=<?php echo urlencode($sel_db); ?>&db_type=mysql" class="btn btn-sm btn-gray">📥 Экспорт .sql</a>
                    <?php endif; ?>
                </div>

                <div class="subnav">
                    <?php if ($sel_db === ''): ?>
                        <a href="?tab=database&db_type=mysql&db_sub=dbs" class="<?php echo $db_sub === 'dbs' ? 'active' : ''; ?>">Базы данных</a>
                        <a href="?tab=database&db_type=mysql&db_sub=sql" class="<?php echo $db_sub === 'sql' ? 'active' : ''; ?>">SQL</a>
                    <?php elseif ($sel_tbl === ''): ?>
                        <a href="?tab=database&db_type=mysql&db=<?php echo urlencode($sel_db); ?>&db_sub=tables" class="<?php echo $db_sub === 'tables' ? 'active' : ''; ?>">Таблицы</a>
                        <a href="?tab=database&db_type=mysql&db=<?php echo urlencode($sel_db); ?>&db_sub=sql" class="<?php echo $db_sub === 'sql' ? 'active' : ''; ?>">SQL</a>
                    <?php else: ?>
                        <a href="?tab=database&db_type=mysql&db=<?php echo urlencode($sel_db); ?>&tbl=<?php echo urlencode($sel_tbl); ?>&db_sub=browse" class="<?php echo $db_sub === 'browse' ? 'active' : ''; ?>">Обзор</a>
                        <a href="?tab=database&db_type=mysql&db=<?php echo urlencode($sel_db); ?>&tbl=<?php echo urlencode($sel_tbl); ?>&db_sub=insert" class="<?php echo $db_sub === 'insert' ? 'active' : ''; ?>">Вставить</a>
                        <a href="?tab=database&db_type=mysql&db=<?php echo urlencode($sel_db); ?>&tbl=<?php echo urlencode($sel_tbl); ?>&db_sub=sql" class="<?php echo $db_sub === 'sql' ? 'active' : ''; ?>">SQL</a>
                    <?php endif; ?>
                </div>

                <?php if ($db_sub === 'dbs'): ?>
                    <form method="POST" class="flex-bar" style="margin-bottom:10px;">
                        <input type="text" name="new_db_name" placeholder="Имя новой БД..." required>
                        <button type="submit" name="pma_action" value="create_db" class="btn btn-teal">Создать БД</button>
                    </form>
                    <div class="table-wrap">
                        <table>
                            <tr><th>База данных</th><th>Действия</th></tr>
                            <?php foreach ($db_list as $dname): ?>
                                <tr>
                                    <td><a href="?tab=database&db_type=mysql&db=<?php echo urlencode($dname); ?>&db_sub=tables" style="color:#bb86fc; font-weight:bold; text-decoration:none;">🗄 <?php echo htmlspecialchars($dname); ?></a></td>
                                    <td>
                                        <a href="?tab=database&db_type=mysql&db=<?php echo urlencode($dname); ?>&db_sub=tables" class="btn btn-sm">Открыть</a>
                                        <?php if (!in_array($dname, ['information_schema', 'mysql', 'performance_schema', 'sys'])): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить БД?');">
                                                <input type="hidden" name="target_db" value="<?php echo htmlspecialchars($dname); ?>">
                                                <button type="submit" name="pma_action" value="drop_db" class="btn btn-sm btn-red">Удалить</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                <?php elseif ($db_sub === 'tables' && $sel_db !== ''): ?>
                    <div class="table-wrap">
                        <table>
                            <tr><th>Таблица</th><th>Строк</th><th>Действия</th></tr>
                            <?php
                            $st_res = $conn->query("SHOW TABLE STATUS");
                            while ($st = $st_res->fetch_assoc()):
                                $tname = $st['Name'];
                            ?>
                                <tr>
                                    <td><a href="?tab=database&db_type=mysql&db=<?php echo urlencode($sel_db); ?>&tbl=<?php echo urlencode($tname); ?>&db_sub=browse" style="color:#03dac6; font-weight:bold; text-decoration:none;">📋 <?php echo htmlspecialchars($tname); ?></a></td>
                                    <td><?php echo $st['Rows']; ?></td>
                                    <td>
                                        <a href="?tab=database&db_type=mysql&db=<?php echo urlencode($sel_db); ?>&tbl=<?php echo urlencode($tname); ?>&db_sub=browse" class="btn btn-sm">Обзор</a>
                                        <a href="?tab=database&db_type=mysql&db=<?php echo urlencode($sel_db); ?>&tbl=<?php echo urlencode($tname); ?>&db_sub=insert" class="btn btn-sm btn-teal">Вставить</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить?');">
                                            <input type="hidden" name="target_tbl" value="<?php echo htmlspecialchars($tname); ?>">
                                            <input type="hidden" name="op_type" value="drop">
                                            <button type="submit" name="pma_action" value="tbl_op" class="btn btn-sm btn-red">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    </div>

                <?php elseif ($db_sub === 'browse' && $sel_db !== '' && $sel_tbl !== ''): ?>
                    <?php
                    $cols_info = [];
                    $c_res = $conn->query("SHOW COLUMNS FROM `$sel_tbl`");
                    while ($cr = $c_res->fetch_assoc()) $cols_info[] = $cr;
                    $rows_res = $conn->query("SELECT * FROM `$sel_tbl` LIMIT 50");
                    ?>
                    <div class="table-wrap">
                        <table>
                            <tr><th>Действия</th><?php foreach ($cols_info as $ci) echo "<th>" . htmlspecialchars($ci['Field']) . "</th>"; ?></tr>
                            <?php while ($row = $rows_res->fetch_assoc()):
                                $parts = [];
                                foreach ($row as $k => $v) $parts[] = $v === null ? "`$k` IS NULL" : "`$k` = '" . $conn->real_escape_string($v) . "'";
                                $w_b64 = base64_encode(implode(" AND ", $parts));
                            ?>
                                <tr>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить?');">
                                            <input type="hidden" name="where_clause" value="<?php echo htmlspecialchars($w_b64); ?>">
                                            <button type="submit" name="pma_action" value="delete_row" class="btn btn-sm btn-red">✕</button>
                                        </form>
                                    </td>
                                    <?php foreach ($row as $val) echo "<td>" . ($val === null ? '<em style="color:#888;">NULL</em>' : htmlspecialchars(mb_strimwidth((string)$val, 0, 60, '...'))) . "</td>"; ?>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    </div>

                <?php elseif ($db_sub === 'insert' && $sel_db !== '' && $sel_tbl !== ''): ?>
                    <form method="POST">
                        <div class="table-wrap">
                            <table>
                                <tr><th>Столбец</th><th>Значение</th></tr>
                                <?php
                                $c_res = $conn->query("SHOW COLUMNS FROM `$sel_tbl`");
                                while ($ci = $c_res->fetch_assoc()): $fn = $ci['Field'];
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($fn); ?></strong></td>
                                        <td><input type="text" name="row_data[<?php echo htmlspecialchars($fn); ?>]" style="width:100%;"></td>
                                    </tr>
                                <?php endwhile; ?>
                            </table>
                        </div>
                        <button type="submit" name="pma_action" value="insert_row" class="btn btn-teal" style="margin-top:8px;">Вставить</button>
                    </form>

                <?php elseif ($db_sub === 'sql'): ?>
                    <form method="POST">
                        <textarea name="sql_query" style="height:120px;"><?php echo isset($_POST['sql_query']) ? htmlspecialchars($_POST['sql_query']) : ($sel_tbl ? "SELECT * FROM `$sel_tbl` LIMIT 50;" : "SHOW DATABASES;"); ?></textarea>
                        <button type="submit" name="pma_action" value="run_sql" class="btn btn-teal" style="margin-top:6px;">Выполнить SQL</button>
                    </form>
                    <?php if (is_array($sql_result) && count($sql_result) > 0): ?>
                        <div class="table-wrap" style="margin-top:10px;">
                            <table>
                                <tr><?php foreach (array_keys($sql_result[0]) as $col) echo "<th>" . htmlspecialchars($col) . "</th>"; ?></tr>
                                <?php foreach ($sql_result as $row): ?>
                                    <tr><?php foreach ($row as $val) echo "<td>" . ($val === null ? '<em>NULL</em>' : htmlspecialchars((string)$val)) . "</td>"; ?></tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; $conn->close(); endif; ?>

            <?php else: ?>
                <?php
                // Универсальная консоль для PostgreSQL, MongoDB, Redis
                $cli_out = "";
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nosql_cmd'])) {
                    $q = trim($_POST['nosql_cmd']);
                    if ($active_db_type === 'pgsql') {
                        $cli_out = (string)@shell_exec("psql -U postgres -c " . escapeshellarg($q) . " 2>&1");
                    } elseif ($active_db_type === 'mongodb') {
                        $bin = cmd_exists('mongosh') ? 'mongosh' : 'mongo';
                        $cli_out = (string)@shell_exec("$bin --quiet --eval " . escapeshellarg($q) . " 2>&1");
                    } elseif ($active_db_type === 'redis') {
                        $cli_out = (string)@shell_exec("redis-cli " . $q . " 2>&1");
                    }
                }
                $default_q = $active_db_type === 'pgsql' ? '\l' : ($active_db_type === 'mongodb' ? 'db.adminCommand({ listDatabases: 1 })' : 'INFO keyspace');
                ?>
                <?php if (!$available_dbs[$active_db_type]['ready']): ?>
                    <div class="alert">Служба <strong><?php echo htmlspecialchars($available_dbs[$active_db_type]['label']); ?></strong> остановлена или не запущена. Включите её на главной странице.</div>
                <?php endif; ?>
                <form method="POST">
                    <label style="font-size:0.85em; color:#aaa;">Интерактивная консоль запросов (<?php echo htmlspecialchars($available_dbs[$active_db_type]['label']); ?>):</label>
                    <textarea name="nosql_cmd" style="height:100px; margin-top:4px;"><?php echo htmlspecialchars($_POST['nosql_cmd'] ?? $default_q); ?></textarea>
                    <button type="submit" class="btn btn-teal" style="margin-top:6px;">Выполнить команду</button>
                </form>
                <?php if ($cli_out !== ""): ?>
                    <pre class="logbox" style="margin-top:10px; height:220px;"><?php echo htmlspecialchars($cli_out); ?></pre>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'terminal'): ?>
        <div class="card">
            <h2>💻 Классический веб-терминал</h2>
            <div style="margin-bottom: 8px; display: flex; gap: 4px; flex-wrap: wrap;">
                <button type="button" class="btn btn-sm btn-gray" onclick="runQuickCmd('ls -la')">ls -la</button>
                <button type="button" class="btn btn-sm btn-gray" onclick="runQuickCmd('pkg update && pkg upgrade -y')">pkg update</button>
                <button type="button" class="btn btn-sm btn-gray" onclick="runQuickCmd('df -h')">df -h</button>
                <button type="button" class="btn btn-sm btn-gray" onclick="runQuickCmd('ps aux')">ps aux</button>
                <button type="button" class="btn btn-sm btn-red" onclick="runQuickCmd('clear')">clear</button>
            </div>

            <div class="classic-terminal" onclick="document.getElementById('term_input').focus()">
                <div class="term-history" id="term_history">
                    <div style="color:#888; margin-bottom:6px;">LocalServerPanel Bash Shell [CWD: <?php echo htmlspecialchars($_SESSION['term_cwd']); ?>]</div>
                    <?php foreach ($_SESSION['term_history'] as $entry): ?>
                        <div class="term-entry">
                            <div class="term-prompt">termux@lsp:<?php echo htmlspecialchars(basename($entry['cwd'])); ?>$ <?php echo htmlspecialchars($entry['cmd']); ?></div>
                            <?php if ($entry['out'] !== ''): ?>
                                <pre class="term-out"><?php echo htmlspecialchars($entry['out']); ?></pre>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form method="POST" id="term_form" class="term-input-line">
                    <span class="term-prompt">termux@lsp:<?php echo htmlspecialchars(basename($_SESSION['term_cwd'])); ?>$</span>
                    <input type="text" id="term_input" name="term_cmd" autocomplete="off" autofocus placeholder="Введите команду...">
                </form>
            </div>

            <script>
            const histBox = document.getElementById('term_history');
            if (histBox) histBox.scrollTop = histBox.scrollHeight;
            function runQuickCmd(cmd) {
                document.getElementById('term_input').value = cmd;
                document.getElementById('term_form').submit();
            }
            </script>

            <h3 style="margin-top: 16px;">📜 Логи веб-сервера (Error Log & Access Log)</h3>
            <form method="POST" style="margin-bottom:4px;"><button type="submit" name="clear_logs" class="btn btn-sm btn-red">Очистить оба лога</button></form>
            
            <p style="font-size:0.82em; color:#bbb; margin:6px 0 2px 0;"><strong>Error Log</strong> (Ошибки):</p>
            <pre class="logbox"><?php
                if (file_exists($error_log_path)) {
                    echo htmlspecialchars(implode("", array_slice(file($error_log_path), -40))) ?: "Лог ошибок пуст.";
                } else {
                    echo "Файл логов не найден.";
                }
            ?></pre>

            <p style="font-size:0.82em; color:#bbb; margin:10px 0 2px 0;"><strong>Access Log</strong> (Запросы):</p>
            <pre class="logbox"><?php
                if (file_exists($access_log_path)) {
                    echo htmlspecialchars(implode("", array_slice(file($access_log_path), -40))) ?: "Лог запросов пуст.";
                } else {
                    echo "Файл access_log не найден.";
                }
            ?></pre>
        </div>
    <?php endif; ?>

    </div>

    <footer>
        &copy; <?php echo date('Y'); ?> <strong>LocalServerPanel</strong> (LSP Universal) &mdash; Универсальная мобильная панель для Termux
    </footer>

</body>
</html>
