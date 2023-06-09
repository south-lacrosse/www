<?php
add_action( 'cli_init', function() {
	WP_CLI::add_command( 'fixtures', \Semla\CLI\Fixtures_Command::class );
	WP_CLI::add_command( 'history', \Semla\CLI\History_Command::class );
	WP_CLI::add_command( 'purge', \Semla\CLI\Purge_Command::class );
	WP_CLI::add_command( 'semla-media', \Semla\CLI\Media_Command::class );
} );
