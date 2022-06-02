<?php
$type = isset($team) ? 'team' : 'club';
$url = rest_url($request->get_route()); ?>
<p>Below you will find links to the REST services available for the <?= $type ?>.
If you simply want to embed the <?= $type ?>'s fixtures or tables in your website
<a href="#embed">scroll down</a>.
<table><tbody>
<tr><th>Fixtures</th>
<td><a href="<?= $url ?>/fixtures">HTML snippet</a></td>
<td><a href="<?= $url ?>/fixtures.json">JSON</a></td>
<?php if (isset($team)) : ?>
<td><a href="<?= $url ?>/fixtures.ics">iCalendar</a></td>
<?php endif; ?>
</tr>
<tr><th>League Tables</th>
<td><a href="<?= $url ?>/tables">HTML snippet</a></td>
<td><a href="<?= $url ?>/tables.json">JSON</a></td>
</tr>
</tbody>
</table>
<h3>JSON Fixtures</h3>
<p>Fixtures have the following optional fields:</p>
<ul>
<li><i>venue</i> if the game won't be played at the home team's ground</li>
<li><i>pitchType</i> for games not on grass, and which haven't been played yet</li>
<li><i>matchTime</i> for games which haven't been played yet</li>
<li><i>pointsMulti</i> if the game is for double points</li>
</ul>
<h3>JSON Tables</h3>
<p>Tables will have a <i>pointsDeducted</i> field for all teams in a division where any
team has been deducted points.</p>
<h2 id="embed">Embedding In A Web Page</h2>
<p>To embed the fixtures or league tables copy and paste the embed tags below where you
want them to appear in your web page. See <a href="/data-resources">Data Resources</a> for
a detailed explanation, additional information about styling, and how to modify the
asynchronous versions so that the user sees a spinner before the data is loaded.</p>
<p>If you are using a Content Management System then there should be an option in the page
editor to add custom HTML, e.g. in WordPress you can add a Custom HTML block, so add that
where you want the fixtures/tables and paste the embed tags there.</p>
<h3>Embed <?= $type ?>'s fixtures</h3>
<p>Put the following tag where you want the fixtures to appear. <b>Note:</b> this method will
block the rendering of your page until the fixtures are downloaded from our server. If the
fixtures are the only thing on your page then this is usually the best choice, however if you
want to download the fixtures without blocking then use the asynchronous method below.</p>
<pre><code>&lt;script src="<?= $url ?>/fixtures.js"&gt;&lt;/script&gt;</code></pre>
<h3>Embed <?= $type ?>'s fixtures asynchronously</h3>
<p>Put the <i>&lt;div&gt;</i> tag where you want the fixtures to appear, and the <i>&lt;script&gt;</i> tag
before the closing <i>&lt;/body&gt;</i> tag (or as late on the page as you can). You also need to make sure
the <i>id</i> is unique on your page, and the <i>data-target</i> matches the <i>id</i>.</p>
<pre><code>&lt;div id="my-div"&gt;&lt;/div&gt;
&lt;script data-target="my-div" src="<?= $url ?>/fixtures.js?async" async&gt;&lt;/script&gt;</code></pre>
<h3>Embed <?= $type ?>'s league tables</h3>
<p>Put the following tag where you want the league tables to appear. <b>Note:</b> this method will
block the rendering of your page until the tables are downloaded from our server. If the
tables are the only thing on your page then this is usually the best choice, however if you want
to download the tables without blocking then use the asynchronous method below.</p>
<pre><code>&lt;script src="<?= $url ?>/tables.js"&gt;&lt;/script&gt;</code></pre>
<h3>Embed <?= $type ?>'s league tables asynchronously</h3>
<p>Put the <i>&lt;div&gt;</i> tag where you want the league tables to appear, and the <i>&lt;script&gt;</i> tag
before the closing <i>&lt;/body&gt;</i> tag (or as late on the page as you can). You also need to make sure
the <i>id</i> is unique on your page, and the <i>data-target</i> matches the <i>id</i>.</p>
<pre><code>&lt;div id="my-div"&gt;&lt;/div&gt;
&lt;script data-target="my-div" src="<?= $url ?>/tables.js?async" async&gt;&lt;/script&gt;</code></pre>
