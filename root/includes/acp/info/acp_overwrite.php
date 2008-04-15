<?php
/**
*
* @package acp
* @version $Id$
* @copyright (c) 2005 phpBB Group
* @copyright (c) 2008 Andreas Fischer (bantu@phpbb.de)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @package module_install
*/
class acp_overwrite_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_overwrite',
			'title'		=> 'ACP_OVERWRITE_USER_SETTINGS',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'time'		=> array('title' => 'ACP_OVERWRITE_TIME_SETTINGS', 'auth' => 'acl_a_user', 'cat' => array('ACP_CAT_USERS')),
			),
		);
	}

	function install()
	{
	}

	function uninstall()
	{
	}
}

?>