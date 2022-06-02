<?php
/**
 * This is the template that displays all of the <head> section and everything up until
 * the main content area
 * 
 * Note: quite a bit of info is hardcoded. Since this is a custom theme
 * for only this site, and hardcoding stops database lookups
 *
 * If the CSS changes then update the version in ?ver=1.0
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 * @package Lax
 */
// if comments are enabled then uncomment this
// if (is_singular() && comments_open() && get_option('thread_comments')) {
// 	wp_enqueue_script('comment-reply');
// }
?><!DOCTYPE html>
<html class="no-js" lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php wp_head(); ?>
<?php if (defined('SEMLA_ANALYTICS')) : ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= SEMLA_ANALYTICS ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= SEMLA_ANALYTICS ?>');</script>
<?php endif; ?>
<script>var d=document.documentElement;"addEventListener"in window&&(d.className="js"),"undefined"!=typeof SVGRect&&(d.className+=" svg");</script>
<link rel="icon" type="image/png" sizes="32x32" href="/icon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/icon-16.png">
<link rel="shortcut icon" href="/favicon.ico">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
</head>
<body <?php body_class(); ?>>
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
