<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
CtvAuth::logout();
header('Location: /auth');
exit;
