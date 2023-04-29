<?php $url = rest_url($request->get_route()) . '/'; ?>
<p>See also <a href="<?= substr($url, 0 , -6) ?>clubs">Clubs</a>.</p>
<table>
<tbody>
<?php foreach ($teams as $team) : ?>
<tr><td><a href="<?= $url . urlencode(str_replace(' ','_',$team)) ?>"><?= $team ?></a></td>
<?php endforeach; ?>
</tbody></table>
