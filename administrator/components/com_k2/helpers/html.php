<?php
/**
 * @version		3.0.0
 * @package		K2
 * @author		JoomlaWorks http://www.joomlaworks.net
 * @copyright	Copyright (c) 2006 - 2014 JoomlaWorks Ltd. All rights reserved.
 * @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 */

// no direct access
defined('_JEXEC') or die ;

require_once JPATH_ADMINISTRATOR.'/components/com_k2/models/model.php';
K2Model::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_k2/models');

/**
 * K2 HTML helper class.
 */

class K2HelperHTML
{

	public static function state($name = 'state', $value = null, $all = false, $trashed = false, $type = 'radio', $boolean = false)
	{
		$options = array();
		if ($all)
		{
			$options[] = JHTML::_('select.option', '', JText::_($all));
		}
		$options[] = JHTML::_('select.option', 1, JText::_('K2_PUBLISHED'));
		$options[] = JHTML::_('select.option', 0, JText::_('K2_UNPUBLISHED'));
		if ($trashed)
		{
			$options[] = JHTML::_('select.option', -1, JText::_('K2_TRASHED'));
		}
		if ($type == 'select')
		{
			return JHtml::_('select.genericlist', $options, $name, '', 'value', 'text', $value);
		}
		else
		{
			return self::radiolist($options, $name, $value, $boolean);
		}
	}

	public static function featured($name = 'featured', $value = null)
	{
		$options = array();
		$options[] = JHTML::_('select.option', '', JText::_('K2_ALL'));
		$options[] = JHTML::_('select.option', 1, JText::_('K2_YES'));
		$options[] = JHTML::_('select.option', 0, JText::_('K2_NO'));
		return self::radiolist($options, $name, $value);
	}

	public static function language($name = 'language', $value = null, $none = false)
	{
		$options = JHtml::_('contentlanguage.existing', true, true);
		if ($none)
		{
			array_unshift($options, JHtml::_('select.option', '', JText::_($none)));
		}
		return JHtml::_('select.genericlist', $options, $name, '', 'value', 'text', $value);
	}

	public static function sorting($options = array(), $name = 'sorting', $value = null)
	{
		$list = array();
		foreach ($options as $optionLabel => $optionValue)
		{
			$list[] = JHTML::_('select.option', $optionValue, JText::_($optionLabel));
		}

		return JHtml::_('select.genericlist', $list, $name, '', 'value', 'text', $value);
	}

	public static function categories($name = 'catid', $value = null, $none = false, $exclude = null, $attributes = '', $recursive = false, $valueProperty = 'id', $inheritance = false, $batch = false)
	{
		$model = K2Model::getInstance('Categories', 'K2Model');
		$model->setState('sorting', 'ordering');
		$rows = $model->getRows();
		$options = array();
		if ($none)
		{
			$noneValue = $none === 'K2_LEAVE_UNCHANGED' ? '' : '0';
			$options[] = JHtml::_('select.option', $noneValue, JText::_($none));
		}
		if ($inheritance)
		{
			$options[] = JHtml::_('select.option', '1', JText::_('K2_FROM_K2_CATEGORY_PARAMETERS'));
		}
		if ($batch)
		{
			$options[] = JHtml::_('select.option', '1', JText::_('K2_NONE'));
		}
		foreach ($rows as $row)
		{
			if ($exclude != $row->id)
			{
				$title = str_repeat('-', intval($row->level) - 1).$row->title;
				if ($row->state == -1)
				{
					$title .= JText::_('K2_TRASHED_CATEGORY_NOTICE');
				}
				else if ($row->state == 0)
				{
					$title .= JText::_('K2_UNPUBLISHED_CATEGORY_NOTICE');
				}
				$optionValue = $row->$valueProperty;
				$options[] = JHtml::_('select.option', $optionValue, $title);
			}

		}

		$output = JHtml::_('select.genericlist', $options, $name, $attributes, 'value', 'text', $value);
		if ($recursive)
		{
			$options = array();
			$options[] = JHtml::_('select.option', '0', JText::_('K2_NO'));
			$options[] = JHtml::_('select.option', '1', JText::_('K2_YES'));
			$output .= '<label>'.JText::_($recursive->label).'</label>'.K2HelperHTML::radiolist($options, $recursive->name, $recursive->value, true);
		}
		return $output;
	}

