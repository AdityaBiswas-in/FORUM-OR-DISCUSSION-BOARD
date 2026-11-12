<?php
require_once __DIR__ . '/db.php';

$baseUrl = 'http://localhost/FORUM-OR-DISCUSSION-BOARD';
$passed = 0;
$failed = 0;

function check(string $label, bool $cond, string $detail = ''): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "[PASS] $label\n";
    } else {
        $failed++;
        echo "[FAIL] $label" . ($detail ? " ($detail)" : "") . "\n";
    }
}

function httpReq(string $url, ?array $post = null, string $cookieFile = ''): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
    ];
    if ($cookieFile !== '') {
        $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
    }
    if ($post !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($resp, 0, $hdrSize);
    $body = substr($resp, $hdrSize);
    $loc = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
        $loc = trim($m[1]);
    }
    return ['code' => $code, 'location' => $loc, 'body' => $body];
}

$ts = time();
$stuEmail1 = "teststu1_$ts@college.edu";
$stuEmail2 = "teststu2_$ts@college.edu";
$admEmail  = "testadm_$ts@college.edu";
$admId     = (string)random_int(9100000000, 9899999999);

$stuCookie = tempnam(sys_get_temp_dir(), 'stu_');
$admCookie = tempnam(sys_get_temp_dir(), 'adm_');

try {
    // 1. Faculty hidden on public register.php & login.php
    $regHtml = httpReq("$baseUrl/register.php")['body'];
    $logHtml = httpReq("$baseUrl/login.php")['body'];
    check('Faculty tab hidden in register.php', strpos($regHtml, 'register.php?role=faculty"') === false);
    check('Student ID input removed from student register.php', strpos($regHtml, 'name="account_id"') === false);
    check('Faculty tab hidden in login.php', strpos($logHtml, 'login.php?role=faculty"') === false);
    check('Login form uses email input', strpos($logHtml, 'type="email" name="login_input"') !== false);

    // 2. Student registration with ONLY name, email, password (no Student ID)
    $r1 = httpReq("$baseUrl/register.php?role=student", [
        'reg_role' => 'student',
        'name' => 'Test Student One',
        'email' => $stuEmail1,
        'password' => 'Secret123',
        'confirm_password' => 'Secret123',
    ]);
    check('Student registers without Student ID', $r1['code'] === 302 && strpos($r1['location'], 'login.php?role=student&registered=1') !== false, "code={$r1['code']} loc={$r1['location']}");

    $r2 = httpReq("$baseUrl/register.php?role=student", [
        'reg_role' => 'student',
        'name' => 'Test Student Two',
        'email' => $stuEmail2,
        'password' => 'Secret123',
        'confirm_password' => 'Secret123',
    ]);
    check('Second student registers without Student ID', $r2['code'] === 302 && strpos($r2['location'], 'login.php?role=student&registered=1') !== false);

    // 3. Admin registration requires Admin ID
    $raBad = httpReq("$baseUrl/register.php?role=admin", [
        'reg_role' => 'admin',
        'name' => 'Test Admin',
        'email' => $admEmail,
        'account_id' => '',
        'password' => 'AdminPass123',
        'confirm_password' => 'AdminPass123',
        'auth_key' => 'QUAD-ADMIN-2026',
    ]);
    check('Admin registration rejects missing Admin ID', $raBad['code'] === 200 && strpos($raBad['body'], '10-digit Admin ID') !== false);

    $raOk = httpReq("$baseUrl/register.php?role=admin", [
        'reg_role' => 'admin',
        'name' => 'Test Admin',
        'email' => $admEmail,
        'account_id' => $admId,
        'password' => 'AdminPass123',
        'confirm_password' => 'AdminPass123',
        'auth_key' => 'QUAD-ADMIN-2026',
    ]);
    check('Admin registration succeeds with Admin ID + key', $raOk['code'] === 302 && strpos($raOk['location'], 'login.php?role=admin&registered=1') !== false);

    // 4. Student login with email & password
    $ls = httpReq("$baseUrl/login.php?role=student", [
        'action' => 'login',
        'login_role' => 'student',
        'login_input' => $stuEmail1,
        'password' => 'Secret123',
    ], $stuCookie);
    check('Student logs in with email and password', $ls['code'] === 302 && $ls['location'] === 'index.php', "code={$ls['code']} loc={$ls['location']}");

    // 5. Admin login with email & password ONLY
    $la = httpReq("$baseUrl/login.php?role=admin", [
        'action' => 'login',
        'login_role' => 'admin',
        'login_input' => $admEmail,
        'password' => 'AdminPass123',
    ], $admCookie);
    check('Admin logs in with email and password only -> admin dashboard', $la['code'] === 302 && $la['location'] === 'index.php?view=admin', "code={$la['code']} loc={$la['location']}");

    // Lookup user IDs
    $stu2Row = $conn->execute_query("SELECT id FROM users WHERE email = ? LIMIT 1", [$stuEmail2])->fetch_assoc();
    $admRow  = $conn->execute_query("SELECT id, uid FROM users WHERE email = ? LIMIT 1", [$admEmail])->fetch_assoc();
    $stu2Id = (int)($stu2Row['id'] ?? 0);
    $admUserId = (int)($admRow['id'] ?? 0);

    // 6. Profile visibility checks
    $pStuViewStu = httpReq("$baseUrl/profile.php?id=$stu2Id", null, $stuCookie);
    check('Student can view another student profile', $pStuViewStu['code'] === 200 && strpos($pStuViewStu['body'], 'Test Student Two') !== false);

    $pStuViewAdm = httpReq("$baseUrl/profile.php?id=$admUserId", null, $stuCookie);
    check('Student CANNOT view admin profile (redirected)', $pStuViewAdm['code'] === 302 && $pStuViewAdm['location'] === 'index.php', "code={$pStuViewAdm['code']} loc={$pStuViewAdm['location']}");

    $pGuestViewAdm = httpReq("$baseUrl/profile.php?id=$admUserId");
    check('Guest CANNOT view admin profile (redirected)', $pGuestViewAdm['code'] === 302 && $pGuestViewAdm['location'] === 'index.php');

    $pAdmViewStu = httpReq("$baseUrl/profile.php?id=$stu2Id", null, $admCookie);
    check('Admin CAN view student profile', $pAdmViewStu['code'] === 200 && strpos($pAdmViewStu['body'], 'Test Student Two') !== false);

    // 7. Faculty table still intact
    $facCheck = $conn->query("SHOW TABLES LIKE 'faculty'")->fetch_row();
    check('Faculty database table remains intact', (bool)$facCheck);
} finally {
    // Clean up test records
    $conn->execute_query("DELETE FROM users WHERE email IN (?, ?, ?)", [$stuEmail1, $stuEmail2, $admEmail]);
    $conn->execute_query("DELETE FROM students WHERE email IN (?, ?)", [$stuEmail1, $stuEmail2]);
    $conn->execute_query("DELETE FROM admins WHERE email = ?", [$admEmail]);
    @unlink($stuCookie);
    @unlink($admCookie);
}

echo "\nSUMMARY: $passed passed, $failed failed.\n";
exit($failed > 0 ? 1 : 0);

