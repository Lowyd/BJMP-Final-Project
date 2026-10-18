<?php
if (!defined('JMRIS_BOOTSTRAPPED')) exit;
function header_html(string $title,string $current='',bool $print=false): void {
    $me=user();global $config;
    $app=e($config['app_name']);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>'.e($title).' · '.$app.'</title><link rel="stylesheet" href="assets/style.css?v=20261018"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">';
    echo '<script src="assets/app.js" defer></script></head><body'.($print?' class="print-layout"':'').'>';
    if (!$me) return;
    echo '<div class="app-shell"><aside class="sidebar no-print"><a class="brand" href="'.e(link_to()).'"><span class="brand-icon"><img src="assets/bjmp-logo.png" alt="BJMP logo"></span><span><b>BJMP</b><small>Records Information System</small></span></a>';
    echo '<div class="sidebar-label">OVERVIEW</div>';
    nav_item('dashboard','Dashboard','fa-table-columns',$current);
    echo '<div class="sidebar-label">RECORDS</div>';
    foreach (['inmates'=>['Inmates','fa-person'],'personnel'=>['Personnel','fa-users'],'visitors'=>['Visitors','fa-id-card'],'visits'=>['Visitor Visits','fa-calendar-check'],'incidents'=>['Incidents / Cases','fa-triangle-exclamation'],'cases'=>['Cases & Court','fa-gavel'],'custody_events'=>['Custody Events','fa-arrows-rotate'],'documents'=>['Documents','fa-file-lines'],'routing_sheets'=>['Routing Sheets','fa-route']] as $navPage=>$navMeta) {
        if (module_allowed($navPage)) nav_item($navPage,$navMeta[0],$navMeta[1],$current);
    }
    echo '<div class="sidebar-label">INSIGHTS & ADMIN</div>';
    nav_item('reports','Reports & Print','fa-chart-column',$current);
    nav_item('search','Global Search','fa-magnifying-glass',$current);
    nav_item('activity','Recent Activity','fa-clock-rotate-left',$current);
    if (is_admin()) nav_item('users','User Accounts','fa-user-gear',$current);
    echo '<div class="sidebar-bottom"><div class="user-circle">'.e(mb_strtoupper(mb_substr($me['full_name'],0,1))).'</div><div><b>'.e($me['full_name']).'</b><small>'.e(human($me['role'])).'</small></div></div></aside>';
    echo '<div class="main-pane"><header class="topbar no-print"><button class="mobile-menu" id="menu-toggle" aria-label="Toggle menu"><i class="fa-solid fa-bars" aria-hidden="true"></i></button><span class="top-title">'.$app.' / '.e($title).'</span><span class="top-actions"><span class="role-tag">'.e(str_replace('_',' ',ucfirst($me['role']))).'</span><form method="post" action="index.php">'.csrf_input().'<input type="hidden" name="task" value="logout"><button class="btn-link" type="submit"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sign out</button></form></span></header><main class="content">';
}
function nav_item(string $page,string $label,string $icon,string $current): void {
    echo '<a class="nav-item '.($current===$page?'active':'').'" href="'.e(link_to($page)).'"><span class="nav-icon"><i class="fa-solid '.e($icon).'" aria-hidden="true"></i></span>'.e($label).'</a>';
}
function footer_html(): void {
    if (user()) echo '</main><footer class="site-footer no-print">BJMP · Authorized personnel only · All access is logged.</footer></div></div>';
    echo '</body></html>';
}
function page_title(string $title,string $subtitle='',string $buttons=''): void {
    echo '<div class="page-head"><div><h1>'.e($title).'</h1>'.($subtitle?'<p>'.e($subtitle).'</p>':'').'</div><div class="head-actions no-print">'.$buttons.'</div></div>';
}
function link_button(string $url,string $text,string $style='primary'): string {
    return '<a class="btn btn-'.e($style).'" href="'.e($url).'">'.e($text).'</a>';
}
function badge(string $status): string {
    $low=strtolower($status);
    $tone=in_array($low,['active','approved','completed','resolved','received','public'],true)?'green':(in_array($low,['released','cancelled','denied','closed','inactive','separated'],true)?'gray':(in_array($low,['open','pending','under review','in transit','restricted'],true)?'amber':'blue'));
    return '<span class="status '.$tone.'">'.e($status).'</span>';
}
function filters_html(string $module,string $base=''): void {
    global $modules;
    $cfg=$modules[$module];$base=$base?:$module;
    echo '<form class="filter-bar no-print" method="get" action="index.php"><input type="hidden" name="page" value="'.e($base).'">';
    if ($base==='reports') echo '<input type="hidden" name="module" value="'.e($module).'">';
    echo '<input class="input" type="search" name="q" value="'.e($_GET['q']??'').'" placeholder="Search ID, name, or keyword" aria-label="Search">';
    if ($cfg['status']) {
      echo '<select class="input" name="status" aria-label="Filter by status"><option value="">All statuses</option>';
      foreach ($cfg['fields'][$cfg['status']]['options'] as $opt) echo '<option value="'.e($opt).'"'.(($_GET['status']??'')===$opt?' selected':'').'>'.e($opt).'</option>';
      echo '</select>';
    }
    if ($cfg['date']) {
      echo '<label class="datefilter">From <input class="input" type="date" name="from" value="'.e($_GET['from']??'').'"></label>';
      echo '<label class="datefilter">To <input class="input" type="date" name="to" value="'.e($_GET['to']??'').'"></label>';
    }
    echo '<button class="btn btn-secondary" type="submit">Apply</button><a class="btn btn-plain" href="'.e(link_to($base,$base==='reports'?['module'=>$module]:[])).'">Reset</a></form>';
}
function record_table(string $module,array $rows,bool $report=false): void {
    global $modules;
    $cfg=$modules[$module];
    echo '<div class="table-scroll"><table><thead><tr>';
    foreach ($cfg['columns'] as $key) echo '<th>'.e(field_label($module,$key)).'</th>';
    if (!$report) echo '<th class="no-print">Actions</th>';
    echo '</tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="'.(count($cfg['columns'])+($report?0:1)).'" class="empty">No matching records found.</td></tr>';
    foreach ($rows as $row) {
      echo '<tr>';
      foreach ($cfg['columns'] as $key) {
        $val=display_value($module,$key,$row[$key]??null);
        echo '<td>'.(($key==='status'||$key==='classification')?badge($val):e($val)).'</td>';
      }
      if (!$report) {
        echo '<td class="no-print actions"><a href="'.e(link_to($module,['action'=>'view','id'=>$row['id']])).'">View</a>';
        if (can_edit()) echo '<a href="'.e(link_to($module,['action'=>'edit','id'=>$row['id']])).'">Edit</a>';
        echo '</td>';
      }
      echo '</tr>';
    }
    echo '</tbody></table></div>';
}
function pagination_html(string $module,int $total,int $page): void {
    $pages=(int)ceil($total/20);
    if ($pages<=1) return;
    $vars=$_GET; $vars['page']=$module;
    echo '<nav class="pagination no-print" aria-label="Pages"><span>'.e((string)$total).' results · Page '.e((string)$page).' of '.e((string)$pages).'</span>';
    if ($page>1) {$vars['p']=$page-1; echo link_button('index.php?'.http_build_query($vars),'Previous','secondary');}
    if ($page<$pages) {$vars['p']=$page+1; echo link_button('index.php?'.http_build_query($vars),'Next','secondary');}
    echo '</nav>';
}
function list_page(string $module): void {
    global $modules;
    $cfg=$modules[$module];
    $add=can_edit()?link_button(link_to($module,['action'=>'new']),'+ Add '.$cfg['singular']):'';
    page_title($cfg['title'],'Search, manage, and track records.',$add);
    show_flash();filters_html($module);
    $total=total_for($module,$_GET);$p=max(1,(int)($_GET['p']??1));
    echo '<div class="section-heading"><h2>'.number_format($total).' records</h2><span>20 records per page</span></div>';
    echo '<section class="panel">';record_table($module,rows_for($module,$_GET));echo '</section>';
    pagination_html($module,$total,$p);
}
function form_field(string $key,array $field,mixed $value=null): void {
    $type=$field['type']??'text';$label=$field['label'];
    $required=!empty($field['required']);
    echo '<div class="field"><label for="fld_'.e($key).'">'.e($label).($required?' <span class="required">*</span>':'').'</label>';
    $id='fld_'.$key;
    $attrs=' id="'.e($id).'" name="'.e($key).'" '.($required?'required ':'');
    if ($type==='textarea') {
        echo '<textarea class="input" rows="4" '.$attrs.'>'.e($value).'</textarea>';
    } elseif ($type==='select' || $type==='reference') {
        echo '<select class="input" '.$attrs.'><option value="" disabled'.(($value===null || $value==='')?' selected':'').' hidden>— Select —</option>';
        if ($type==='select') {
          $options=$field['options'];
          if (array_is_list($options)) $options=array_combine($options,$options);
        } else $options=reference_options($field['ref']);
        foreach ($options as $optionValue=>$optionLabel) {
          echo '<option value="'.e($optionValue).'"'.((string)$value===(string)$optionValue?' selected':'').'>'.e($optionLabel).'</option>';
        }
        echo '</select>';
        if ($type==='reference') echo '<small>Missing option? First create it under '.e(human($field['ref'])).'.</small>';
    } else {
        if ($type==='datetime-local' && $value) $value=date('Y-m-d\TH:i',strtotime((string)$value));
        echo '<input class="input" type="'.e($type).'" '.$attrs.' value="'.e($value).'"'.($type==='text'?' maxlength="255"':'').'>';
    }
    echo '</div>';
}
function edit_page(string $module,int $id): void {
    global $modules;
    $cfg=$modules[$module];$record=$id?record_row($module,$id):null;
    if ($id && !$record) {not_found();return;}
    $title=($id?'Edit ':'Add ').$cfg['singular'];
    page_title($title,'Fields marked * are required.',link_button(link_to($module),'Back','secondary'));
    show_flash();
    echo '<section class="panel narrow"><form method="post" action="index.php" class="record-form">'.csrf_input();
    echo '<input type="hidden" name="task" value="save"><input type="hidden" name="module" value="'.e($module).'"><input type="hidden" name="id" value="'.e($id).'">';
    echo '<div class="form-grid">';
    foreach($cfg['fields'] as $key=>$field) {
        if ($module==='routing_sheets' && $key==='status') {
            if ($record) continue;
            echo '<div class="field"><label>Status</label><p class="hint">New routing sheets start as Draft. Use Routing Activity to move them.</p></div>';
            continue;
        }
        if ($module==='documents' && $key==='classification') $field['options']=allowed_doc_classifications();
        $default = $record[$key]??($key==='status'?($field['options'][0]??null):null);
        if (!$record && ($field['type']??'')==='date' && $key!=='birth_date' && $key!=='status_date') $default=date('Y-m-d');
        if (!$record && ($field['type']??'')==='datetime-local') $default=date('Y-m-d H:i:s');
        form_field($key,$field,$default);
    }
    echo '</div><div class="form-actions"><button type="submit" class="btn btn-primary">Save '.$cfg['singular'].'</button>'.link_button(link_to($module),'Cancel','secondary').'</div></form></section>';
}
function details_page(string $module,int $id): void {
    global $modules;
    $cfg=$modules[$module];$record=record_row($module,$id);
    if (!$record) {not_found();return;}
    $buttons=link_button(link_to($module),'Back','secondary');
    if (can_edit()) $buttons.=' '.link_button(link_to($module,['action'=>'edit','id'=>$id]),'Edit','primary');
    $buttons.=' <button type="button" class="btn btn-secondary" onclick="window.print()">Print record</button>';
    page_title($cfg['singular'].' Details',$record[$cfg['number']],$buttons);
    show_flash();
    echo '<section class="panel details"><div class="detail-grid">';
    foreach($cfg['fields'] as $key=>$field) {
        $val=display_value($module,$key,$record[$key]??null);
        echo '<div class="detail-cell"><span>'.e($field['label']).'</span><div>'.(($key==='status'||$key==='classification')?badge($val):nl2br(e($val))).'</div></div>';
    }
    echo '</div><div class="meta">Created: '.e($record['created_at']).' · Last updated: '.e($record['updated_at']).'</div></section>';
    if ($module==='routing_sheets') routing_history_html($id,$record);
    if ($module==='inmates') {
        $vis=(int)sql('SELECT COUNT(*) FROM visits WHERE inmate_id=? AND archived_at IS NULL',[$id])->fetchColumn();
        $cases=(int)sql('SELECT COUNT(*) FROM incidents WHERE inmate_id=? AND archived_at IS NULL',[$id])->fetchColumn();
        echo '<section class="panel associated"><b>Associated records</b><p>'.e($vis).' visits · '.e($cases).' incidents. Related records are retained when an inmate is archived.</p></section>';
    }
    if (is_admin()) {
        echo '<section class="danger-zone no-print"><h3>Archive record</h3><p>Removes the record from normal lists but retains it for audit and linked history.</p>';
        echo '<form method="post" action="index.php" data-confirm="Archive this record? It will no longer appear in regular lists.">'.csrf_input().'<input type="hidden" name="task" value="archive"><input type="hidden" name="module" value="'.e($module).'"><input type="hidden" name="id" value="'.e($id).'"><button type="submit" class="btn btn-danger">Archive '.$cfg['singular'].'</button></form></section>';
    }
}
function routing_history_html(int $id,array $record): void {
    $history=all('SELECT h.*,u.full_name actor_name FROM routing_history h LEFT JOIN users u ON u.id=h.actor_id WHERE h.routing_sheet_id=? ORDER BY h.id DESC',[$id]);
    echo '<section class="panel spaced"><h2>Routing History</h2><div class="timeline">';
    if (!$history) echo '<p class="muted">No activity yet.</p>';
    foreach($history as $event) {
        echo '<div class="timeline-entry"><div class="timeline-mark"></div><div><strong>'.e($event['action']).'</strong><span>'.e($event['acted_at']).' · '.e($event['actor_name']??'Former user').'</span>';
        echo '<p>'.e(($event['from_unit']??'').' to '.($event['to_unit']??'')).'</p>';
        if ($event['note']) echo '<p>'.nl2br(e($event['note'])).'</p>';
        echo '</div></div>';
    }
    echo '</div></section>';
    if (!can_edit()) return;
    echo '<section class="panel spaced no-print"><h2>Record Routing Activity</h2><form method="post" action="index.php" class="record-form">'.csrf_input();
    echo '<input type="hidden" name="task" value="routing_event"><input type="hidden" name="id" value="'.e($id).'">';
    echo '<div class="form-grid"><div class="field"><label>Action *</label><select name="routing_action" class="input" required><option>Forwarded</option><option>Received</option><option>Completed</option><option>Note</option></select></div>';
    echo '<div class="field"><label>From Unit</label><input name="from_unit" class="input" maxlength="150" value="'.e($record['origin_unit']).'"></div>';
    echo '<div class="field"><label>To Unit</label><input name="to_unit" class="input" maxlength="150" value="'.e($record['destination_unit']).'"></div>';
    echo '<div class="field full"><label>Notes</label><textarea name="note" class="input" rows="3"></textarea></div></div>';
    echo '<div class="form-actions"><button class="btn btn-primary" type="submit">Save routing activity</button></div></form></section>';
}
function not_found(): void {http_response_code(404);page_title('Record not found','The record is missing, archived, or you do not have permission to view it.');}
function dashboard_page(): void {
    global $modules;
    page_title('Dashboard','Operational overview and latest record activity',(can_edit() && module_allowed('inmates'))?link_button(link_to('inmates',['action'=>'new']),'+ New Inmate'):null);
    show_flash();
    $cards=[
      ['inmates','Total Inmates','SELECT COUNT(*) FROM inmates WHERE archived_at IS NULL'],
      ['personnel','Active Personnel',"SELECT COUNT(*) FROM personnel WHERE archived_at IS NULL AND status='Active'"],
      ['visits',"Today's Visits",'SELECT COUNT(*) FROM visits WHERE archived_at IS NULL AND DATE(visit_at)=CURDATE()'],
      ['incidents','Open Incidents',"SELECT COUNT(*) FROM incidents WHERE archived_at IS NULL AND status IN ('Open','Under Review')"],
      ['documents','Documents','SELECT COUNT(*) FROM documents WHERE archived_at IS NULL'],
      ['routing_sheets','Active Routing',"SELECT COUNT(*) FROM routing_sheets WHERE archived_at IS NULL AND status IN ('Draft','In Transit','Received')"],
    ];
    echo '<div class="metrics">';
    foreach($cards as [$mod,$label,$query]) {
        if (!module_allowed($mod)) continue;
        if ($mod==='documents') {$allowed=allowed_doc_classifications();$query='SELECT COUNT(*) FROM documents WHERE archived_at IS NULL AND classification IN ('.implode(',',array_fill(0,count($allowed),'?')).')';$count=(int)sql($query,$allowed)->fetchColumn();}
        elseif ($mod==='routing_sheets') {[$cond,$p]=records_constraint('routing_sheets');$count=(int)sql('SELECT COUNT(*) FROM routing_sheets t WHERE '.$cond." AND status IN ('Draft','In Transit','Received')",$p)->fetchColumn();}
        else $count=(int)db()->query($query)->fetchColumn();
        echo '<a class="metric" href="'.e(link_to($mod)).'"><span>'.e($label).'</span><strong>'.number_format($count).'</strong><small>View records</small></a>';
    }
    echo '</div><div class="dashboard-grid"><section class="panel"><div class="section-heading"><h2>Recent Activity</h2><a href="'.e(link_to('activity')).'">View all</a></div>';
    activity_items(8);
    echo '</section><section class="panel"><h2>Quick Actions</h2><div class="quick-list">';
    foreach(['inmates'=>'Register inmate','visitors'=>'Register visitor','visits'=>'Record visit','incidents'=>'Record incident','documents'=>'Register document','routing_sheets'=>'Create routing sheet'] as $module=>$label) {
        if (can_edit() && module_allowed($module)) echo '<a href="'.e(link_to($module,['action'=>'new'])).'">'.e($label).'</a>';
    }
    if (!can_edit()) echo '<p class="muted">Your account is read-only.</p>';
    echo '</div></section></div>';
}
function activity_items(int $limit=100, array $filters=[], int $offset=0): void {
    $limit=max(1,min(500,$limit)); $offset=max(0,$offset); $where=[]; $params=[];
    $term=trim((string)($filters['q']??''));
    if ($term!=='') { $where[]='(a.message LIKE ? OR a.module LIKE ? OR u.full_name LIKE ?)'; $params[]='%'.$term.'%'; $params[]='%'.$term.'%'; $params[]='%'.$term.'%'; }
    $action=(string)($filters['action']??'');
    $actions=['login','logout','created','updated','archived','routed','saved'];
    if (in_array($action,$actions,true)) {$where[]='a.action=?';$params[]=$action;}
    $condition=$where?' WHERE '.implode(' AND ',$where):'';
    $rows=all('SELECT a.*,u.full_name actor_name FROM activity_logs a LEFT JOIN users u ON u.id=a.actor_id'.$condition.' ORDER BY a.id DESC LIMIT '.$limit.' OFFSET '.$offset,$params);
    if (!$rows) {echo '<div class="empty">No recent activity. New transactions will appear here.</div>';return;}
    echo '<div class="activity-list">';
    foreach($rows as $r) {
      echo '<div class="activity-entry"><span class="activity-dot"></span><div><strong>'.e($r['message']).'</strong><p>'.e($r['actor_name']??'System').' · '.e(human($r['module'])).'</p></div><time>'.e(date('M j, g:i A',strtotime($r['created_at']))).'</time></div>';
    }
    echo '</div>';
}
function activity_page(): void {
    $filters=['q'=>(string)($_GET['q']??''),'action'=>(string)($_GET['action']??'')];
    $where=[];$params=[];$term=trim($filters['q']);
    if ($term!=='') {$where[]='(a.message LIKE ? OR a.module LIKE ? OR u.full_name LIKE ?)';$params[]='%'.$term.'%';$params[]='%'.$term.'%';$params[]='%'.$term.'%';}
    $actions=['login','logout','created','updated','archived','routed','saved'];
    if (in_array($filters['action'],$actions,true)) {$where[]='a.action=?';$params[]=$filters['action'];}
    $condition=$where?' WHERE '.implode(' AND ',$where):'';
    $total=(int)sql('SELECT COUNT(*) FROM activity_logs a LEFT JOIN users u ON u.id=a.actor_id'.$condition,$params)->fetchColumn();
    $page=max(1,(int)($_GET['p']??1));$perPage=10;$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);
    page_title('Recent Activity','Audit trail of sign-ins and record changes.');
    echo '<form class="filter-bar no-print" method="get" action="index.php"><input type="hidden" name="page" value="activity"><input class="input" type="search" name="q" value="'.e($filters['q']).'" placeholder="Search activity, module, or staff name"><select class="input" name="action"><option value="">All actions</option>';
    foreach($actions as $option) echo '<option value="'.e($option).'"'.($filters['action']===$option?' selected':'').'>'.e(ucfirst($option)).'</option>';
    echo '</select><button class="btn btn-secondary">Apply</button><a class="btn btn-plain" href="'.e(link_to('activity')).'">Reset</a></form>';
    echo '<section class="panel">';activity_items($perPage,$filters,($page-1)*$perPage);echo '</section>';
    if ($pages>1) {echo '<nav class="pagination no-print" aria-label="Activity pages"><span>'.number_format($total).' results · Page '.e((string)$page).' of '.e((string)$pages).'</span>'; $base=['page'=>'activity','q'=>$filters['q'],'action'=>$filters['action']]; if($page>1){$base['p']=$page-1;echo link_button('index.php?'.http_build_query($base),'Previous','secondary');} if($page<$pages){$base['p']=$page+1;echo link_button('index.php?'.http_build_query($base),'Next','secondary');} echo '</nav>';}
}
function reports_page(): void {
    global $modules;
    $supported=array_values(array_filter(['inmates','personnel','visitors','visits','incidents','cases','custody_events','documents','routing_sheets'], 'module_allowed'));
    $module=(string)($_GET['module']??'inmates');
    if (!in_array($module,$supported,true)) $module='inmates';
    $opts='';foreach($supported as $key) $opts.='<option value="'.e($key).'"'.($module===$key?' selected':'').'>'.e($modules[$key]['title']).'</option>';
    $filters=$_GET;unset($filters['page'],$filters['module'],$filters['print']);
    $printUrl=link_to('reports',array_merge(['module'=>$module],$filters,['print'=>'1']));
    $csvUrl=link_to('export',array_merge(['module'=>$module],$filters));
    $buttons=link_button($printUrl,'Print report','secondary').' '.link_button($csvUrl,'Export CSV','primary');
    page_title('Reports & Print','Choose a report, apply filters, then print or export.',$buttons);
    echo '<form method="get" action="index.php" class="report-chooser no-print"><input type="hidden" name="page" value="reports"><label>Report Type</label><select class="input" name="module" onchange="this.form.submit()">'.$opts.'</select><button class="btn btn-secondary">Choose</button></form>';
    filters_html($module,'reports');
    $count=total_for($module,$_GET);
    $printing=isset($_GET['print']) && $_GET['print']==='1';
    $rows=rows_for($module,$_GET,true);
    echo '<section class="panel"><div class="report-heading"><div><h2>'.e($modules[$module]['title']).' Report</h2><p>Generated '.e(date('F j, Y · g:i A')).' · '.number_format($count).' matching records</p></div>';
    if($printing) echo '<button class="btn btn-secondary no-print" onclick="window.print()">Print now</button>';
    echo '</div>';record_table($module,$rows,true);echo '</section>';
    if($printing) echo '<script>window.addEventListener("load",function(){window.print()})</script>';
}
function search_page(): void {
    global $modules;
    $term=trim((string)($_GET['q']??''));
    page_title('Global Search','Search control numbers, names, subjects, and incident descriptions across modules.');
    echo '<form class="global-search no-print" method="get" action="index.php"><input type="hidden" name="page" value="search"><input type="search" class="input" name="q" maxlength="150" value="'.e($term).'" placeholder="Enter ID, control number, name, or keyword" required><button class="btn btn-primary">Search</button></form>';
    if (!$term) {echo '<div class="panel empty">Enter a keyword to search all modules.</div>';return;}
    foreach($modules as $key=>$cfg) {
        if (!module_allowed($key)) continue;
        $rows=rows_for($key,['q'=>$term,'p'=>1]);
        if (!$rows) continue;
        echo '<section class="panel search-result"><div class="section-heading"><h2>'.e($cfg['title']).'</h2><a href="'.e(link_to($key,['q'=>$term])).'">View more</a></div>';
        record_table($key,array_slice($rows,0,5));echo '</section>';
    }
}
function users_page(): void {
    require_admin();
    $action=(string)($_GET['action']??'list');$id=(int)($_GET['id']??0);
    if ($action==='new'||$action==='edit') {
        $account=$id?one('SELECT id,username,full_name,role,unit,is_active FROM users WHERE id=?',[$id]):null;
        if ($id && !$account) {not_found();return;}
        page_title($id?'Edit User':'Add User','Accounts are role-based and require individual credentials.',link_button(link_to('users'),'Back','secondary'));
        show_flash();
        echo '<section class="panel narrow"><form method="post" action="index.php" class="record-form">'.csrf_input().'<input type="hidden" name="task" value="save_user"><input type="hidden" name="id" value="'.e($id).'"><div class="form-grid">';
        form_field('full_name',['label'=>'Full Name','required'=>true],$account['full_name']??'');
        form_field('username',['label'=>'Username (3–60 characters)','required'=>true],$account['username']??'');
        echo '<div class="field"><label for="account_password">'.($id?'New Password (leave empty to keep current)':'Password (12+ characters) *').'</label><input class="input" id="account_password" name="password" type="password" autocomplete="new-password" '.($id?'':'required').' minlength="12"></div>';
        form_field('role',['label'=>'Role','type'=>'select','options'=>['admin'=>'Administrator','records_officer'=>'Records Officer','viewer'=>'Viewer'],'required'=>true],$account['role']??'');
        form_field('unit',['label'=>'Unit','type'=>'select','options'=>['Admin','Records Unit','Security and Custody Unit','Case Management Unit','Visitors and Reception Unit'],'required'=>true],$account['unit']??'');
        echo '<div class="field"><label class="checkbox"><input type="checkbox" name="is_active" value="1"'.((!$account || $account['is_active'])?' checked':'').'> Account active</label></div></div>';
        echo '<div class="form-actions"><button class="btn btn-primary">Save User</button>'.link_button(link_to('users'),'Cancel','secondary').'</div></form></section>';
        return;
    }
    page_title('User Accounts','Admins manage staff access and passwords.',link_button(link_to('users',['action'=>'new']),'+ New User'));
    show_flash();
    echo '<section class="panel"><div class="table-scroll"><table><thead><tr><th>Username</th><th>Full name</th><th>Role</th><th>Unit</th><th>Status</th><th>Last sign-in</th><th>Action</th></tr></thead><tbody>';
    foreach(all('SELECT id,username,full_name,role,unit,is_active,last_login_at FROM users ORDER BY id DESC') as $account) {
        echo '<tr><td>'.e($account['username']).'</td><td>'.e($account['full_name']).'</td><td>'.e(human($account['role'])).'</td><td>'.e($account['unit']).'</td><td>'.badge($account['is_active']?'Active':'Inactive').'</td><td>'.e($account['last_login_at']??'Never').'</td><td><a href="'.e(link_to('users',['action'=>'edit','id'=>$account['id']])).'">Edit</a></td></tr>';
    }
    echo '</tbody></table></div></section>';
    echo '<section class="panel spaced"><h2>Pending Account Requests</h2><p>Review identity and role requests before creating access.</p><div class="table-scroll"><table><thead><tr><th>Applicant</th><th>Employee No.</th><th>Department</th><th>Role</th><th>Decision</th></tr></thead><tbody>';
    foreach(all("SELECT * FROM account_requests WHERE status='Pending' ORDER BY created_at ASC") as $request) {
        echo '<tr><td>'.e($request['full_name']).'<br>'.e($request['contact_email']).'</td><td>'.e($request['employee_no']).'</td><td>'.e($request['department']).'</td><td>'.e(human($request['requested_role'])).'</td><td><form method="post" action="index.php">'.csrf_input().'<input type="hidden" name="task" value="review_account_request"><input type="hidden" name="request_id" value="'.e($request['id']).'"><input name="username" placeholder="Username" required><button name="decision" value="approve">Approve</button><button name="decision" value="reject">Reject</button></form></td></tr>';
    }
    echo '</tbody></table></div></section>';
}
function account_request_page(): void {
    echo '<div class="login-layout"><div class="login-brand"><div class="login-logo"><img src="assets/bjmp-logo.png" alt="BJMP logo"></div><h1>BJMP</h1><p>Controlled staff access request</p><span>REQUEST · REVIEW · APPROVAL</span></div><section class="login-card"><h2>Request an Account</h2><p>Submit your work details. An administrator must approve your request before access is created.</p>';
    show_flash();
    echo '<form method="post" action="index.php" class="record-form">'.csrf_input().'<input type="hidden" name="task" value="request_account"><div class="field"><label>Full Name *</label><input class="input" name="full_name" maxlength="150" required></div><div class="field"><label>Employee / Staff Number *</label><input class="input" name="employee_no" maxlength="60" required></div><div class="field"><label>Department / Unit *</label><select class="input" name="department" required><option value="">Select a unit</option><option>Records Unit</option><option>Security and Custody Unit</option><option>Case Management Unit</option><option>Visitors and Reception Unit</option></select></div><div class="field"><label>Official Work Email *</label><input class="input" type="email" name="contact_email" maxlength="190" required></div><div class="field"><label>Requested Access *</label><select class="input" name="requested_role" required><option value="viewer">Viewer — read-only reports</option><option value="records_officer">Records Officer — add and edit records</option></select></div><div class="field"><label>Preferred Password (12+ characters) *</label><input class="input" type="password" name="password" minlength="12" autocomplete="new-password" required></div><div class="field"><label>Confirm Preferred Password *</label><input class="input" type="password" name="password_confirm" minlength="12" autocomplete="new-password" required></div><div class="field"><label>Reason for access *</label><textarea class="input" name="justification" rows="4" minlength="10" maxlength="1000" required></textarea></div><button class="btn btn-primary full-btn">Submit Request</button></form><div class="login-help"><a href="index.php?page=login">Back to sign in</a></div></section></div>';
}
function setup_page(): void {
    echo '<div class="login-layout"><div class="login-brand"><div class="login-logo"><img src="assets/bjmp-logo.png" alt="BJMP logo"></div><h1>BJMP</h1><p>Initial administrator setup</p><span>ONE-TIME SYSTEM SETUP</span></div><section class="login-card"><h2>Create Administrator</h2><p>Create the first administrator account. This page closes automatically after the first account is created.</p>';
    show_flash();
    echo '<form method="post" action="index.php" class="record-form">'.csrf_input().'<input type="hidden" name="task" value="setup_admin"><div class="field"><label>Username *</label><input class="input" name="username" maxlength="60" placeholder="adminofficer@gmail.com" required></div><div class="field"><label>Full Name *</label><input class="input" name="full_name" maxlength="150" required></div><div class="field"><label>Password (12+ characters) *</label><input class="input" type="password" name="password" minlength="12" required></div><button class="btn btn-primary full-btn">Create Administrator</button></form></section></div>';
}
function login_page(): void {
    echo '<div class="login-layout"><div class="login-brand"><div class="login-logo"><img src="assets/bjmp-logo.png" alt="BJMP logo"></div><h1>BJMP</h1><p>Bureau of Jail Management and Penology, Balingasag District</p><span>SECURE RECORDS · CONTROLLED ACCESS</span></div><section class="login-card"><h2>Sign in</h2><p>Enter your authorized staff credentials.</p>';
    show_flash();
    echo '<form method="post" action="index.php" class="record-form">'.csrf_input().'<input type="hidden" name="task" value="login"><div class="field"><label>Username</label><input class="input" name="username" autocomplete="username" required autofocus maxlength="60"></div><div class="field"><label>Password</label><input class="input" type="password" name="password" autocomplete="current-password" required></div><button class="btn btn-primary full-btn">Sign in</button></form><div class="login-help"><a href="index.php?page=request_account">Request an account</a></div></section></div>';
}
