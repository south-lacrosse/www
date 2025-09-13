<?php
// This file is generated. Do not modify it manually.
return array(
	'attr-value' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'semla/attr-value',
		'version' => '1.0.0',
		'title' => 'Attribute/value pair',
		'category' => 'text',
		'icon' => 'feedback',
		'description' => 'An attribute/value pair, e.g. \'Colours: Purple\'. Use Contact block for contacts.',
		'attributes' => array(
			'attr' => array(
				'type' => 'string',
				'source' => 'text',
				'selector' => '.avf-name'
			),
			'value' => array(
				'type' => 'string',
				'source' => 'html',
				'selector' => '.avf-value'
			),
			'sameLine' => array(
				'type' => 'boolean',
				'default' => true
			)
		),
		'example' => array(
			'attributes' => array(
				'attr' => 'Colours',
				'value' => 'Purple'
			)
		),
		'supports' => array(
			'html' => false,
			'spacing' => array(
				'margin' => true,
				'padding' => true
			)
		),
		'editorScript' => 'file:./index.js'
	),
	'calendar' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'semla/calendar',
		'version' => '1.0.0',
		'title' => 'Google Calendar',
		'category' => 'widgets',
		'icon' => 'calendar-alt',
		'description' => 'Embed a Google Calendar',
		'attributes' => array(
			'cid' => array(
				'type' => 'string'
			),
			'enhanced' => array(
				'type' => 'boolean',
				'default' => false
			),
			'tagsList' => array(
				'type' => 'array',
				'default' => array(
					
				)
			)
		),
		'supports' => array(
			'html' => false,
			'multiple' => false
		),
		'editorScript' => 'file:./index.js'
	),
	'club-title' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'semla/club-title',
		'version' => '1.0.0',
		'title' => 'Club Title',
		'category' => 'theme',
		'description' => 'Club title, including logo (featured image)',
		'usesContext' => array(
			'postId',
			'postType'
		),
		'supports' => array(
			'html' => false,
			'multiple' => false
		),
		'editorScript' => 'file:./index.js'
	),
	'contact' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'semla/contact',
		'version' => '1.0.0',
		'title' => 'Contact',
		'category' => 'text',
		'icon' => 'admin-users',
		'description' => 'Contact with role and name, email, and telephone',
		'attributes' => array(
			'role' => array(
				'type' => 'string'
			),
			'name' => array(
				'type' => 'string',
				'default' => ''
			),
			'email' => array(
				'type' => 'string',
				'default' => ''
			),
			'tel' => array(
				'type' => 'string',
				'default' => ''
			),
			'sameLine' => array(
				'type' => 'boolean',
				'default' => true
			),
			'exclude' => array(
				'type' => 'boolean',
				'default' => false
			)
		),
		'example' => array(
			'attributes' => array(
				'role' => 'Captain',
				'name' => 'Fred Blogs',
				'email' => 'fred@gmail.com',
				'tel' => '07555 555555'
			)
		),
		'supports' => array(
			'html' => false,
			'spacing' => array(
				'margin' => true,
				'padding' => true
			)
		),
		'editorScript' => 'file:./index.js'
	),
	'data' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'semla/data',
		'version' => '1.0.0',
		'title' => 'SEMLA Data',
		'category' => 'widgets',
		'icon' => 'editor-table',
		'description' => 'Add SEMLA data such as league tables or fixtures',
		'attributes' => array(
			'src' => array(
				'type' => 'string',
				'default' => 'none'
			)
		),
		'supports' => array(
			'customClassName' => false,
			'className' => false,
			'html' => false,
			'multiple' => false
		),
		'editorScript' => 'file:./index.js'
	),
	'location' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'semla/location',
		'version' => '1.0.1',
		'title' => 'Location',
		'category' => 'widgets',
		'icon' => 'location',
		'description' => 'All information about a location - address, notes, map, directions',
		'attributes' => array(
			'address' => array(
				'type' => 'string',
				'source' => 'text',
				'selector' => 'p'
			),
			'mapperLinks' => array(
				'type' => 'boolean',
				'default' => true
			)
		),
		'supports' => array(
			'html' => false
		),
		'editorScript' => 'file:./index.js'
	),
	'map' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'semla/map',
		'version' => '1.0.0',
		'title' => 'Map',
		'category' => 'widgets',
		'description' => 'Google Map & directions',
		'attributes' => array(
			'lat' => array(
				'type' => 'number'
			),
			'long' => array(
				'type' => 'number'
			),
			'latLong' => array(
				'type' => 'string'
			)
		),
		'supports' => array(
			'html' => false
		),
		'editorScript' => 'file:./index.js'
	),
	'toc' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'semla/toc',
		'version' => '1.0.0',
		'title' => 'Table of Contents',
		'category' => 'theme',
		'icon' => 'list-view',
		'description' => 'Create a table of contents from any headings.',
		'attributes' => array(
			'title' => array(
				'type' => 'string',
				'source' => 'text',
				'selector' => 'h4',
				'default' => 'Contents'
			),
			'toc' => array(
				'type' => 'string',
				'source' => 'html',
				'selector' => 'nav>ul',
				'default' => ''
			)
		),
		'example' => array(
			'attributes' => array(
				'title' => 'Contents'
			)
		),
		'supports' => array(
			'html' => false,
			'multiple' => false,
			'spacing' => array(
				'margin' => true,
				'padding' => true
			)
		),
		'editorScript' => 'file:./index.js',
		'viewScript' => 'file:./view.js'
	),
	'website' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'semla/website',
		'version' => '1.0.0',
		'title' => 'Website Link',
		'category' => 'theme',
		'description' => 'Website link',
		'attributes' => array(
			'url' => array(
				'type' => 'string'
			)
		),
		'supports' => array(
			'html' => false,
			'multiple' => false
		),
		'parent' => array(
			'semla/club-title'
		),
		'editorScript' => 'file:./index.js'
	)
);
