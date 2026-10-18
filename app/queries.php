<?php
if (!defined('JMRIS_BOOTSTRAPPED')) exit;
function filtered_query(string $module, array $filters=[],bool $count=false,bool $unlimited=false): array {
    global $modules;
    if (!isset($modules[$module])) throw new InvalidArgumentException('Unknown module');
    $cfg=$modules[$module]; [$condition,$params]=records_constraint($module);
    $q=trim((string)($filters['q']??''));
    if ($q!=='') {
        $clauses=[];
        foreach ($cfg['search'] as $col) $clauses[]='t.'.$col.' LIKE ?';
        if ($clauses) {
            $condition.=' AND ('.implode(' OR ',$clauses).')';
            foreach ($clauses as $_) $params[]='%'.$q.'%';
        }
    }
    if ($cfg['status'] && isset($filters['status']) && $filters['status']!=='') {
        $opts=$cfg['fields'][$cfg['status']]['options'];
        if (in_array($filters['status'],$opts,true)) {
            $condition.=' AND t.'.$cfg['status'].'=?'; $params[]=$filters['status'];
        }
    }
    $dateField=$cfg['date'];
    if ($dateField) {
        foreach (['from'=>'>=','to'=>'<='] as $key=>$op) {
            $value=(string)($filters[$key]??'');
            if ($value!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$value) && strtotime($value)!==false) {
                $condition.=' AND t.'.$dateField.' '.$op.' ?';
                $params[]=$value.($key==='to'?' 23:59:59':' 00:00:00');
            }
        }
    }
    if ($count) return ['SELECT COUNT(*) FROM '.$cfg['table'].' t WHERE '.$condition,$params];
    $query='SELECT t.* FROM '.$cfg['table'].' t WHERE '.$condition.' ORDER BY t.id DESC';
    if (!$unlimited) {
        $page=max(1,min(100000,(int)($filters['p']??1)));
        $perPage=20;
        $query.=' LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage);
    }
    return [$query,$params];
}
function rows_for(string $module,array $filters=[],bool $unlimited=false): array {
    [$query,$params]=filtered_query($module,$filters,false,$unlimited);
    return all($query,$params);
}
function total_for(string $module,array $filters=[]): int {
    [$query,$params]=filtered_query($module,$filters,true);
    return (int)sql($query,$params)->fetchColumn();
}
