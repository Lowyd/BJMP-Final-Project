<?php
// Local CLI only: php bin/create_admin.php admin "System Administrator"
if (PHP_SAPI !== 'cli') {http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/app/bootstrap.php';
$username=$argv[1]??'';
$name=$argv[2]??'System Administrator';
if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.@-]{2,59}$/',$username)) {
    fwrite(STDERR,"Usage: php bin/create_admin.php <username> [full_name]\n");exit(1);
}
if (!function_exists('stream_get_line')) {fwrite(STDERR,"Standard input unavailable\n");exit(1);}
fwrite(STDOUT,"Enter a new password (12+ characters; input visible): ");
$password=rtrim((string)fgets(STDIN),"\r\n");
if (strlen($password)<12) {fwrite(STDERR,"Password is too short\n");exit(1);}
try {
  sql('INSERT INTO users(username,full_name,password_hash,role) VALUES(?,?,?,?)',
      [$username,$name,password_hash($password,PASSWORD_DEFAULT),'admin']);
  fwrite(STDOUT,"Admin account created. Delete the plaintext password from your terminal history if applicable.\n");
} catch(PDOException $ex) {
  fwrite(STDERR,"Unable to create account. Check database setup and username uniqueness.\n");exit(1);
}
