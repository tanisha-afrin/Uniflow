<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$tokenMode = $token !== '';
$error = '';
$success = '';
$admin = null;

if ($tokenMode) {
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare('SELECT admin_id,name,email,role,admin_type,activation_expires_at,activation_used FROM admins WHERE activation_token_hash = ? LIMIT 1');
    $stmt->bind_param('s',$hash);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$admin || (int)$admin['activation_used'] === 1 || empty($admin['activation_expires_at']) || strtotime($admin['activation_expires_at']) < time()) {
        $error='This invitation link is invalid, expired, or has already been used.';
        $admin=null;
    }
} else {
    if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit(); }
    $adminId=(int)$_SESSION['admin_id'];
    $stmt=$conn->prepare('SELECT admin_id,name,email,role,admin_type,must_change_password FROM admins WHERE admin_id=? LIMIT 1');
    $stmt->bind_param('i',$adminId); $stmt->execute(); $admin=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$admin) { session_destroy(); header('Location: login.php'); exit(); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin) {
    $new=$_POST['new_password'] ?? ''; $confirm=$_POST['confirm_password'] ?? '';
    if (strlen($new) < 8) $error='Password must be at least 8 characters.';
    elseif ($new !== $confirm) $error='Passwords do not match.';
    else {
        $hashPassword=password_hash($new,PASSWORD_DEFAULT);
        $stmt=$conn->prepare('UPDATE admins SET password=?, must_change_password=0, password_expires_at=NULL, activation_token_hash=NULL, activation_expires_at=NULL, activation_used=? WHERE admin_id=?');
        $used=1; $stmt->bind_param('sii',$hashPassword,$used,$admin['admin_id']);
        if ($stmt->execute()) {
            $stmt->close();
            if ($tokenMode) {
                session_regenerate_id(true);
                $_SESSION['admin_id']=(int)$admin['admin_id']; $_SESSION['admin_name']=$admin['name']; $_SESSION['admin_role']=$admin['role']; $_SESSION['admin_type']=$admin['admin_type']; $_SESSION['user_type']='admin';
            }
            if (($_SESSION['return_to'] ?? '') === 'lost_found') {
                unset($_SESSION['return_to']);
                header('Location: lost_found/index.php');
                exit();
            }
            if (($admin['admin_type'] ?? '') === 'main_admin') { header('Location: administrative/dashboard.php'); exit(); }
            $areas=getAdminAccessAreas($conn,(int)$admin['admin_id']);
            $target=count($areas)===1 ? $areas[0] : ($admin['role'] ?? 'technical');
            $url=match($target){'technical'=>'technical/dashboard.php','administrative'=>'administrative/dashboard.php','proctorial'=>'proctorial/dashboard.php','lost_found'=>'lost_found/index.php',default=>'index.php'};
            header('Location: ' . $url); exit();
        }
        $stmt->close(); $error='Could not update the password. Please try again.';
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Create Password | UniFlow</title><style>
body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:Arial,sans-serif;background:linear-gradient(135deg,#fff8f2,#fff,#fff2e7);color:#2b211c}.card{width:min(92%,520px);background:#fff;border:1px solid #f1dfd1;border-radius:22px;padding:36px;box-shadow:0 25px 70px rgba(96,48,15,.12)}h1{margin:0 0 8px}p{color:#806f64;line-height:1.6}.notice{background:#fff7ed;border:1px solid #fed7aa;padding:14px;border-radius:12px;margin:18px 0}.error{background:#fff0f0;border:1px solid #ffc5c5;color:#b42318;padding:13px;border-radius:10px;margin-bottom:18px}label{display:block;font-weight:700;margin:16px 0 7px}input{width:100%;box-sizing:border-box;padding:14px;border:1px solid #ead8cc;border-radius:12px;font-size:15px}button{width:100%;margin-top:22px;padding:15px;border:0;border-radius:12px;background:#ff6a00;color:#fff;font-weight:800;font-size:15px;cursor:pointer}
</style></head><body><div class="card"><h1>Create your UniFlow password</h1><?php if($admin): ?><p>Hello <strong><?=e($admin['name'])?></strong>. Create the password you will use with your University Email.</p><div class="notice"><strong>Login Email:</strong><br><?=e($admin['email'])?></div><?php endif; ?><?php if($error): ?><div class="error"><?=e($error)?></div><?php endif; ?><?php if($admin): ?><form method="post"><input type="hidden" name="token" value="<?=e($token)?>"><label>New Password</label><input type="password" name="new_password" minlength="8" required><label>Confirm Password</label><input type="password" name="confirm_password" minlength="8" required><button type="submit">Create Password &amp; Continue</button></form><?php else: ?><p>Please request a new invitation from the UniFlow Administrative team.</p><?php endif; ?></div></body></html>
