<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../config.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');
if ($email === '') {
    echo json_encode(["success" => false, "message" => "Email required"]);
    exit;
}
// Check user exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->rowCount() === 0) {
    echo json_encode(["success" => false, "message" => "Email not registered"]);
    exit;
}
// Generate OTP
$otp = random_int(100000, 999999);
// Fix: Use MySQL DATE_ADD(NOW(), ...) to handle timezone matching perfectly
$stmt = $pdo->prepare("INSERT INTO password_reset_otp (email, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
$stmt->execute([$email, $otp]);
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = "smtp.gmail.com";
    $mail->SMTPAuth   = true;
    $mail->Username   = "amsaashwin@gmail.com";
    $mail->Password   = "lrgcknvstioxeueq"; // Check if this is correct
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->setFrom("amsaashwin@gmail.com", "Floating Flavors");
    $mail->addAddress($email);
    $mail->Subject = "Floating Flavors - Password Reset OTP";
    $mail->Body    = "Your OTP is $otp.\n\nValid for 10 minutes.";
    $mail->send();
    echo json_encode(["success" => true, "message" => "OTP sent successfully"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Failed to send OTP"]);
}
?>