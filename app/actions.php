<?php
if (!defined('JMRIS_BOOTSTRAPPED')) exit;
function login_attempt(): never {
    verify_csrf();
    $username=trim((string)($_POST['username']??''));
    $password=(string)($_POST['password']??'');
    $row=one('SELECT * FROM users WHERE username=?',[$username]);
    $bad = !$row || !$row['is_active'] || ($row['locked_until'] && strtotime($row['locked_until'])>time()) || !password_verify($password,$row['password_hash']);
    if ($bad) {
        if ($row && $row['is_active'] && (!$row['locked_until'] || strtotime($row['locked_until'])<=time())) {
            $fails=(int)$row['failed_attempts']+1;
            sql('UPDATE users SET failed_attempts=?,locked_until=? WHERE id=?',[$fails>=5?0:$fails,$fails>=5?date('Y-m-d H:i:s',time()+15*60):null,$row['id']]);
        }
        flash('Invalid credentials or account temporarily locked. Try again later.','error'); go('login');
    }
    session_regenerate_id(true);
    $_SESSION['user']=['id'=>(int)$row['id'],'username'=>$row['username'],'full_name'=>$row['full_name'],'role'=>$row['role']];
    $_SESSION['csrf']=bin2hex(random_bytes(32));
    sql('UPDATE users SET failed_attempts=0, locked_until=NULL, last_login_at=NOW() WHERE id=?',[$row['id']]);
    log_action('login','users',(int)$row['id'],'User signed in');
    go('dashboard');
}
function validate_input(string $module): array {
    global $modules;
    $fields=$modules[$module]['fields'];
    $data=[];
    foreach ($fields as $key=>$spec) {
        $value=trim((string)($_POST[$key]??''));
        $type=$spec['type']??'text';
        if (!empty($spec['required']) && $value==='') throw new DomainException($spec['label'].' is required.');
        if ($value==='') {$data[$key]=null;continue;}
        if (($type==='select') && !in_array($value,$spec['options'],true)) throw new DomainException('Invalid '.$spec['label'].'.');
        if ($type==='reference') {
            if (!ctype_digit($value) || (int)$value<1 || !record_row($spec['ref'],(int)$value)) throw new DomainException('Invalid or inaccessible '.$spec['label'].'.');
            $data[$key]=(int)$value;
            continue;
        }
        if ($type==='date' || $type==='datetime-local') {
            $format=$type==='date'?'!Y-m-d':'!Y-m-d\TH:i';
            $dt=DateTimeImmutable::createFromFormat($format,$value);
            if (!$dt || $dt->format($type==='date'?'Y-m-d':'Y-m-d\TH:i')!==$value) throw new DomainException('Invalid date for '.$spec['label'].'.');
            $data[$key]=$type==='date'?$value:$dt->format('Y-m-d H:i:s');
            continue;
        }
        $limit=$type==='textarea'?10000:(str_ends_with($key,'_no')?40:255);
        if (mb_strlen($value)>$limit) throw new DomainException($spec['label'].' exceeds '.$limit.' characters.');
        $data[$key]=$value;
    }
    if ($module==='inmates' && $data['status']!=='Active' && !$data['status_date']) throw new DomainException('Please set the released/transferred date.');
    if ($module==='documents' && !in_array($data['classification'],allowed_doc_classifications(),true)) throw new DomainException('Your role cannot assign that document classification.');
    return $data;
}
function save_record(string $module): never {
    global $modules;
    if (!can_edit() || !isset($modules[$module])) {http_response_code(403);exit('Forbidden');}
    verify_csrf();
    $id=max(0,(int)($_POST['id']??0));
    $existing=$id?record_row($module,$id):null;
    if ($id && !$existing) {http_response_code(404);exit('Record not found or access denied');}
    try {
        $data=validate_input($module);
        if ($module==='visits' && !$existing && in_array($data['status'],['Pending','Approved'],true)) {
            $inmate=record_row('inmates',(int)$data['inmate_id']);
            if (!$inmate || $inmate['status']!=='Active') throw new DomainException('New pending/approved visits must reference an active inmate.');
        }
        if ($module==='routing_sheets') {
            // Workflow status may only be changed by the routing-actions UI.
            $data['status']=$existing['status']??'Draft';
        }
        db()->beginTransaction();
        $table=$modules[$module]['table'];
        if ($existing) {
            $set=implode(', ',array_map(fn($c)=>$c.'=?',array_keys($data)));
            sql('UPDATE '.$table.' SET '.$set.' WHERE id=?',array_merge(array_values($data),[$id]));
        } else {
            $cols=array_keys($data);
            sql('INSERT INTO '.$table.' ('.implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')',array_values($data));
            $id=(int)db()->lastInsertId();
        }
        if ($module==='routing_sheets' && !$existing) {
            sql('INSERT INTO routing_history(routing_sheet_id,action,from_unit,to_unit,note,actor_id) VALUES(?,?,?,?,?,?)',
               [$id,'Created',$data['origin_unit'],$data['destination_unit'],'Routing sheet created',user()['id']]);
        }
        $action=$existing?'updated':'created';
        $createdMessages=[
            'inmates'=>'New inmate added', 'personnel'=>'Personnel recorded',
            'visitors'=>'Visitor registered', 'visits'=>'Visitor recorded',
            'incidents'=>'Incident recorded', 'documents'=>'Document registered',
            'cases'=>'Case recorded', 'custody_events'=>'Custody event recorded',
            'routing_sheets'=>'Routing sheet created',
        ];
        $message=$existing?($modules[$module]['singular'].' updated'):$createdMessages[$module];
        log_action($action,$module,$id,$message);
        db()->commit();
        flash($modules[$module]['singular'].' saved successfully.');go($module,['action'=>'view','id'=>$id]);
    } catch (DomainException $ex) {
        if (db()->inTransaction()) db()->rollBack();
        flash($ex->getMessage(),'error');go($module,['action'=>$id?'edit':'new','id'=>$id]);
    } catch (PDOException $ex) {
        if (db()->inTransaction()) db()->rollBack();
        if ($ex->getCode()==='23000') flash('Duplicate control number or invalid linked record. Check the fields.','error');
        else {error_log($ex->getMessage());flash('Database error. Contact the administrator.','error');}
        go($module,['action'=>$id?'edit':'new','id'=>$id]);
    }
}
function archive_record(string $module): never {
    global $modules;
    require_admin();verify_csrf();
    $id=(int)($_POST['id']??0);
    if (!$id || !isset($modules[$module]) || !record_row($module,$id)) {http_response_code(404);exit('Not found');}
    try {
        db()->beginTransaction();
        sql('UPDATE '.$modules[$module]['table'].' SET archived_at=NOW() WHERE id=? AND archived_at IS NULL',[$id]);
        log_action('archived',$module,$id,$modules[$module]['singular'].' archived');
        db()->commit();flash('Record archived. Historical links remain intact.');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        error_log($ex->getMessage());flash('Unable to archive record.','error');
    }
    go($module);
}
function routing_event(): never {
    if (!can_edit()) {http_response_code(403);exit('Forbidden');}
    verify_csrf();
    $id=(int)($_POST['id']??0);$action=(string)($_POST['routing_action']??'');
    $sheet=record_row('routing_sheets',$id);
    $map=['Forwarded'=>'In Transit','Received'=>'Received','Completed'=>'Completed','Note'=>null];
    if (!$sheet || !array_key_exists($action,$map)) {http_response_code(400);exit('Invalid routing action');}
    $from=trim((string)($_POST['from_unit']??$sheet['origin_unit']));
    $to=trim((string)($_POST['to_unit']??$sheet['destination_unit']));
    $note=trim((string)($_POST['note']??''));
    if (mb_strlen($from)>150 || mb_strlen($to)>150 || mb_strlen($note)>10000) {flash('Routing entry is too long.','error');go('routing_sheets',['action'=>'view','id'=>$id]);}
    db()->beginTransaction();
    try {
        if ($map[$action]) sql('UPDATE routing_sheets SET status=?, destination_unit=? WHERE id=?',[$map[$action],$to,$id]);
        sql('INSERT INTO routing_history(routing_sheet_id,action,from_unit,to_unit,note,actor_id) VALUES (?,?,?,?,?,?)',
            [$id,$action,$from,$to,$note?:null,user()['id']]);
        log_action('routed','routing_sheets',$id,'Routing sheet '.$action);
        db()->commit();flash('Routing activity recorded.');
    } catch(Throwable $ex) {
        db()->rollBack();error_log($ex->getMessage());flash('Could not save routing activity.','error');
    }
    go('routing_sheets',['action'=>'view','id'=>$id]);
}
function save_user(): never {
    require_admin();verify_csrf();
    $id=(int)($_POST['id']??0);
    $name=trim((string)($_POST['full_name']??''));
    $username=trim((string)($_POST['username']??''));
    $role=(string)($_POST['role']??'viewer');
    $unit=(string)($_POST['unit']??'Admin');
    $active=isset($_POST['is_active'])?1:0;
    $password=(string)($_POST['password']??'');
    $units=['Admin','Records Unit','Security and Custody Unit','Case Management Unit','Visitors and Reception Unit'];
    if (!$name || mb_strlen($name)>150 || !preg_match('/^[A-Za-z0-9_.@-]{3,60}$/',$username) || !in_array($role,['admin','records_officer','viewer'],true) || !in_array($unit,$units,true)) {
        flash('Provide a valid name, username (3–60 chars) and role.','error');go('users',['action'=>$id?'edit':'new','id'=>$id]);
    }
    if (!$id && strlen($password)<12) {flash('New accounts need passwords of at least 12 characters.','error');go('users',['action'=>'new']);}
    if ($password!=='' && strlen($password)<12) {flash('Password must be at least 12 characters.','error');go('users',['action'=>'edit','id'=>$id]);}
    if ($id===(int)user()['id'] && (!$active || $role!=='admin')) {flash('You cannot disable or demote your own admin account.','error');go('users',['action'=>'edit','id'=>$id]);}
    try {
        db()->beginTransaction();
        if ($id) {
            if (!one('SELECT id FROM users WHERE id=?',[$id])) throw new DomainException('Account not found');
            sql('UPDATE users SET full_name=?,username=?,role=?,unit=?,is_active=? WHERE id=?',[$name,$username,$role,$unit,$active,$id]);
            if ($password!=='') sql('UPDATE users SET password_hash=?,failed_attempts=0,locked_until=NULL WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);
        } else {
            sql('INSERT INTO users(full_name,username,password_hash,role,unit,is_active) VALUES(?,?,?,?,?,?)',
               [$name,$username,password_hash($password,PASSWORD_DEFAULT),$role,$unit,$active]);
            $id=(int)db()->lastInsertId();
        }
        log_action('saved','users',$id,'User account saved');db()->commit();flash('User saved successfully.');go('users');
    } catch(PDOException $ex) {
        if(db()->inTransaction())db()->rollBack();
        flash($ex->getCode()==='23000'?'Username already exists.':'Could not save user.','error');go('users');
    } catch(DomainException $ex) {
        if(db()->inTransaction())db()->rollBack();flash($ex->getMessage(),'error');go('users');
    }
}
function setup_admin(): never { verify_csrf(); if((int)sql('SELECT COUNT(*) FROM users')->fetchColumn()>0){go('login');} $u=trim((string)($_POST['username']??''));$n=trim((string)($_POST['full_name']??''));$p=(string)($_POST['password']??''); if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.@-]{2,59}$/',$u)||!$n||strlen($p)<12){flash('Enter valid details and a 12+ character password.','error');go('setup');} sql('INSERT INTO users(username,full_name,password_hash,role,unit) VALUES(?,?,?,?,?)',[$u,$n,password_hash($p,PASSWORD_DEFAULT),'admin']);go('login');}
function submit_account_request(): never {
    verify_csrf();
    $name=trim((string)($_POST['full_name']??'')); $employee=trim((string)($_POST['employee_no']??'')); $department=trim((string)($_POST['department']??'')); $email=trim((string)($_POST['contact_email']??'')); $role=(string)($_POST['requested_role']??'viewer'); $why=trim((string)($_POST['justification']??'')); $password=(string)($_POST['password']??''); $confirm=(string)($_POST['password_confirm']??'');
    $units=['Records Unit','Security and Custody Unit','Case Management Unit','Visitors and Reception Unit'];
    if(!$name||!$employee||!in_array($department,$units,true)||!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['records_officer','viewer'],true)||mb_strlen($why)<10||strlen($password)<12||$password!==$confirm){flash('Complete all fields and make sure the passwords match and contain at least 12 characters.','error');go('request_account');}
    sql('INSERT INTO account_requests(full_name,employee_no,department,contact_email,password_hash,requested_role,justification) VALUES(?,?,?,?,?,?,?)',[$name,$employee,$department,$email,password_hash($password,PASSWORD_DEFAULT),$role,$why]); flash('Request submitted for administrator approval.'); go('request_account');
}
function review_account_request(): never {
    require_admin(); verify_csrf(); $id=(int)($_POST['request_id']??0); $decision=(string)($_POST['decision']??''); $u=trim((string)($_POST['username']??'')); $r=one("SELECT * FROM account_requests WHERE id=? AND status='Pending'",[$id]); if(!$r){flash('Request not found.','error');go('users');} if($decision==='reject'){sql("UPDATE account_requests SET status='Rejected',reviewed_by=?,reviewed_at=NOW() WHERE id=?",[user()['id'],$id]);flash('Request rejected.');go('users');} if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.@-]{2,59}$/',$u)){flash('Enter a valid username.','error');go('users');} sql('INSERT INTO users(username,full_name,password_hash,role,unit) VALUES(?,?,?,?,?)',[$u,$r['full_name'],$r['password_hash'],$r['requested_role'],$r['department']]); sql("UPDATE account_requests SET status='Approved',desired_username=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?",[$u,user()['id'],$id]); flash('Request approved and account created.'); go('users');
}
