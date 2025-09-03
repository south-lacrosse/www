<form role="search" method="get" action="/" class="sf" onsubmit="return s.value!=''">
<label class="sl">
<span class="screen-reader-text">Search for:</span>
<input type="search" class="si" placeholder="Search…" <?= !empty($autofocus) ? 'autofocus ' : '' ?>onfocus="select(this)" value="<?= get_search_query() ?>" name="s" required>
</label>
<input type="submit" class="btn ss" value="Search">
</form>
