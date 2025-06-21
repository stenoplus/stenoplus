<?php
session_start();
require '../config.php'; // Database connection

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // Ensure PHPMailer is installed

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $forgotEmail = filter_var($_POST['forgotEmail'], FILTER_VALIDATE_EMAIL);
    
    if (!$forgotEmail) {
        echo "Invalid email format!";
        exit();
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE Email = ?");
    $stmt->bind_param("s", $forgotEmail);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $userId = $row['user_id'];
        $resetToken = bin2hex(random_bytes(32)); // Secure token
        $expiry = (new DateTime())->modify('+10 minutes')->format('Y-m-d H:i:s');

        // Update token in DB
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $resetToken, $expiry, $userId);
        $stmt->execute();

        // Send Email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.hostinger.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'noreply@stenoplus.in';
            $mail->Password = '$StenoPlus@2025';
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;

            $mail->setFrom('noreply@stenoplus.in', 'StenoPlus');
            $mail->addAddress($forgotEmail);
            $mail->isHTML(true);
            $mail->Subject = "Reset Your Password - StenoPlus";

            // Inline Styled Email Body
            $resetLink = "https://stenoplus.in/backend/authentication/reset-password.php?token=" . $resetToken;
            $mail->Body = '
            <div style="max-width: 600px; background-color: #fff; padding: 20px; margin: 20px auto; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); font-family: Arial, sans-serif;">
                <div style="text-align: center;">
                    <img src="https://stenoplus.in/assets/images/stenoplus-logo.png" alt="StenoPlus" style="max-width: 150px; margin-bottom: 20px;">
                </div>
                <h2 style="color: #002147; text-align: center;">Password Reset Request</h2>
                <p style="color: #333; font-size: 16px; text-align: center;">
                    Click the button below to reset your password:
                </p>
                <div style="text-align: center; margin: 20px 0;">
                    <a href="' . $resetLink . '" style="display: inline-block; background-color: #D2171E; color: #ffffff; text-decoration: none; padding: 12px 24px; font-size: 16px; border-radius: 5px;">
                        Reset Password
                    </a>
                </div>
                <p style="color: #333; font-size: 14px; text-align: center;">
                    <strong>Note:</strong> This link expires in <strong>10 minutes</strong>. If you didn’t request this, please ignore this email.
                </p>
                <hr style="border: 0; border-top: 1px solid #ddd; margin: 20px 0;">
                <div style="text-align: center;">
                    <p style="font-size: 14px; color: #666;">Need help? <a href="mailto:support@stenoplus.in" style="color:#D2171E;">Contact Support</a></p>
                    <p style="font-size: 12px; color: #888;">&copy; 2025 StenoPlus. All rights reserved.</p>
                </div>
            </div>';

            // Plain Text Alternative
            $mail->AltBody = "Reset Your Password - StenoPlus\n\nClick the link below to reset your password:\n$resetLink\n\nNote: This link expires in 10 minutes.";

            // Send Email
            if ($mail->send()) {
                echo "Password reset link sent to your email!";
            } else {
                echo "Email sending failed!";
            }

        } catch (Exception $e) {
            echo "Mail Error: " . $mail->ErrorInfo;
        }
    } else {
        echo "Email not found!";
    }
}
?>
