<?php $esc_title = htmlspecialchars($title, ENT_NOQUOTES); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $esc_title ?></title>
<link href="<?= plugins_url( 'css/rest' . SEMLA_MIN . '.css',dirname(__DIR__,2)) ?>" rel="stylesheet">
</head>
<body>
<div id="content">
<?php if (!empty($parent)) :
	$route = $request->get_route();
	$parent_url = rest_url(substr($route, 0,strrpos($route, '/')));
?>
<nav>><a href="<?= $parent_url ?>"><?= $parent ?></a></nav>
<?php endif; ?>
<h1><?= $esc_title ?></h1>
