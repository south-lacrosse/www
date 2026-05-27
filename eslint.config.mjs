import { defineConfig } from 'eslint/config';
import globals from 'globals';
import js from '@eslint/js';
import eslintPluginPrettierRecommended from 'eslint-plugin-prettier/recommended';

export default defineConfig([
	{
		ignores: [
			'**/*',
			// Unignore the directory structure to allow traversal, otherwise
			// the complete directory will be skipped, and unignores for
			// contained files/directories will have no effect.
			'!wp-content/',
			'!wp-content/plugins/',
			'!wp-content/plugins/semla/',
			'!wp-content/plugins/semla/**/*',
			'wp-content/plugins/semla/blocks/**/*',
			'!wp-content/themes/',
			'!wp-content/themes/lax/',
			'!wp-content/themes/lax/**/*',
			'**/*.min.js',
		],
	},
	js.configs.recommended,
	eslintPluginPrettierRecommended,
	{
		files: ['**/*.js'],
		languageOptions: {
			globals: {
				...globals.browser,
			},

			ecmaVersion: 2017,
			sourceType: 'script',
		},
	},
	{
		basePath: 'wp-content/themes/lax',
		languageOptions: {
			ecmaVersion: 5,
		},
	},
]);
