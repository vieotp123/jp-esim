<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
if (CtvAuth::currentUser()) { header('Location: /ctv/dashboard.php'); exit; }
header('Location: /auth?role=partner');
exit;
