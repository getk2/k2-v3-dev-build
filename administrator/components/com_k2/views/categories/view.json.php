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

require_once JPATH_ADMINISTRATOR.'/components/com_k2/views/view.php';

/**
 * Categories JSON view.
 */

class K2ViewCategories extends K2View
{

	/**
	 * Builds the response variables needed for rendering a list.
	 * Usually there will be no need to override this function.
	 *
	 * @return void
	 */

	public function show()
	{
		// Set title
		$this->setTitle('K2_CATEGORIES');

		// Set user states
		$this->setUserStates();

		// Set pagination
		$this->setPagination();

		// Set rows
		$this->setRows();

		// Set filters
		$this->setFilters();

		// Set toolbar
		$this->setToolbar();

		// Set menu
		$this->setMenu();

		// Set Actions
		$this->setListActions();

		// Set Batch Actions
		$this->setBatchActions();

		// Render
		parent::render();
	}

	/**
	 * Builds the response variables needed for rendering a form.
	 * Usually there will be no need to override this function.
	 *
	 * @param integer $id	The id of the resource to load.
	 *
	 * @return void
	 */

	public function edit($id = null)
	{
		// Set title
		$this->setTitle($id ? 'K2_EDIT_CATEGORY' : 'K2_ADD_CATEGORY');

		// Set row
		$this->setRow($id);

		// Set form
		$this->setForm();

		// Set menu
		$this->setMenu('edit');

		// Set Actions
		$this->setFormActions();

		// Render
		parent::render();
	}

	protected function setUserStates()
	{
		$this->setUserState('persist', 1, 'int');
		$this->setUserState('limit', JFactory::getApplication()->get('list_limit'), 'int', $this->getUserState('persist'));
		$this->setUserState('page', 1, 'int', $this->getUserState('persist'));
		$this->setUserState('search', '', 'string');
		$this->setUserState('access', 0, 'int');
		$this->setUserState('state', '', 'cmd');
		$this->setUserState('language', '', 'string');
		$this->setUserState('root', 0, 'int', $this->getUserState('persist'));
		$this->setUserState('parent', 0, 'int', $this->getUserState('persist'));
		$this->setUserState('sorting', 'ordering', 'string');
	}

	protected function setFilters()
	{

		// Root filter
		K2Response::addFilter('root', JText::_('K2_ROOT'), K2HelperHTML::categories('root', null, 'K2_NONE'), true, 'header');

		// Access filter
		K2Response::addFilter('access', JText::_('K2_ACCESS'), JHtml::_('access.level', 'access', null, '', array(JHtml::_('select.option', '0', JText::_('K2_ANY')))), false, 'header');

		// Language filter
		K2Response::addFilter('language', JText::_('K2_LANGUAGE'), K2HelperHTML::language('language', '', 'K2_ANY'), false, 'header');

		// Search filter
		K2Response::addFilter('search', JText::_('K2_SEARCH'), K2HelperHTML::search(), false, 'sidebar');

		// State filter
		K2Response::addFilter('state', JText::_('K2_STATE'), K2HelperHTML::state('state', null, 'K2_ANY', true), true, 'sidebar');

	}

	protected function setToolbar()
	{
		// Check permissions for the current rows to determine if we should show the state togglers and the delete button
		$rows = K2Response::getRows();
		$canEditState = false;
		$canDelete = false;
		foreach ($rows as $row)
		{
			if ($row->canEditState)
			{
				$canEditState = true;
			}
			if ($row->canDelete)
			{
				$canDelete = true;
			}
		}
		if ($canEditState)
		{
			K2Response::addToolbarAction('publish', 'K2_PUBLISH', array(
				'data-state' => 'state',
				'data-value' => '1',
				'data-action' => 'set-state'
			));
			K2Response::addToolbarAction('unpublish', 'K2_UNPUBLISH', array(
				'data-state' => 'state',
				'data-value' => '0',
				'data-action' => 'set-state'
			));
			K2Response::addToolbarAction('trash', 'K2_TRASH', array(
				'data-state' => 'state',
				'data-value' => '-1',
				'data-action' => 'set-state'
			));
		}
		K2Response::addToolbarAction('batch', 'K2_BATCH', array('data-action' => 'batch'));
		if ($canDelete)
		{
			K2Response::addToolbarAction('remove', 'K2_DELETE', array('data-action' => 'remove'));
		}
	}