	public static function search($name = 'search')
	{
		return '<input type="text" name="'.$name.'" autocomplete="off" />';
	}

	public static function template($name = 'template', $value = null)
	{
		jimport('joomla.filesystem.folder');
		$application = JFactory::getApplication();
		$componentPath = JPATH_SITE.'/components/com_k2/templates';
		$componentFolders = JFolder::folders($componentPath);
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select($db->quoteName('template'))->from($db->quoteName('#__template_styles'))->where($db->quoteName('client_id').' = 0')->where($db->quoteName('home').' = 1');
		$db->setQuery($query);
		$defaultemplate = $db->loadResult();

		if (JFolder::exists(JPATH_SITE.'/templates/'.$defaultemplate.'/html/com_k2/templates'))
		{
			$templatePath = JPATH_SITE.'/templates/'.$defaultemplate.'/html/com_k2/templates';
		}
		else
		{
			$templatePath = JPATH_SITE.'/templates/'.$defaultemplate.'/html/com_k2';
		}

		if (JFolder::exists($templatePath))
		{
			$templateFolders = JFolder::folders($templatePath);
			$folders = @array_merge($templateFolders, $componentFolders);
			$folders = @array_unique($folders);
		}
		else
		{
			$folders = $componentFolders;
		}

		$exclude = 'default';
		$options = array();
		foreach ($folders as $folder)
		{
			if (preg_match(chr(1).$exclude.chr(1), $folder))
			{
				continue;
			}
			$options[] = JHtml::_('select.option', $folder, $folder);
		}

		array_unshift($options, JHtml::_('select.option', '', '-- '.JText::_('K2_USE_DEFAULT').' --'));
		return JHtml::_('select.genericlist', $options, $name, '', 'value', 'text', $value);
	}

	public static function extraFieldsGroups($name = 'extra_fields_group', $value = null, $none = false, $attributes = '', $scope = null)
	{
		$options = array();
		if ($none)
		{
			$options[] = JHtml::_('select.option', '', JText::_($none));
		}
		$model = K2Model::getInstance('ExtraFieldsGroups', 'K2Model');
		$model->setState('sorting', 'name');
		if (!is_null($scope))
		{
			$model->setState('scope', $scope);
		}
		$rows = $model->getRows();
		foreach ($rows as $row)
		{
			$options[] = JHtml::_('select.option', $row->id, $row->name);
		}
		return JHtml::_('select.genericlist', $options, $name, $attributes, 'value', 'text', $value);
	}
	
	public static function k2ExtraFieldsGroups($name = 'extra_fields_group', $field, $value = null, $none = false)
	{
		return '<div data-widget="'.$name.'" data-value="'.implode ('|',$value).'">'.K2HelperHTML::extraFieldsGroups($field, $value, $none, array('data-role' => 'extra-field-groups-selector'), 'item').'<div data-role="list"></div></div>';
	}

	public static function extraFieldsScopes($name = 'scope', $value = null, $attributes = '', $none = false)
	{
		$options = array();
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/helpers/extrafields.php';
		$rows = K2HelperExtraFields::getScopes();
		if ($none)
		{
			$options[] = JHtml::_('select.option', '', JText::_($none));
		}
		foreach ($rows as $row)
		{
			$options[] = JHtml::_('select.option', $row, JText::_('K2_EXTRA_FIELD_SCOPE_'.strtoupper($row)));
		}
		return JHtml::_('select.genericlist', $options, $name, $attributes, 'value', 'text', $value);
	}

	public static function extraFieldsTypes($name = 'extra_fields_type', $value = null, $none = false, $attributes = '')
	{
		$options = array();
		if ($none)
		{
			$options[] = JHtml::_('select.option', '', JText::_($none));
		}
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/helpers/extrafields.php';

		$rows = K2HelperExtraFields::getTypes();

		foreach ($rows as $row)
		{
			$options[] = JHtml::_('select.option', $row, JText::_('K2_EXTRA_FIELD_TYPE_'.strtoupper($row)));
		}
		return JHtml::_('select.genericlist', $options, $name, $attributes, 'value', 'text', $value);
	}

