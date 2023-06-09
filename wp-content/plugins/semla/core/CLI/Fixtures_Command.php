<?php
namespace Semla\CLI;
/**
 * Manage SEMLA fixtures/tables/flags.
 *
 * Note it is better to use the SEMLA WordPress Admin menu as that purges fewer
 * cached pages than the CLI version because of the way LiteSpeed cache works.
 *
 * ## EXAMPLE
 *
 *     # Update fixtures.
 *     $ wp fixtures update
 */

use Semla\Data_Access\Fixtures_Sheet_Gateway;
use \WP_CLI;
class Fixtures_Command {
	/**
	 * Update the fixtures and tables.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : also update the divisions and teams
	 */
	public function update($args, $assoc_args) {
		$type = isset($assoc_args['all']) ? 'update-all' : 'update';
		$result = (new Fixtures_Sheet_Gateway())->update($type);
		if (is_wp_error($result)) {
			WP_CLI::warning('Update failed (no data has been changed)');
			$this->handle_wp_error($result);
		}
		WP_CLI::success('Fixtures updated');
		foreach ($result as $message) {
			WP_CLI::log($message);
		}
		$this->done();
	}

	/**
	 * Revert last fixtures update.
	 *
	 * Only run as a last resort to quickly undo an update. It won't work if the
	 * teams or divisions were changed.
	 */
	public function revert() {
		$result = Fixtures_Sheet_Gateway::revert();
		if (is_wp_error($result)) {
			$this->handle_wp_error($result);
		}
		WP_CLI::success($result);
		$this->done();
	}

	private function done() {
		if (defined( 'LSCWP_V' )) {
			WP_CLI::log('Info: fixtures updates are better run from the SEMLA Admin menu as that purges fewer cached pages than the CLI version');
			Util::clear_lscache();
		}
	}

	private function handle_wp_error($error) {
		foreach ($error->get_error_messages() as $message) {
			WP_CLI::error($message, false);
		}
		WP_CLI::halt(1);
	}
}
