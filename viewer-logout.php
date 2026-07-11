<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';if(vp3_method()!=='POST'){http_response_code(405);exit('Method not allowed.');}vp3_verify_csrf();vp3_viewer_logout();vp3_redirect('clips.php');
