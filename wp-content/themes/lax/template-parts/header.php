<?php
/**
 * Displays all of the <head> section and everything up until the main content
 * area
 *
 * Note: quite a bit of info is hardcoded. Since this is a custom theme
 * for only this site, and hardcoding stops database lookups
 */
?><!DOCTYPE html>
<html class="no-js" lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php wp_head(); ?>
<?php if (defined('SEMLA_ANALYTICS')) : ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= SEMLA_ANALYTICS ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= SEMLA_ANALYTICS ?>');</script>
<?php endif; ?>
<script>var d=document.documentElement;"addEventListener"in window&&(d.className="js"),"undefined"==typeof SVGRect&&(d.className+=" no-svg");</script>
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content">Skip to content</a>
<header class="main-header">
<div class="banner inner">
<a title="Display Menu" href="#menu" id="show-menu" role="button" aria-expanded="false">Menu</a>
<a title="South of England Men's Lacrosse Association Home" href="/" class="logo">SEMLA</a>
<?php
// wp_nav_menu executes 4 queries, which return a load of data, so rather than
// calling directly the menus are cached here.
lax_menu("popular");
?>
</div>
</header>
<nav class="menu-nav">
<div id="menu" class="inner">
<div id="overlay"></div>
<div class="menu-wrapper">
<button id="close-menu">Close Menu</button>
<?php
// wp_nav_menu executes 4 queries, which return a load of data, so rather than
// calling directly the menus are cached here.
lax_menu("main");
?>
</div>
<a id="search" title="Search the site" href="/search">Search</a>
</div>
</nav>
<div class="middle inner">
