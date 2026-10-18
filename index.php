<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/queries.php';
require __DIR__.'/app/actions.php';
require __DIR__.'/app/views.php';

if ((int)sql('SELECT COUNT(*) FROM users')->fetchColumn()===0) {
    sql('INSERT IGNORE INTO users(username,full_name,password_hash,role,unit,is_active) VALUES(?,?,?,?,?,1)',
        ['adminofficer@gmail.com','System Administrator','$2y$10$00DvV5EUNcJafxvf74ogFeDC3fK00KZGKIVNL0mI4M5lvh3WlIDXm','admin','Admin']);
}
$page=(string)($_GET['page']??'dashboard');
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $task=(string)($_POST['task']??'');
    if ($task==='login') login_attempt();
    if ($task==='setup_admin') setup_admin();
    if ($task==='request_account') submit_account_request();
    require_login();
    refresh_user_session();
    if ($task==='logout') {
        verify_csrf();log_action('logout','users',(int)user()['id'],'User signed out');
        $_SESSION=[];session_regenerate_id(true);session_destroy();go('login');
    }
    if ($task==='save') save_record((string)($_POST['module']??''));
    if ($task==='archive') archive_record((string)($_POST['module']??''));
    if ($task==='routing_event') routing_event();
    if ($task==='save_user') save_user();
    if ($task==='review_account_request') review_account_request();
    http_response_code(400);exit('Unknown action');
}
if ($page==='request_account') {
    header_html('Request an Account'); account_request_page(); footer_html(); exit;
}
if ($page==='login') {
    if(user())go('dashboard');
    header_html('Sign in');login_page();footer_html();exit;
}
require_login();
// Refresh role, unit, and status for every request so RBAC changes apply promptly.
refresh_user_session();
if ($page==='export') {
    $module=(string)($_GET['module']??'');
    if (!isset($modules[$module])) {http_response_code(404);exit('Unknown report');}
    if (!module_allowed($module)) {http_response_code(403);exit('This report is not available to your unit.');}
    // CSV exports are bounded to avoid exhausting shared-host PHP memory.
    [$countQuery,$countParams]=filtered_query($module,$_GET,true);
    if ((int)sql($countQuery,$countParams)->fetchColumn()>25000) {
        http_response_code(413);exit('Too many results to export at once. Narrow your date or status filters.');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="jmris_'.$module.'_'.date('Ymd_His').'.csv"');
    $out=fopen('php://output','wb');
    fputcsv($out,array_map(fn($key)=>field_label($module,$key),$modules[$module]['columns']),',','"','');
    foreach(rows_for($module,$_GET,true) as $record) {
        $cells=[];
        foreach($modules[$module]['columns'] as $key) $cells[]=csv_safe(display_value($module,$key,$record[$key]??null));
        fputcsv($out,$cells,',','"','');
    }
    fclose($out);exit;
}
if (isset($modules[$page])) {
    if (!module_allowed($page)) {http_response_code(403);exit('This module is not available to your unit.');}
    $action=(string)($_GET['action']??'list');
    $id=max(0,(int)($_GET['id']??0));
    if (($action==='edit'||$action==='new') && !can_edit()) {http_response_code(403);exit('Read-only account');}
    header_html($modules[$page]['title'],$page);
    if ($action==='edit'||$action==='new') edit_page($page,$action==='new'?0:$id);
    elseif ($action==='view') details_page($page,$id);
    elseif ($action==='list') list_page($page);
    else not_found();
    footer_html();exit;
}
if ($page==='users' && !is_admin()) {http_response_code(403);exit('Access denied');}
header_html(match($page){'dashboard'=>'Dashboard','reports'=>'Reports','activity'=>'Recent Activity','search'=>'Global Search','users'=>'User Accounts',default=>'Page Not Found'},$page,isset($_GET['print']));
switch ($page) {
 case 'dashboard':dashboard_page();break;
 case 'reports':reports_page();break;
 case 'search':search_page();break;
 case 'activity':activity_page();break;
 case 'users':users_page();break;
 default:not_found();
}
footer_html();
