<?php
namespace Semla;
// Autoload classes from namespace/class name. That way we can just reference
// the class without having to require the source. Files must be named the
// same as the class, in the directory of their namespace.
spl_autoload_register(function(string $class) {
	// does the class use the namespace prefix?
	$len = strlen(__NAMESPACE__);
	if (strncmp(__NAMESPACE__, $class, $len) !== 0) {
		return;
	}
	$relative_class = substr($class, $len);
	$file = __DIR__ . str_replace('\\', '/', $relative_class) . '.php';
	if (file_exists($file)) {
		require $file;
	}
});