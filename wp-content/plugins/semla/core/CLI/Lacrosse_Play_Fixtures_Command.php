<?php
namespace Semla\CLI;
/**
 * Manage SEMLA fixtures/tables/flags from the LacrossePlay API.
 *
 * Note it is better to use the SEMLA WordPress Admin menu as that purges fewer
 * cached pages than the CLI version because of the way LiteSpeed cache works.
 *
 * ## EXAMPLE
 *
 *     # Update fixtures.
 *     $ wp fixtures update
 */

use Semla\Data_Access\Lacrosse_Play_Gateway;
use \WP_CLI;
class Lacrosse_Play_Fixtures_Command {
	/**
	 * Update the fixtures and tables.
	 *
	 * ## OPTIONS
	 *
	 * [--flags]
	 * : Load flags,  which is the default behaviour. Use `--no-flags` to ignore flags.
	 *
	 * [--tables]
	 * : Load tables,  which is the default behaviour. Use `--no-tables` to ignore tables.
	 *
	 * [--fixtures]
	 * : Load fixtures,  which is the default behaviour. Use `--no-fixtures` to ignore fixtures.
	 *
	 * [--all]
	 * : also update the divisions and teams
	 */
	public function update($args, $assoc_args) {
		$load_competition_data = isset($assoc_args['all']);
		$data = [];
		foreach (['fixtures','tables','flags'] as $flag) {
			$data[$flag] =  WP_CLI\Utils\get_flag_value( $assoc_args, $flag, true );
		}
		$result = (new Lacrosse_Play_Gateway())->update($load_competition_data, $data);
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
