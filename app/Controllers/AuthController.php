<?php
namespace App\Controllers;

use App\Models\DatabaseModel;
use PDOException;

class AuthController {
    public static function handleLogin() {
        if (isset($_GET['logout'])) {
            session_destroy();
            header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;
        }

        if (!isset($_SESSION['login_attempts'])) { $_SESSION['login_attempts'] = 0; }

        $login_error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_login'])) {
            $h = trim($_POST['db_host']);
            $p = trim($_POST['db_port']);
            $u = trim($_POST['db_user']);
            $pw = $_POST['db_pass'];

            $captcha_valid = true;
            if ($_SESSION['login_attempts'] >= 3) {
                $user_captcha = intval($_POST['captcha_answer'] ?? -1);
                $real_captcha = $_SESSION['captcha_result'] ?? -2;
                if ($user_captcha !== $real_captcha) {
                    $captcha_valid = false;
                    $login_error = "Captcha salah! Anda robot ya?";
                    DatabaseModel::writeLog('login.log', "FAILED (Captcha) - User: $u @ $h");
                }
            }

            if ($captcha_valid) {
                try {
                    DatabaseModel::connect($h, $p, $u, $pw);
                    
                    $_SESSION['db_host'] = $h; $_SESSION['db_port'] = $p; 
                    $_SESSION['db_user'] = $u; $_SESSION['db_pass'] = $pw; 
                    $_SESSION['logged_in'] = true; $_SESSION['login_attempts'] = 0; 
                    unset($_SESSION['captcha_result']);
                    
                    DatabaseModel::writeLog('login.log', "SUCCESS - User: $u @ $h");
                    header("Location: " . $_SERVER['REQUEST_URI']); exit;
                } catch (PDOException $e) { 
                    $_SESSION['login_attempts']++; 
                    $login_error = "Koneksi Gagal: " . $e->getMessage(); 
                    DatabaseModel::writeLog('login.log', "FAILED (Wrong Credentials) - User: $u @ $h");
                }
            } else {
                $_SESSION['login_attempts']++; 
            }
        }

        $captcha_text = '';
        if (!isset($_SESSION['logged_in']) && $_SESSION['login_attempts'] >= 3) {
            $num1 = rand(1, 10); $num2 = rand(1, 10);
            $_SESSION['captcha_result'] = $num1 + $num2;
            $captcha_text = "Berapa $num1 + $num2 ?";
        }

        return ['error' => $login_error, 'captcha_text' => $captcha_text];
    }
}