	protected function setFormFields(&$form, $row)
	{
		$form->state = K2HelperHTML::state('state', $row->state, false, true, 'radio', true);
		$form->language = K2HelperHTML::language('language', $row->language);
		$form->access = JHtml::_('access.level', 'access', $row->access, '', false);
		$form->parent = K2HelperHTML::categories('parent_id', $row->parent_id, 'K2_NONE', $row->id);
		$form->inheritance = K2HelperHTML::categories('inheritance', $row->inheritance, 'K2_NONE', $row->id, '', false, 'id', true);
		$form->template = K2HelperHTML::template('template', $row->template);
		$form->extrafieldgroups = K2HelperHTML::k2ExtraFieldsGroups('extrafieldgroups','params[catExtraFieldGroups]', $row->params->catExtraFieldGroups, ' ');

		require_once JPATH_ADMINISTRATOR.'/components/com_k2/classes/editor.php';
		$config = JFactory::getConfig();
		$editor = K2Editor::getInstance($config->get('editor'));
		$form->description = $editor->display('description', $row->description, '100%', '300', '40', '5');
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/helpers/extrafields.php';
		$form->extraFields = K2HelperExtraFields::getCategoryExtraFieldsGroups($row->extra_fields);

		// Associations
		$associations = new stdClass;
		$associations->enabled = JLanguageAssociations::isEnabled();
		$associations->languages = array();
		if ($associations->enabled)
		{
			$languages = JLanguageHelper::getLanguages('lang_code');
			foreach ($languages as $tag => $language)
			{
				if (empty($row->language) || $tag != $row->language)
				{
					$lang = new stdClass;
					$lang->title = $language->title;
					$lang->code = $language->lang_code;
					$lang->associated = new stdClass;
					$lang->associated->title = '';
					$lang->associated->id = '';
					if (isset($row->associations) && is_array($row->associations) && isset($row->associations[$language->lang_code]))
					{
						$associated = $row->associations[$language->lang_code];
						$lang->associated->title = $associated->title;
						$lang->associated->id = (int)$associated->id;
					}
					$associations->languages[] = $lang;
				}
			}
		}
		$form->associations = $associations;
	}

	/**
	 * Hook for children views to allow them set the menu for the list requests.
	 * Children views usually will not need to override this method.
	 *
	 * @return void
	 */
	protected function setListActions()
	{
		$user = JFactory::getUser();
		if ($user->authorise('k2.category.create', 'com_k2'))
		{
			K2Response::addAction('add', 'K2_ADD', array('data-action' => 'add'));
		}
	}

	protected function setBatchActions()
	{
		K2Response::addBatchAction('access', 'K2_ACCESS', JHtml::_('access.level', 'access', null, '', array(JHtml::_('select.option', '', JText::_('K2_LEAVE_UNCHANGED')))));
		K2Response::addBatchAction('language', 'K2_LANGUAGE', K2HelperHTML::language('language', '', 'K2_LEAVE_UNCHANGED'));
		K2Response::addBatchAction('parent', 'K2_PARENT', K2HelperHTML::categories('parent_id', '', 'K2_LEAVE_UNCHANGED', false, '', false, 'id', false, true));
		K2Response::addBatchAction('inheritance', 'K2_INHERITANCE', K2HelperHTML::categories('inheritance', '', 'K2_LEAVE_UNCHANGED', false, '', false, 'id', true));
	}

	protected function prepareRows($rows)
	{
		// Load items counter in just two queries
		K2Categories::countItems($rows);

		foreach ($rows as $row)
		{
			$row->inheritFrom = $row->getInheritFrom();
		}
	}

	protected function prepareRow($row)
	{
		
		// State
		if (!$row->id)
		{
			$row->state = 1;
		}
		
		// Associations
		if (JLanguageAssociations::isEnabled())
		{
			$row->associations = array();
			if ($row->id)
			{
				require_once JPATH_SITE.'/components/com_k2/helpers/association.php';
				$associations = K2HelperAssociation::getCategoryAssociations($row->id);
				foreach ($associations as $tag => $association)
				{
					$row->associations[$tag] = $association;
				}
			}
		}

	}

}
