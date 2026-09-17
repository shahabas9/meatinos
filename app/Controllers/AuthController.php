<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\View;

final class AuthController
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            redirect('dashboard');
        }
        View::render('auth/login', ['title' => 'Sign in'], false);
    }

    public function login(): void
    {
        Csrf::verify($_POST['_token'] ?? null);
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (!Auth::attempt($email, $password)) {
            flash('danger', 'Sign-in failed. Check your credentials or wait 15 minutes after repeated attempts.');
            $_SESSION['_old']['email'] = $email;
            redirect('login');
        }
        unset($_SESSION['_old']);
        flash('success', 'Welcome back. All Meatin systems are connected.');
        redirect('dashboard');
    }

    public function logout(): void
    {
        Csrf::verify($_POST['_token'] ?? null);
        Auth::logout();
        redirect('login');
    }

    public function passwordForm(): void
    {
        Auth::requireLogin();
        View::render('auth/password', ['title' => 'Security settings', 'user' => Auth::user()]);
    }

    public function changePassword(): void
    {
        Auth::requireLogin();
        Csrf::verify($_POST['_token'] ?? null);
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');
        if (strlen($new) < 12 || !preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/\d/', $new) || !preg_match('/[^A-Za-z0-9]/', $new)) {
            flash('danger', 'Use at least 12 characters with upper-case, lower-case, number, and symbol.');
            redirect('password');
        }
        if (!hash_equals($new, $confirmation)) {
            flash('danger', 'The new password confirmation does not match.');
            redirect('password');
        }
        if (!Auth::changePassword($current, $new)) {
            flash('danger', 'The current password is incorrect.');
            redirect('password');
        }
        flash('success', 'Password updated successfully.');
        redirect('dashboard');
    }
}

