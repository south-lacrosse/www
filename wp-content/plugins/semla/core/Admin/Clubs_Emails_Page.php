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
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			Admin_Menu::validate_nonce('semla_clubs_emails'); ?>
<div class="postbox"><div class="inside">
<button id="display-copy" class="button button-secondary">Copy to clipboard</button>
<pre id="semla-emails" style="overflow:auto">
<?php list($format, $include, $officers) = self::render_emails(); ?>
</pre>
<script>
(function () {
	const copyBtn = document.getElementById('display-copy');
	let timeoutId;
	copyBtn.addEventListener('click', function() {
		navigator.clipboard.writeText(document.getElementById('semla-emails').textContent);
		copyBtn.textContent = 'Emails copied';
		clearTimeout( timeoutId );
		timeoutId = setTimeout( () => { copyBtn.textContent = 'Copy to clipboard'; }, 5000 );
	} );
})();
</script>
</div></div>
<?php
		} else {
			$format = 'text';
			$include = 'all';
			$officers = true;
		}
?>
	<p>Download, copy, or display all club email addresses.</p>
	<p>Note: this page will only extract emails if they are in a Contact or Social Icons block.</p>
	<form method="post" id="emails-form">
		<table class="form-table" role="presentation"><tbody>
		<tr>
			<th scope="row">Format</th>
			<td><fieldset>
				<?php self::render_radio_buttons([
					'text' => 'Plain email addresses',
					'full' => 'Name/addresses e.g. John Doe - Club Name 1st Team Captain &lt;john@example.com&gt;',
					'csv' => 'CSV (Comma Separated Value) with fields for each part, for loading into Excel or Google Sheets.'
					], 'format', $format); ?>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row">Include Emails</th>
			<td><fieldset>
				<?php self::render_radio_buttons([
					'all' => 'All club emails',
					'one-per-club' => 'One email address per club, chosen in order of preference '
						. 'from the Secretary, General Contact (includes email in Social Icons), '
						. 'Chair*, President, otherwise first contact'
					], 'include', $include); ?>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row">Include SEMLA Officers</th>
			<td>
				<p><label>
					<input name="officers" type="checkbox" value="1" <?php checked( $officers ); ?>>
					Add all Officers from the Contacts page</label>
				</p>
			</td>
		</tr>
		</tbody></table>
		<p class="submit">
			<?php wp_nonce_field('semla_clubs_emails') ?>
			<input type="submit" name="display" id="submit" class="button button-primary" value="Display">
			<input type="submit" name="download" id="submit" class="button button-secondary" value="Download">
			<button type="button" id="submit-copy" class="button button-secondary">Copy to Clipboard</button>
		</p>
	</form>
</div>
<script>
(function () {
	const copyBtn = document.getElementById('submit-copy');
	let timeoutId;
	copyBtn.addEventListener('click', async function() {
		const form = document.getElementById('emails-form');
		try {
			const data = new URLSearchParams(new FormData(form));
			data.append('download','Download');
    		const response = await fetch(form.action, {method:'post', body: data});
			if (!response.ok) {
    			throw new Error('Network error');
  			}
			const contentType = await response.headers.get('Content-Type');
			if (contentType !== 'application/octet-stream') {
				throw new Error('Invalid response type: ' + contentType);
			}
		    const result = await response.text();
			navigator.clipboard.writeText(result);
			setButtonText('Emails copied');
		} catch (error) {
			console.error(error);
			setButtonText('Copy failed - check console');
		}
	} );
	function setButtonText(message) {
		copyBtn.textContent = message;
		clearTimeout( timeoutId );
		timeoutId = setTimeout( () => { copyBtn.textContent = 'Copy to clipboard'; }, 5000 );
	}
})();
</script>
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
		$officers = isset( $_POST['officers'] );

		$emails = Club_Gateway::get_club_emails($include === 'one-per-club', $officers);
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
		return [$format, $include, $officers];
	}
}
