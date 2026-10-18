<?php
declare(strict_types=1);
const JMRIS_BOOTSTRAPPED = true;
$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store, private');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name($config['session_name']);
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
$modules = require __DIR__ . '/modules.php';

function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . $config['db_host'] . ';port=' . $config['db_port'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function sql(string $query, array $args=[]): PDOStatement {
    $stmt = db()->prepare($query); $stmt->execute($args); return $stmt;
}
function one(string $query, array $args=[]): ?array {
    $row = sql($query,$args)->fetch(); return $row === false ? null : $row;
}
function all(string $query, array $args=[]): array {return sql($query,$args)->fetchAll();}
function e(mixed $val): string {return htmlspecialchars((string)($val??''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');}
function link_to(string $page='dashboard', array $args=[]): string {return 'index.php?' . http_build_query(array_merge(['page'=>$page],$args));}
function go(string $page='dashboard', array $args=[]): never {header('Location: '.link_to($page,$args),true,303); exit;}
function flash(string $message, string $level='success'): void {$_SESSION['flash']=['message'=>$message,'level'=>$level];}
function show_flash(): void {
    if (empty($_SESSION['flash'])) return;
    $f=$_SESSION['flash']; unset($_SESSION['flash']);
    echo '<div role="alert" class="alert '.e($f['level']).'">'.e($f['message']).'</div>';
}
function user(): ?array {return $_SESSION['user']??null;}
function is_admin(): bool {return (user()['role']??'')==='admin';}
function can_edit(): bool {return in_array(user()['role']??'', ['admin','records_officer'],true);}
function allowed_unit_modules(): array {
    if (is_admin() || (user()['unit']??'')==='Admin') return array_keys($GLOBALS['modules']);
    return match (user()['unit']??'') {
      'Records Unit'=>['inmates','cases','documents'],
      'Security and Custody Unit'=>['custody_events','incidents','inmates'],
      'Case Management Unit'=>['cases','incidents','inmates'],
      'Visitors and Reception Unit'=>['visitors','visits','inmates'],
      default=>[],
    };
}
function module_allowed(string $module): bool {return in_array($module,allowed_unit_modules(),true);}function require_login(): void {if (!user()) go('login');}
function require_admin(): void {if (!is_admin()) {http_response_code(403); exit('Access denied.');}}function refresh_user_session(): void {
    if (!user()) return;
    $fresh=one('SELECT id,full_name,username,role,unit,is_active FROM users WHERE id=?',[user()['id']]);
    if (!$fresh || !$fresh['is_active']) {$_SESSION=[];session_destroy();go('login');}
    $_SESSION['user']=['id'=>(int)$fresh['id'],'username'=>$fresh['username'],'full_name'=>$fresh['full_name'],'role'=>$fresh['role'],'unit'=>$fresh['unit']];
}
function csrf_token(): string {if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf'];}
function csrf_input(): string {return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">';}
function verify_csrf(): void {if (!isset($_POST['csrf']) || !hash_equals(csrf_token(),(string)$_POST['csrf'])) {http_response_code(419); exit('Invalid or expired form token. Go back, refresh and retry.');}}
function log_action(string $action, string $module, ?int $id, string $message): void {
    $ip = $_SERVER['REMOTE_ADDR']??null;
    sql('INSERT INTO activity_logs(actor_id,action,module,record_id,message,ip_address) VALUES(?,?,?,?,?,?)',
       [user()['id']??null,$action,$module,$id,$message,$ip]);
}
function allowed_doc_classifications(): array {
    return match (user()['role']??'viewer') {
      'admin'=>['Public','Internal','Confidential','Restricted'],
      'records_officer'=>['Public','Internal','Confidential'],
      default=>['Public','Internal'],
    };
}
function classification_accessible(int $documentId): bool {
    $doc=one('SELECT classification FROM documents WHERE id=? AND archived_at IS NULL',[$documentId]);
    return $doc!==null && in_array($doc['classification'],allowed_doc_classifications(),true);
}
function record_accessible(string $module,array $record): bool {
    if ($module==='documents') return in_array($record['classification'],allowed_doc_classifications(),true);
    if ($module==='routing_sheets') return classification_accessible((int)$record['document_id']);
    return true;
}
function records_constraint(string $module, string $alias='t'): array {
    $cond="$alias.archived_at IS NULL"; $params=[];
    if ($module==='documents') {
       $vals=allowed_doc_classifications();
       $cond.=' AND '.$alias.'.classification IN ('.implode(',',array_fill(0,count($vals),'?')).')';
       $params=array_merge($params,$vals);
    } elseif ($module==='routing_sheets') {
       $vals=allowed_doc_classifications();
       $cond.=' AND EXISTS (SELECT 1 FROM documents cd WHERE cd.id='.$alias.'.document_id AND cd.archived_at IS NULL AND cd.classification IN ('.implode(',',array_fill(0,count($vals),'?')).'))';
       $params=array_merge($params,$vals);
    }
    return [$cond,$params];
}
function record_row(string $module,int $id): ?array {
    global $modules;
    if (!isset($modules[$module]) || $id<1) return null;
    [$cond,$params]=records_constraint($module);
    return one('SELECT t.* FROM '.$modules[$module]['table'].' t WHERE t.id=? AND '.$cond,array_merge([$id],$params));
}
function human(string $str): string {return ucwords(str_replace('_',' ',$str));}
function field_label(string $module,string $key): string {
    global $modules;
    return $modules[$module]['fields'][$key]['label']??human($key);
}
function reference_options(string $module): array {
    global $modules;
    $cfg=$modules[$module];
    [$cond,$params]=records_constraint($module);
    $items=all('SELECT t.* FROM '.$cfg['table'].' t WHERE '.$cond.' ORDER BY t.id DESC LIMIT 1000',$params);
    $labels=[];
    foreach ($items as $item) {
        $label=$item[$cfg['number']];
        if ($module==='inmates'||$module==='personnel') $label.=' — '.$item['last_name'].', '.$item['first_name'];
        if ($module==='visitors') $label.=' — '.$item['full_name'];
        if ($module==='documents') $label.=' — '.$item['subject'];
        $labels[(int)$item['id']]=$label;
    }
    return $labels;
}
function reference_label(string $module,int $id): string {
    static $cache=[];
    if (!isset($cache[$module])) $cache[$module]=reference_options($module);
    return $cache[$module][$id]??'[Unavailable or restricted]';
}
function display_value(string $module,string $key,mixed $value): string {
    global $modules;
    if ($value===null || $value==='') return '—';
    $field=$modules[$module]['fields'][$key]??[];
    if (($field['type']??'')==='reference') return reference_label($field['ref'],(int)$value);
    if ($key==='created_at' || ($field['type']??'')==='datetime-local') return date('M j, Y g:i A',strtotime((string)$value));
    if (($field['type']??'')==='date') return date('M j, Y',strtotime((string)$value));
    return (string)$value;
}
function csv_safe(mixed $val): string {
    $s=(string)($val??'');
    // Prevent CSV spreadsheet formula injection when opening exports in Excel.
    return preg_match('/^[\s\x00-\x1f]*[=+\-@]/u',$s) ? "'".$s : $s;
}
