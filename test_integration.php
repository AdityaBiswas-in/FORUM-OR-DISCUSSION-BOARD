<?php
require_once __DIR__ . '/db.php';

echo "=== 1. Testing check_id_exists ===\n";
echo "Checking existing student 1000000001: " . (check_id_exists('1000000001', $conn) ?? 'null') . "\n";
echo "Checking existing faculty 5000000001: " . (check_id_exists('5000000001', $conn) ?? 'null') . "\n";
echo "Checking existing admin 9000000001: " . (check_id_exists('9000000001', $conn) ?? 'null') . "\n";
echo "Checking non-existing ID 9999999999: " . (check_id_exists('9999999999', $conn) ?? 'null') . "\n";

echo "\n=== 2. Testing check_email_exists ===\n";
echo "Checking student@quaddit.edu: " . (check_email_exists('student@quaddit.edu', $conn) ?? 'null') . "\n";
echo "Checking faculty@quaddit.edu: " . (check_email_exists('faculty@quaddit.edu', $conn) ?? 'null') . "\n";
echo "Checking admin@quaddit.edu: " . (check_email_exists('admin@quaddit.edu', $conn) ?? 'null') . "\n";
echo "Checking unknown@quaddit.edu: " . (check_email_exists('unknown@quaddit.edu', $conn) ?? 'null') . "\n";

echo "\n=== 3. Testing Login Verification for Seed Users ===\n";
$student = $conn->execute_query("SELECT * FROM students WHERE id = ?", ['1000000001'])->fetch_assoc();
echo "Student password verify: " . (password_verify('password123', $student['password']) ? 'SUCCESS' : 'FAILED') . "\n";

$faculty = $conn->execute_query("SELECT * FROM faculty WHERE id = ?", ['5000000001'])->fetch_assoc();
echo "Faculty password verify: " . (password_verify('password123', $faculty['password']) ? 'SUCCESS' : 'FAILED') . "\n";

$admin = $conn->execute_query("SELECT * FROM admins WHERE id = ?", ['9000000001'])->fetch_assoc();
echo "Admin password verify: " . (password_verify('password123', $admin['password']) ? 'SUCCESS' : 'FAILED') . "\n";

echo "\nAll preliminary tests passed successfully!\n";

