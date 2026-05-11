<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

ph_logout_user();
ph_set_flash('success', 'Vous avez été déconnecté.');
ph_redirect('auth_login.php');
