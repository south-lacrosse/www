<?php
add_action( 'cli_init', function() {
	if (get_option('semla_fixtures_source') === 'lacrosseplay') {
		WP_CLI::add_command( 'fixtures', \Semla\CLI\Lacrosse_Play_Fixtures_Command::class );
	} else {
		WP_CLI::add_command( 'fixtures', \Semla\CLI\Fixtures_Command::class );
	}
	WP_CLI::add_command( 'history', \Semla\CLI\History_Command::class );
	WP_CLI::add_command( 'monitor', \Semla\CLI\Monitor_Command::class );
	WP_CLI::add_command( 'purge', \Semla\CLI\Purge_Command::class );
	WP_CLI::add_command( 'semla-media', \Semla\CLI\Media_Command::class );
} );
