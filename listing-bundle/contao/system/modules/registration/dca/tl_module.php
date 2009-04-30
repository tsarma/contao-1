<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight webCMS
 * Copyright (C) 2005-2009 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at http://www.gnu.org/licenses/.
 *
 * PHP version 5
 * @copyright  Leo Feyer 2005-2009
 * @author     Leo Feyer <leo@typolight.org>
 * @package    Registration
 * @license    LGPL
 * @filesource
 */


/**
 * Add selectors to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'reg_assignDir';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'reg_activate';


/**
 * Add palettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['registration'] = '{title_legend},name,headline,type;{config_legend},editable,newsletters,disableCaptcha;{account_legend},reg_groups,reg_allowLogin,reg_assignDir;{redirect_legend},jumpTo;{email_legend:hide},reg_activate;{template_legend:hide},memberTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['lostPassword'] = '{title_legend},name,headline,type;{config_legend},reg_skipName,disableCaptcha;{redirect_legend},jumpTo;{email_legend:hide},reg_jumpTo,reg_password;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';


/**
 * Add subpalettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['reg_assignDir'] = 'reg_homeDir';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['reg_activate'] = 'reg_jumpTo,reg_text';


/**
 * Add fields to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['disableCaptcha'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_module']['disableCaptcha'],
	'exclude'       => true,
	'inputType'     => 'checkbox'
);

$GLOBALS['TL_DCA']['tl_module']['fields']['reg_groups'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_module']['reg_groups'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'foreignKey'    => 'tl_member_group.name',
	'eval'          => array('multiple'=>true)
);

$GLOBALS['TL_DCA']['tl_module']['fields']['reg_allowLogin'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_module']['reg_allowLogin'],
	'exclude'       => true,
	'inputType'     => 'checkbox'
);

$GLOBALS['TL_DCA']['tl_module']['fields']['reg_skipName'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_module']['reg_skipName'],
	'exclude'       => true,
	'inputType'     => 'checkbox'
);

$GLOBALS['TL_DCA']['tl_module']['fields']['reg_assignDir'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_module']['reg_assignDir'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('submitOnChange'=>true)
);

$GLOBALS['TL_DCA']['tl_module']['fields']['reg_homeDir'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_module']['reg_homeDir'],
	'exclude'       => true,
	'inputType'     => 'fileTree',
	'eval'          => array('fieldType'=>'radio', 'tl_class'=>'clr')
);

$GLOBALS['TL_DCA']['tl_module']['fields']['reg_activate'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_module']['reg_activate'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('submitOnChange'=>true)
);

$GLOBALS['TL_DCA']['tl_module']['fields']['reg_jumpTo'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_module']['reg_jumpTo'],
	'exclude'       => true,
	'inputType'     => 'pageTree',
	'eval'          => array('fieldType'=>'radio')
);

$GLOBALS['TL_DCA']['tl_module']['fields']['reg_text'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_module']['reg_text'],
	'exclude'       => true,
	'inputType'     => 'textarea',
	'eval'          => array('style'=>'height:120px;', 'decodeEntities'=>true, 'alwaysSave'=>true),
	'load_callback' => array
	(
		array('tl_module_registration', 'getActivationDefault')
	)
);

$GLOBALS['TL_DCA']['tl_module']['fields']['reg_password'] = array
(
	'label'         => &$GLOBALS['TL_LANG']['tl_module']['reg_password'],
	'exclude'       => true,
	'inputType'     => 'textarea',
	'eval'          => array('style'=>'height:120px;', 'decodeEntities'=>true, 'alwaysSave'=>true),
	'load_callback' => array
	(
		array('tl_module_registration', 'getPasswordDefault')
	)
);


/**
 * Class tl_module_registration
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 * @copyright  Leo Feyer 2005-2009
 * @author     Leo Feyer <leo@typolight.org>
 * @package    Controller
 */
class tl_module_registration extends Backend
{

	/**
	 * Load the default activation text
	 * @param string
	 * @return string
	 */
	public function getActivationDefault($varValue)
	{
		if (!trim($varValue))
		{
			$varValue = (is_array($GLOBALS['TL_LANG']['tl_module']['emailText']) ? $GLOBALS['TL_LANG']['tl_module']['emailText'][1] : $GLOBALS['TL_LANG']['tl_module']['emailText']);
		}

		return $varValue;
	}


	/**
	 * Load the default password text
	 * @param string
	 * @return string
	 */
	public function getPasswordDefault($varValue)
	{
		if (!trim($varValue))
		{
			$varValue = (is_array($GLOBALS['TL_LANG']['tl_module']['passwordText']) ? $GLOBALS['TL_LANG']['tl_module']['passwordText'][1] : $GLOBALS['TL_LANG']['tl_module']['passwordText']);
		}

		return $varValue;
	}
}

?>