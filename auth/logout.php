<?php
require_once __DIR__ . '/../backend/config/bootstrap.php';
AuthService::logout();
redirect('/frontend/pages/shared/role.php');
