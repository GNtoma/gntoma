<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

cl_logout_user();
cl_redirect('auth_login.php');
