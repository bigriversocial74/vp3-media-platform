<?php declare(strict_types=1);require dirname(__DIR__).'/bootstrap.php';if(vp3_method()==='POST'){vp3_verify_csrf();vp3_logout('admin');}vp3_redirect('admin/login.php');
