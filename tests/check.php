<?php
// Run from JMRIS project root: php tests/check.php
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/queries.php';
$errors=[];
foreach ($modules as $name=>$m) {
    if ($m['table']!==$name) $errors[]="$name table mismatch";
    if (!isset($m['fields'][$m['number']])) $errors[]="$name number field missing";
    foreach ($m['columns'] as $key) {
        if (!isset($m['fields'][$key]) && $key!=='created_at') $errors[]="$name column $key not defined";
    }
    foreach ($m['search'] as $key) if (!isset($m['fields'][$key])) $errors[]="$name search field $key not defined";
    foreach ($m['fields'] as $key=>$field) {
        if (($field['type']??'')==='reference' && !isset($modules[$field['ref']])) $errors[]="$name.$key has unknown foreign module";
        if (($field['type']??'')==='select' && !$field['options']) $errors[]="$name.$key has empty options";
    }
    [$sql,$args]=filtered_query($name,['q'=>'abc','status'=>'Active','from'=>'2026-01-01','to'=>'2026-12-31']);
    if (substr_count($sql,'?')!==count($args)) $errors[]="$name query placeholder count mismatch";
}
if (csv_safe('=SUM(1,2)')!=="'=SUM(1,2)" || csv_safe('Normal')!=='Normal') $errors[]='CSV sanitization failed';
if (e('<script>')!=='&lt;script&gt;') $errors[]='HTML encoding failed';
if ($errors) {fwrite(STDERR,"FAIL:\n".implode("\n",$errors)."\n");exit(1);}
echo 'PASS: '.count($modules).' module configurations, SQL placeholders, HTML encoding, CSV escaping. (No DB required.)'.PHP_EOL;
