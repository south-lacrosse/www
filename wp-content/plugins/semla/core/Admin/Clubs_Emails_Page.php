<?php
namespace Semla\Admin;
use Semla\Data_Access\Club_Gateway;
/**
 * Display/download all club emails
 */
class Clubs_Emails_Page {
	public static function render_page() {
		if (!current_user_can('manage_semla'))  {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		?>
<div class="wrap">
	<h1>Club Emails</h1>
<?php
		if ( strtolower( $_SERVER['REQUEST_METHOD'] ) === 'post' ) {
			Admin_Menu::validate_nonce('semla_clubs_emails'); ?>
<div class="postbox"><div class="inside">
<button id="copy-to-clipboard" class="button button-secondary">Copy to clipboard</button>
<pre id="semla-emails" style="overflow:auto">
<?php list($format,$include) = self::render_emails(); ?>
</pre>
<script>
	document.getElementById('copy-to-clipboard').addEventListener('click', function() {
		navigator.clipboard.writeText(document.getElementById('semla-emails').innerText)
	});
</script>
</div></div>
<?php
		} else {
			$format = 'text';
			$include = 'all';
		}
?>
	<p>Download or display all club email addresses.</p>
	<p>Note: this page will only extract emails if they are in an Attribute/Value or
		Social Icons block. Attribute/Values should have the role as the attribute, and the
		value should start with the name (if there is one), and contain the email address.</p>
	<form method="post">
		<table class="form-table" role="presentation"><tbody>
		<tr>
			<th scope="row">Format</th>
			<td><fieldset>
				<?php self::render_radio_buttons([
					'text' => 'Plain email addresses',
					'full' => 'Name/addresses e.g. John Doe - Club Name 1st Team Captain &lt;john@example.com&gt;',
					'csv' => 'CSV (Comma Separated Value) with fields for each part'
					], 'format', $format); ?>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row">Include Emails</th>
			<td><fieldset>
				<?php self::render_radio_buttons([
					'all' => 'All club emails',
					'one-per-club' => 'Only the first email on each Club page, usually the main contact'
					], 'include', $include); ?>
				</fieldset>
			</td>
		</tr>
		</tbody></table>
		<p class="submit">
			<?php wp_nonce_field('semla_clubs_emails') ?>
			<input type="submit" name="display" id="submit" class="button button-primary" value="Display">
			<input type="submit" name="download" id="submit" class="button button-secondary" value="Download">
		</p>
	</form>
</div>
<?php
	}

	public static function check_download() {
		if ( empty( $_POST['download'] ) ) return;

		if (!current_user_can('manage_semla'))  {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		Admin_Menu::validate_nonce('semla_clubs_emails');
		list($format,$str) = self::render_emails(true);
		if ( headers_sent() ) {
			$last_error = error_get_last();
			$msg        = isset( $last_error['message'] ) ? '<p>Error: ' . $last_error['message'] . '</p>' : '';
			wp_die( '<h3>Output prevented download.</h3>' . $msg );
		}
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Length: ' . strlen( $str ) );
		header( 'Content-Disposition: attachment; filename=emails.'
			. ( $format === 'csv' ? 'csv' : 'txt') );
		echo $str;
		exit;
	}

	/**
	 * Render a set of radio buttons and mark which one is checked
	 */
	private static function render_radio_buttons($options, $name, $value) {
		foreach ($options as $option => $label) {
			?>
				<p><label>
					<input name="<?= $name ?>" type="radio" value="<?= $option ?>" class="tog" <?php checked( $option, $value ); ?>>
					<?= $label ?></label>
				</p>
<?php

		}
	}

	/**
	 * For $download return format & emails, otherwise echo emails and return
	 * format & include.
	 */
	private static function render_emails($download = false) {
		$include = $_POST['include'] ?? 'all';
		$format = $_POST['format'] ?? 'text';

		$emails = Club_Gateway::get_club_emails($include === 'one-per-club');
		$str = '';
		if ($format === 'text') {
			$str = implode("\n",array_keys($emails));
		} else {
			foreach ($emails as $email => $values) {
				$name_addr = '';
				if (!empty($values['name'])) {
					$name_addr = $values['name'] . ' - ';
				}
				$name_addr .= $values['club'];
				if (!empty($values['role'])) {
					$name_addr .= ' ' . $values['role'];
				}
				$emails[$email]['full'] = $name_addr . ' <' . $email . '>';
			}
			if ($format === 'csv') {
				$str = "Email,Club,Role,Name,\"Name Address\"\n";
				foreach ($emails as $email => $values) {
					$str .= "$email,\"{$values['club']}\",\"{$values['role']}\",\"{$values['name']}\",\"{$values['full']}\"\n";
				}
			} else { // full
				$str = implode("\n",array_column($emails, 'full'));
			}
		}

		if ($download) return [$format, $str];
		echo strtr($str, [
			'<' => '&lt;',
			'>' => '&gt;',
		]);
		return [$format, $include];
	}
}
