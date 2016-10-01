<?php
/**
*
* @package phpBB Extension - Reassign First Post Russian
* @copyright (c) 2016 Sheer
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'REASSIGN_FIRST'			=> 'Сделать первым сообщением темы',
));
