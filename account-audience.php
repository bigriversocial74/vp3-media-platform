<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use VP3\Network\CreatorAudienceService;

$pageTitle='Audience analytics';
require VP3_ROOT.'/includes/account-header.php';
$data=(new CreatorAudienceService(vp3_db()))->summary((int)$customer['id']);
function vp3_audience_rate(array $row):string{
    $views=max(0,(int)($row['views']??0));
    return $views>0?number_format(((int)($row['completions']??0)/$views)*100,1).'%':'0.0%';
}
?>
<h1>Audience analytics</h1>
<p class="helper">Aggregated VP3 Network discovery performance. No viewer email addresses or private profiles are exposed.</p>
<section class="card"><h2>Creators</h2><div class="table-wrap"><table class="table"><thead><tr><th>Creator</th><th>Followers</th><th>Clips</th><th>Views</th><th>Completion</th><th>Likes</th><th>Saves</th><th>Comments</th><th>Destination opens</th></tr></thead><tbody>
<?php foreach($data['creators']as$row):?><tr><td><?=vp3_e($row['display_name'])?></td><td><?=(int)$row['followers']?></td><td><?=(int)$row['clips']?></td><td><?=(int)$row['views']?></td><td><?=vp3_audience_rate($row)?></td><td><?=(int)$row['likes']?></td><td><?=(int)$row['saves']?></td><td><?=(int)$row['comments']?></td><td><?=(int)$row['destination_opens']?></td></tr><?php endforeach;?>
<?php if(!$data['creators']):?><tr><td colspan="9">No creator audience data yet.</td></tr><?php endif;?>
</tbody></table></div></section>
<section class="card"><h2>Shows</h2><div class="table-wrap"><table class="table"><thead><tr><th>Show</th><th>Followers</th><th>Clips</th><th>Views</th><th>Completion</th><th>Likes</th><th>Saves</th><th>Comments</th><th>Destination opens</th></tr></thead><tbody>
<?php foreach($data['shows']as$row):?><tr><td><?=vp3_e($row['title'])?></td><td><?=(int)$row['followers']?></td><td><?=(int)$row['clips']?></td><td><?=(int)$row['views']?></td><td><?=vp3_audience_rate($row)?></td><td><?=(int)$row['likes']?></td><td><?=(int)$row['saves']?></td><td><?=(int)$row['comments']?></td><td><?=(int)$row['destination_opens']?></td></tr><?php endforeach;?>
<?php if(!$data['shows']):?><tr><td colspan="9">No show audience data yet.</td></tr><?php endif;?>
</tbody></table></div></section>
<section class="card"><h2>Recent clips</h2><div class="table-wrap"><table class="table"><thead><tr><th>Clip</th><th>Creator / show</th><th>Views</th><th>Completion</th><th>Likes</th><th>Saves</th><th>Comments</th><th>Destination opens</th></tr></thead><tbody>
<?php foreach($data['clips']as$row):?><tr><td><?=vp3_e($row['title'])?><br><small><?=vp3_e((string)$row['published_at'])?></small></td><td><?=vp3_e(trim((string)$row['creator_name'].' / '.(string)$row['show_title'],' /'))?></td><td><?=(int)$row['views']?></td><td><?=vp3_audience_rate($row)?></td><td><?=(int)$row['likes']?></td><td><?=(int)$row['saves']?></td><td><?=(int)$row['comments']?></td><td><?=(int)$row['destination_opens']?></td></tr><?php endforeach;?>
<?php if(!$data['clips']):?><tr><td colspan="8">No clip audience data yet.</td></tr><?php endif;?>
</tbody></table></div></section>
<?php require VP3_ROOT.'/includes/account-footer.php';?>
