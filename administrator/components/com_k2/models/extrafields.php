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

class K2ModelExtraFields extends K2Model
{
	public function getRows()
	{
		// Get database
		$db = $this->getDBO();

		// Get query
		$query = $db->getQuery(true);

		// Select rows
		$query->select($db->quoteName('extraField').'.*')->from($db->quoteName('#__k2_extra_fields', 'extraField'));

		// Join over the groups
		$query->select($db->quoteName('group.name', 'groupName'));
		$query->leftJoin($db->quoteName('#__k2_extra_fields_groups', 'group').' ON '.$db->quoteName('extraField.group').' = '.$db->quoteName('group.id'));

		// Set query conditions
		$this->setQueryConditions($query);

		// Set query sorting
		$this->setQuerySorting($query);

		// Hook for plugins
		$this->onBeforeSetQuery($query, 'com_k2.extrafields.list');

		// Set the query
		$db->setQuery($query, (int)$this->getState('limitstart'), (int)$this->getState('limit'));

		// Get rows
		$data = $db->loadAssocList();

		// Generate K2 resources instances from the result data.
		$rows = $this->getResources($data);

		// Return rows
		return (array)$rows;
	}

	public function countRows()
	{
		// Get database
		$db = $this->getDBO();

		// Get query
		$query = $db->getQuery(true);

		// Select statement
		$query->select('COUNT(*)')->from($db->quoteName('#__k2_extra_fields', 'extraField'));

		// Set query conditions
		$this->setQueryConditions($query);

		// Hook for plugins
		$this->onBeforeSetQuery($query, 'com_k2.extrafields.count');

		// Set the query
		$db->setQuery($query);

		// Get the result
		$total = $db->loadResult();

		// Return the result
		return (int)$total;
	}

	private function setQueryConditions(&$query)
	{
		$db = $this->getDBO();
		if (is_numeric($this->getState('state')))
		{
			$query->where($db->quoteName('extraField.state').' = '.(int)$this->getState('state'));
		}
		if ($this->getState('id'))
		{
			$id = $this->getState('id');
			if (is_array($id))
			{
				JArrayHelper::toInteger($id);
				$query->where($db->quoteName('extraField.id').' IN '.$id);
			}
			else
			{
				$query->where($db->quoteName('extraField.id').' = '.(int)$id);
			}
		}
		if ($this->getState('group'))
		{
			$query->where($db->quoteName('extraField.group').' = '.(int)$this->getState('group'));
		}
		if ($this->getState('type'))
		{
			$query->where($db->quoteName('extraField.type').' = '.$db->quote($this->getState('type')));
		}
		if ($this->getState('search'))
		{
			$search = JString::trim($this->getState('search'));
			$search = JString::strtolower($search);
			if ($search)
			{
				$search = $db->escape($search, true);
				$query->where('( LOWER('.$db->quoteName('extraField.name').') LIKE '.$db->Quote('%'.$search.'%', false).' 
				OR '.$db->quoteName('extraField.id').' = '.(int)$search.')');
			}
		}
	}

	private function setQuerySorting(&$query)
	{
		$sorting = $this->getState('sorting');
		switch($sorting)
		{
			default :
				$ordering = 'extraField.id';
				$direction = 'DESC';
				break;
			case 'id' :
			case 'id.reverse' :
				$ordering = 'extraField.id';
				$direction = $sorting == 'id' ? 'ASC' : 'DESC';
				break;
			case 'name' :
			case 'name.reverse' :
				$ordering = 'extraField.name';
				$direction = $sorting == 'name' ? 'ASC' : 'DESC';
				break;
			case 'group' :
			case 'group.reverse' :
				$ordering = 'groupName';
				$direction = $sorting == 'group' ? 'ASC' : 'DESC';
				break;
			case 'type' :
			case 'type.reverse' :
				$ordering = 'type';
				$direction = $sorting == 'type' ? 'ASC' : 'DESC';
				break;
			case 'state' :
			case 'state.reverse' :
				$ordering = 'extraField.state';
				$direction = $sorting == 'state' ? 'ASC' : 'DESC';
				break;
			case 'ordering' :
				$ordering = 'extraField.ordering';
				$direction = 'ASC';
				break;
		}

		// Append sorting
		$db = $this->getDbo();
		if (is_array($ordering))
		{
			$conditions = array();
			foreach ($ordering as $column)
			{
				$conditions[] = $db->quoteName($column).' '.$direction;
			}
			$query->order(implode(', ', $conditions));
		}
		else
		{
			$query->order($db->quoteName($ordering).' '.$direction);
		}

	}

	/**
	 * onBeforeSave method. Hook for chidlren model to prepare the data.
	 *
	 * @param   array  $data     The data to be saved.
	 * @param   JTable  $table   The table object.
	 *
	 * @return boolean
	 */
	protected function onBeforeSave(&$data, $table)
	{
		// Get database
		$db = $this->getDBO();
		
		// User
		$user = JFactory::getUser();

		// Permissions check
		if (!$user->authorise('k2.extrafields.manage'))
		{
			$this->setError(JText::_('K2_YOU_ARE_NOT_AUTHORIZED_TO_PERFORM_THIS_OPERATION'));
			return false;
		}

		// Ordering
		if (!$table->id)
		{
			$data['ordering'] = $table->getNextOrder($db->quoteName('group').' = '.(int)$data['group']);
		}

		return true;
	}

	/**
	 * onBeforeDelete method. 		Hook for chidlren model.
	 *
	 * @param   JTable  $table     	The table object.
	 *
	 * @return boolean
	 */

	protected function onBeforeDelete($table)
	{
		$user = JFactory::getUser();
		if (!$user->authorise('k2.extrafields.manage'))
		{
			$this->setError(JText::_('K2_YOU_ARE_NOT_AUTHORIZED_TO_PERFORM_THIS_OPERATION'));
			return false;
		}
		return true;
	}

}
