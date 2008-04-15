<?php
/**
*
* @package acp
* @version $Id$
* @copyright (c) 2005 phpBB Group - acp_board.php
* @copyright (c) 2008 Andreas Fischer (bantu@phpbb.de)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* @package acp
*/
class acp_overwrite
{
	var $u_action;
	var $new_config = array();

	function main($id, $mode)
	{
		global $db, $user, $auth, $cache, $template;
		global $config, $phpbb_root_path, $phpbb_admin_path, $phpEx;

		if (!$auth->acl_get('a_board') || !$auth->acl_get('a_user'))
		{
			trigger_error($user->lang['NOT_AUTHORISED'] . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$user->add_lang('acp/board');

		$submit = (isset($_POST['submit'])) ? true : false;

		$form_key = 'acp_time';
		add_form_key($form_key);

		switch ($mode)
		{
			case 'time':
				$display_vars = array(
					'title'	=> 'ACP_OVERWRITE_TIME_SETTINGS',
					'vars'	=> array(
						'legend1'				=> 'ACP_BOARD_SETTINGS',
						'default_dateformat'	=> array('lang' => 'DEFAULT_DATE_FORMAT',	'validate' => 'string',	'type' => 'custom', 'method' => 'dateformat_select', 'explain' => true),
						'overwrite_dateformat'	=> array('lang' => 'OVERWRITE_SETTINGS',	'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => true),

						'legend2'				=> 'ACP_BOARD_SETTINGS',
						'board_timezone'		=> array('lang' => 'SYSTEM_TIMEZONE',		'validate' => 'string',	'type' => 'select', 'function' => 'tz_select', 'params' => array('{CONFIG_VALUE}', 1), 'explain' => false),
						'overwrite_timezone'	=> array('lang' => 'OVERWRITE_SETTINGS',	'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => true),

						'legend3'				=> 'ACP_BOARD_SETTINGS',
						'board_dst'				=> array('lang' => 'SYSTEM_DST',			'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => false),
						'overwrite_dst'			=> array('lang' => 'OVERWRITE_SETTINGS',	'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => true),
					)
				);
			break;

			default:
				trigger_error('NO_MODE', E_USER_ERROR);
			break;
		}

		$this->new_config = $config;
		$cfg_array = (isset($_REQUEST['config'])) ? utf8_normalize_nfc(request_var('config', array('' => ''), true)) : $this->new_config;
		$error = array();

		// We validate the complete config if whished
		validate_config_vars($display_vars['vars'], $cfg_array, $error);

		if ($submit && !check_form_key($form_key))
		{
			$error[] = $user->lang['FORM_INVALID'];
		}
		// Do not write values if there is an error
		if (sizeof($error))
		{
			$submit = false;
		}

		// $request_value => array($config_name, $user_column)
		$overwrite_values = array(
			'overwrite_dateformat'	=> array('default_dateformat', 'user_dateformat'),
			'overwrite_timezone'	=> array('board_timezone', 'user_timezone'),
			'overwrite_dst'			=> array('board_dst', 'user_dst'),
		);

		$overwritten = false;

		// We go through the display_vars to make sure no one is trying to set variables he/she is not allowed to...
		foreach ($display_vars['vars'] as $config_name => $null)
		{
			if (!isset($cfg_array[$config_name]) || strpos($config_name, 'legend') !== false)
			{
				continue;
			}

			if (isset($overwrite_values[$config_name]))
			{
				// Values already validated, using 1 instead of true ...
				if ($submit && $cfg_array[$config_name] === 1)
				{
					$column = $overwrite_values[$config_name][1];
					$value = $cfg_array[$overwrite_values[$config_name][0]];

					$this->update_user_table($column, $value);

					$overwritten = true;
				}

				continue;
			}

			$this->new_config[$config_name] = $config_value = $cfg_array[$config_name];

			if ($submit)
			{
				set_config($config_name, $config_value);
			}
		}

		if ($submit)
		{
			if ($overwritten)
			{
				add_log('admin', 'LOG_CONFIG_OVERWRITE_' . strtoupper($mode));

				$cache->destroy('sql', USERS_TABLE);
			}
			else
			{
				add_log('admin', 'LOG_CONFIG_SETTINGS');
			}
			
			trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
		}

		$this->tpl_name = 'acp_board';
		$this->page_title = $display_vars['title'];

		$template->assign_vars(array(
			'L_TITLE'			=> $user->lang[$display_vars['title']],
			'L_TITLE_EXPLAIN'	=> $user->lang[$display_vars['title'] . '_EXPLAIN'],

			'S_ERROR'			=> (sizeof($error)) ? true : false,
			'ERROR_MSG'			=> implode('<br />', $error),

			'U_ACTION'			=> $this->u_action)
		);

		// Output relevant page
		foreach ($display_vars['vars'] as $config_key => $vars)
		{
			if (!is_array($vars) && strpos($config_key, 'legend') === false)
			{
				continue;
			}

			if (strpos($config_key, 'legend') !== false)
			{
				$template->assign_block_vars('options', array(
					'S_LEGEND'		=> true,
					'LEGEND'		=> (isset($user->lang[$vars])) ? $user->lang[$vars] : $vars)
				);

				continue;
			}

			if (strpos($config_key, 'overwrite') !== false)
			{
				// Always select 'No'.
				$this->new_config[$config_key] = 0;
			}

			$type = explode(':', $vars['type']);

			$l_explain = '';
			if ($vars['explain'] && isset($vars['lang_explain']))
			{
				$l_explain = (isset($user->lang[$vars['lang_explain']])) ? $user->lang[$vars['lang_explain']] : $vars['lang_explain'];
			}
			else if ($vars['explain'])
			{
				$l_explain = (isset($user->lang[$vars['lang'] . '_EXPLAIN'])) ? $user->lang[$vars['lang'] . '_EXPLAIN'] : '';
			}
			
			$content = build_cfg_template($type, $config_key, $this->new_config, $config_key, $vars);
			
			if (empty($content))
			{
				continue;
			}
			
			$template->assign_block_vars('options', array(
				'KEY'			=> $config_key,
				'TITLE'			=> (isset($user->lang[$vars['lang']])) ? $user->lang[$vars['lang']] : $vars['lang'],
				'S_EXPLAIN'		=> $vars['explain'],
				'TITLE_EXPLAIN'	=> $l_explain,
				'CONTENT'		=> $content,
				)
			);

			unset($display_vars['vars'][$config_key]);
		}
	}

	/**
	* Select default dateformat
	*/
	function dateformat_select($value, $key)
	{
		global $user, $config;

		// Let the format_date function operate with the acp values
		$old_tz = $user->timezone;
		$old_dst = $user->dst;

		$user->timezone = $config['board_timezone'];
		$user->dst = $config['board_dst'];

		$dateformat_options = '';

		foreach ($user->lang['dateformats'] as $format => $null)
		{
			$dateformat_options .= '<option value="' . $format . '"' . (($format == $value) ? ' selected="selected"' : '') . '>';
			$dateformat_options .= $user->format_date(time(), $format, false) . ((strpos($format, '|') !== false) ? $user->lang['VARIANT_DATE_SEPARATOR'] . $user->format_date(time(), $format, true) : '');
			$dateformat_options .= '</option>';
		}

		$dateformat_options .= '<option value="custom"';
		if (!isset($user->lang['dateformats'][$value]))
		{
			$dateformat_options .= ' selected="selected"';
		}
		$dateformat_options .= '>' . $user->lang['CUSTOM_DATEFORMAT'] . '</option>';

		// Reset users date options
		$user->timezone = $old_tz;
		$user->dst = $old_dst;

		return "<select name=\"dateoptions\" id=\"dateoptions\" onchange=\"if (this.value == 'custom') { document.getElementById('" . addslashes($key) . "').value = '" . addslashes($value) . "'; } else { document.getElementById('" . addslashes($key) . "').value = this.value; }\">$dateformat_options</select>
		<input type=\"text\" name=\"config[$key]\" id=\"$key\" value=\"$value\" maxlength=\"30\" />";
	}

	/**
	* Update User Table
	* @access private
	*/
	function update_user_table($key, $value)
	{
		global $db;

		$sql = 'UPDATE ' . USERS_TABLE . "
			SET $key = '$value'";
		$db->sql_query($sql);
	}
}

?>