	public static function usergroups($name = 'usergroups', $value = null, $none = false, $attributes = '')
	{
		$options = array();
		if ($none)
		{
			$options[] = JHtml::_('select.option', '', JText::_($none));
		}

		$model = K2Model::getInstance('UserGroups', 'K2Model');
		$rows = $model->getRows();

		foreach ($rows as $row)
		{
			$options[] = JHtml::_('select.option', $row->id, str_repeat('-', $row->level).$row->title);
		}
		return JHtml::_('select.genericlist', $options, $name, $attributes, 'value', 'text', $value);
	}

	public static function tags($name = 'tags', $value = null, $none = false, $attributes = '')
	{
		$options = array();
		if ($none)
		{
			$options[] = JHtml::_('select.option', '', JText::_($none));
		}

		$model = K2Model::getInstance('Tags', 'K2Model');
		$rows = $model->getRows();

		foreach ($rows as $row)
		{
			$options[] = JHtml::_('select.option', $row->id, $row->name);
		}
		return JHtml::_('select.genericlist', $options, $name, $attributes, 'value', 'text', $value);
	}

	public static function gender($name = 'gender', $value = null, $attributes = '')
	{
		$options = array();
		$options[] = JHtml::_('select.option', 'm', JText::_('K2_MALE'));
		$options[] = JHtml::_('select.option', 'f', JText::_('K2_FEMALE'));
		return self::radiolist($options, $name, $value);
	}

	public static function author($name = 'author', $value = null, $none = false, $attributes = '')
	{
		$options = array();
		if ($none)
		{
			$options[] = JHtml::_('select.option', '', JText::_($none));
		}

		$model = K2Model::getInstance('Users', 'K2Model');
		$model->setState('limit', 1);
		$model->setState('sorting', 'name');
		$rows = $model->getRows();

		foreach ($rows as $row)
		{
			$options[] = JHtml::_('select.option', $row->id, $row->name);
		}
		return JHtml::_('select.genericlist', $options, $name, $attributes, 'value', 'text', $value);
	}

	public static function radiolist($options, $name, $value = '', $boolean = false, $attributes = '')
	{
		$output = $boolean ? '<div class="jw--state">' : '';
		foreach ($options as $key => $option)
		{
			$active = (string)$option->value == (string)$value;
			$checked = $active ? 'checked="checked"' : '';
			$class = $active ? 'class="jw--radio jw--radio__checked"' : 'class="jw--radio"';
			$id = $name.'_'.$key;
			$output .= '<input type="radio" class="visuallyhidden" name="'.$name.'" id="'.$id.'" '.$checked.' value="'.$option->value.'" '.$attributes.' /><label for="'.$id.'" '.$class.'>'.$option->text.'</label>';
		}
		if ($boolean)
		{
			$output .= '</div>';
		}
		return $output;
	}

	public static function mediaProviders($name = 'provider', $attributes = '')
	{
		$providers = array();
		$filename = JPATH_SITE.'/plugins/content/jw_allvideos/jw_allvideos/includes/sources.php';
		if (file_exists($filename))
		{
			require $filename;
			$thirdPartyProviders = array_slice($tagReplace, 40);
			$providersTmp = array_keys($thirdPartyProviders);
			foreach ($providersTmp as $providerTmp)
			{
				if (stristr($providerTmp, 'google|google.co.uk|google.com.au|google.de|google.es|google.fr|google.it|google.nl|google.pl') !== false)
				{
					$provider = 'google';
				}
				elseif (stristr($providerTmp, 'spike|ifilm') !== false)
				{
					$provider = 'spike';
				}
				else
				{
					$provider = $providerTmp;
				}
				$providers[] = JHtml::_('select.option', $provider, ucfirst($provider));
			}
		}
		return JHtml::_('select.genericlist', $providers, $name, $attributes);
	}

}
