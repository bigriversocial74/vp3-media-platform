<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';use VP3\Network\ViewerActionService;$pageTitle='Saved Clips';$pageDescription='Clips you saved for later.';require VP3_ROOT.'/includes/viewer-header.php';$items=(new ViewerActionService(vp3_db()))->library((int)$viewer['id'],'saved');require VP3_ROOT.'/includes/viewer-library-grid.php';require VP3_ROOT.'/includes/viewer-footer.php';
