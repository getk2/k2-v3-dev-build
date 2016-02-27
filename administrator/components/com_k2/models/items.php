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
require_once JPATH_ADMINISTRATOR.'/components/com_k2/models/categories.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/classes/filesystem.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/items.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/helpers/images.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/helpers/media.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/helpers/galleries.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/helpers/attachments.php';

class K2ModelItems extends K2Model
{

	private static $cache = array();

	public function getRows()
	{
		// Get database
		$db = $this->getDBO();

		// Get query
		$query = $db->getQuery(true);

		// Select rows
		$query->select($db->quoteName('item').'.*')->from($db->quoteName('#__k2_items', 'item'));

		// Join over the categories if required
		if ($this->getState('sorting') == 'ordering' || $this->getState('sorting') == 'ordering.reverse' || $this->getState('sorting') == 'category' || $this->getState('sorting') == 'category.reverse')
		{
			$joinType = $this->getState('sorting') == 'category' || $this->getState('sorting') == 'category.reverse' ? 'INNER' : 'LEFT';
			$query->join($joinType, $db->quoteName('#__k2_categories', 'category').' ON '.$db->quoteName('category.id').' = '.$db->quoteName('item.catid'));
		}

		// Join over the author
		$query->select($db->quoteName('author.name', 'authorName'));
		$joinType = $this->getState('sorting') == 'author' || $this->getState('sorting') == 'author.reverse' ? 'INNER' : 'LEFT';
		$query->join($joinType, $db->quoteName('#__users', 'author').' ON '.$db->quoteName('author.id').' = '.$db->quoteName('item.created_by'));

		// Join over the moderator
		$query->select($db->quoteName('moderator.name', 'moderatorName'));
		$joinType = $this->getState('sorting') == 'moderator' || $this->getState('sorting') == 'moderator.reverse' ? 'INNER' : 'LEFT';
		$query->join($joinType, $db->quoteName('#__users', 'moderator').' ON '.$db->quoteName('moderator.id').' = '.$db->quoteName('item.modified_by'));

		// Join over the hits
		$query->select($db->quoteName('stats.hits', 'hits'));
		$joinType = $this->getState('sorting') == 'hits' || $this->getState('sorting') == 'comments' || $this->getState('sorting') == 'hits.reverse' || $this->getState('sorting') == 'comments.reverse' ? 'INNER' : 'LEFT';
		$query->join($joinType, $db->quoteName('#__k2_items_stats', 'stats').' ON '.$db->quoteName('stats.itemId').' = '.$db->quoteName('item.id'));

		// Set query conditions
		$this->setQueryConditions($query);

		// Set query sorting
		$this->setQuerySorting($query);

		// Hook for plugins
		$this->onBeforeSetQuery($query, 'com_k2.items.list');

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
		$query->select('COUNT(*)')->from($db->quoteName('#__k2_items', 'item'));

		// Set a flag that this is a count query so we can optimize it
		$this->setState('query.count', true);

		// Set query conditions
		$this->setQueryConditions($query);

		// Restore the count query flag
		$this->setState('query.count', '');

		// Hook for plugins
		$this->onBeforeSetQuery($query, 'com_k2.items.count');

		// Set the query
		$db->setQuery($query);

		// Get the result
		$total = $db->loadResult();

		// Return the result
		return (int)$total;
	}

	public function batchCountRows()
	{
		// Get database
		$db = $this->getDBO();

		// Get query
		$query = $db->getQuery(true);

		// Select statement
		$query->select($db->quoteName('catid'));
		$query->select('COUNT(*) AS '.$db->quoteName('numOfItems'));
		$query->from($db->quoteName('#__k2_items', 'item'));

		// Set query conditions
		$this->setQueryConditions($query);

		// Group
		$query->group($db->quoteName('catid'));

		// Hook for plugins
		$this->onBeforeSetQuery($query, 'com_k2.categories.count');

		// Set the query
		$db->setQuery($query);

		// Get the result
		$rows = $db->loadObjectList();

		// Return the result
		return $rows;
	}

	private function setQueryConditions(&$query)
	{
		$db = $this->getDBO();

		if ($this->getState('site'))
		{
			// Get current datetime
			$date = JFactory::getDate()->toSql();

			// Get authorised view levels
			$viewlevels = array_unique(JFactory::getUser()->getAuthorisedViewLevels());

			// Published items only
			$this->setState('state', 1);
			$this->setState('publish_up', $date);
			$this->setState('publish_down', $date);

			// Set state for access
			$this->setState('access', $viewlevels);

			// Language filter
			$application = JFactory::getApplication();
			if ($application->isSite() && $application->getLanguageFilter())
			{
				$language = JFactory::getLanguage();
				$query->where($db->quoteName('item.language').' IN ('.$db->quote($language->getTag()).', '.$db->quote('*').')');
			}
		}

		// Shortcut method for setting the categoy filter
		if ($this->getState('category.filter'))
		{
			$filter = (object)$this->getState('category.filter');
			if (!isset($filter->recursive))
			{
				$filter->recursive = false;
			}
			if (isset($filter->enabled) && $filter->enabled)
			{
				$this->setState('category', $filter->categories);
				$this->setState('recursive', $filter->recursive);
			}
		}

		if ($this->getState('category'))
		{
			$categories = (array)$this->getState('category');
			$filter = K2ModelCategories::getCategoryFilter($categories, $this->getState('recursive'), $this->getState('site'));
			if (!count($filter))
			{
				$filter[] = 1;
			}
			$this->setState('categories.applied', $filter);
			$query->where($db->quoteName('item.catid').' IN ('.implode(',', $filter).')');
		}
		else if ($this->getState('site'))
		{
			$authorised = K2ModelCategories::getAuthorised();
			if (!count($authorised))
			{
				$authorised[] = 1;
			}
			$this->setState('categories.applied', $authorised);
			$query->where($db->quoteName('item.catid').' IN ('.implode(',', $authorised).')');
		}

		if ($this->getState('language'))
		{
			$query->where($db->quoteName('item.language').' = '.$db->quote($this->getState('language')));
		}
		if (is_numeric($this->getState('state')))
		{
			$operator = $this->getState('state.operator') ? $this->getState('state.operator') : '=';
			$query->where($db->quoteName('item.state').' '.$operator.' '.(int)$this->getState('state'));
		}
		if (is_numeric($this->getState('featured')))
		{
			$query->where($db->quoteName('item.featured').' = '.(int)$this->getState('featured'));
		}

		if ($this->getState('access'))
		{
			$access = $this->getState('access');
			if (is_array($access))
			{
				$access = array_unique($access);
				JArrayHelper::toInteger($access);
				$query->where($db->quoteName('item.access').' IN ('.implode(',', $access).')');
			}
			else
			{
				$query->where($db->quoteName('item.access').' = '.(int)$access);
			}
		}
		if ($this->getState('id'))
		{
			$id = $this->getState('id');
			if (is_array($id))
			{
				JArrayHelper::toInteger($id);
				$query->where($db->quoteName('item.id').' IN ('.implode(',', $id).')');
			}
			else
			{
				$query->where($db->quoteName('item.id').' = '.(int)$id);
			}
		}
		if ($this->getState('alias'))
		{
			$query->where($db->quoteName('item.alias').' = '.$db->quote($this->getState('alias')));
		}
		if ($this->getState('author'))
		{
			$query->where($db->quoteName('item.created_by').' = '.(int)$this->getState('author'));
			if ($this->getState('site'))
			{
				$query->where($db->quoteName('item.created_by_alias').' = '.$db->quote(''));
			}
		}

		if ($tag = $this->getState('tag'))
		{

			if ($excludeItemId = $this->getState('tag.exclude.item'))
			{
				$query->where($db->quoteName('item.id').' != '.(int)$excludeItemId);
			}

			// Cast to integer and generate the query string
			$tag = (array)$tag;
			JArrayHelper::toInteger($tag);
			sort($tag, SORT_NUMERIC);
			$condition = implode(',', $tag);

			// Optimize query depending on data amount. Use cache to avoid duplicate queries
			if (!isset(self::$cache[$condition]['count']))
			{
				$subquery = $db->getQuery(true);
				$subquery->select('COUNT('.$db->quoteName('itemId').')')->from($db->quoteName('#__k2_tags_xref'));
				$subquery->where($db->quoteName('tagId').' IN ('.$condition.')');
				$db->setQuery($subquery);
				self::$cache[$condition]['count'] = (int)$db->loadResult();
			}

			$numOfTaggedItems = self::$cache[$condition]['count'];

			if ($numOfTaggedItems == 0 || ($numOfTaggedItems == 1 && $this->getState('tag.exclude.item')))
			{
				// No results should be returned
				$query->where($db->quoteName('item.id').' IN(0)');
			}
			else if ($numOfTaggedItems <= 50)
			{
				if (!isset(self::$cache[$condition]['itemIds']))
				{
					$subquery = $db->getQuery(true);
					$subquery->select($db->quoteName('itemId'))->from($db->quoteName('#__k2_tags_xref'));
					$subquery->where($db->quoteName('tagId').' IN ('.$condition.')');
					$db->setQuery($subquery);
					self::$cache[$condition]['itemIds'] = $db->loadColumn();
				}
				$query->where($db->quoteName('item.id').' IN ('.implode(',', self::$cache[$condition]['itemIds']).')');
			}
			else
			{
				$query->innerJoin($db->quoteName('#__k2_tags_xref').' AS '.$db->quoteName('xref').' ON '.$db->quoteName('item.id').' = '.$db->quoteName('xref.itemId'));
				$query->where($db->quoteName('xref.tagId').' IN ('.$condition.')');
			}

		}

		if ($this->getState('publish_up'))
		{
			$query->where('('.$db->quoteName('item.publish_up').' = '.$db->Quote($db->getNullDate()).' OR '.$db->quoteName('item.publish_up').' <= '.$db->Quote($this->getState('publish_up')).')');
		}
		if ($this->getState('publish_down'))
		{
			$query->where('('.$db->quoteName('item.publish_down').' = '.$db->Quote($db->getNullDate()).' OR '.$db->quoteName('item.publish_down').' >= '.$db->Quote($this->getState('publish_down')).')');
		}
		if ($this->getState('search'))
		{
			$search = trim($this->getState('search'));
			if ($search)
			{
				// Site search
				if ($this->getState('site'))
				{
					$mode = $this->getState('search.mode');
					switch ($mode)
					{
						case 'exact' :
							$text = $db->quote('%'.$db->escape($search, true).'%', false);
							$where = $db->quoteName('item.title').' LIKE '.$text.' OR '.$db->quoteName('item.introtext').' LIKE '.$text.' OR '.$db->quoteName('item.fulltext').' LIKE '.$text.' OR '.$db->quoteName('item.extra_fields').' LIKE '.$text.' OR '.$db->quoteName('item.tags').' LIKE '.$text;
							break;

						case 'all' :
						case 'any' :
						default :
							$words = explode(' ', $search);
							$searchConditions = array();
							foreach ($words as $word)
							{
								$word = $db->quote('%'.$db->escape($word, true).'%', false);
								$wordConditions = array();
								$wordConditions[] = $db->quoteName('item.title').' LIKE '.$word;
								$wordConditions[] = $db->quoteName('item.introtext').' LIKE '.$word;
								$wordConditions[] = $db->quoteName('item.fulltext').' LIKE '.$word;
								$wordConditions[] = $db->quoteName('item.extra_fields').' LIKE '.$word;
								$wordConditions[] = $db->quoteName('item.tags').' LIKE '.$word;
								$searchConditions[] = implode(' OR ', $wordConditions);
							}
							$where = '('.implode(($mode == 'all' ? ') AND (' : ') OR ('), $searchConditions).')';
							break;
					}
					$query->where('('.$where.')');

				}
				// Admin search
				else
				{
					$search = $db->escape($search, true);
					$query->where('('.$db->quoteName('item.title').' LIKE '.$db->Quote('%'.$search.'%', false).'
					OR '.$db->quoteName('item.id').' = '.(int)$search.'
					OR '.$db->quoteName('item.introtext').' LIKE '.$db->Quote('%'.$search.'%', false).'
					OR '.$db->quoteName('item.fulltext').' LIKE '.$db->Quote('%'.$search.'%', false).')');
				}

			}
		}

		if ($this->getState('year') && $this->getState('month') && $this->getState('day'))
		{
			$startDate = JFactory::getDate($this->getState('year').'-'.$this->getState('month').'-'.$this->getState('day'))->toSql();
			$endDate = JFactory::getDate($this->getState('year').'-'.$this->getState('month').'-'.$this->getState('day').' 23:59:59')->toSql();
		}
		else if ($this->getState('year') && $this->getState('month'))
		{
			$startDate = JFactory::getDate($this->getState('year').'-'.$this->getState('month').'-01')->toSql();
			$endDate = JFactory::getDate($this->getState('year').'-'.$this->getState('month').'-'.date('t', strtotime('last day of '.$this->getState('year').'-'.$this->getState('month').'-01')).' 23:59:59')->toSql();
		}
		else if ($this->getState('year'))
		{
			$startDate = JFactory::getDate($this->getState('year').'-01-01')->toSql();
			$endDate = JFactory::getDate($this->getState('year').'-12-31 23:59:59')->toSql();
		}
		if (isset($startDate))
		{
			$query->where($db->quoteName('item.created').' >= '.$db->quote($startDate));
		}
		if (isset($endDate))
		{
			$query->where($db->quoteName('item.created').' <= '.$db->quote($endDate));
		}

		if ($this->getState('media'))
		{
			$query->where($db->quoteName('item.media').' != '.$db->quote('[]'));
			$query->where($db->quoteName('item.media').' != '.$db->quote(''));
		}
		if ($this->getState('created.value'))
		{
			$query->where($db->quoteName('item.created').' '.$this->getState('created.operator').' '.$db->quote($this->getState('created.value')));
		}
		if (is_numeric($this->getState('ordering.value')))
		{
			$query->where($db->quoteName('item.ordering').' '.$this->getState('ordering.operator').' '.(int)$this->getState('ordering.value'));
		}
		if ($excludeItemId = $this->getState('exclude'))
		{
			$query->where($db->quoteName('item.id').' != '.(int)$excludeItemId);
		}

	}

	private function setQuerySorting(&$query)
	{
		$sorting = $this->getState('sorting');
		switch($sorting)
		{
			default :
				$ordering = 'item.id';
				$direction = 'DESC';
				break;
			case 'id' :
			case 'id.reverse' :
				$ordering = 'item.id';
				$direction = $sorting == 'id' ? 'ASC' : 'DESC';
				break;
			case 'title' :
			case 'title.reverse' :
				$ordering = 'item.title';
				$direction = $sorting == 'title' ? 'ASC' : 'DESC';
				break;
			case 'ordering' :
			case 'ordering.reverse' :
				$categories = $this->getState('categories.applied');
				$ordering = count($categories) == 1 ? 'item.ordering' : array('category.lft', 'item.ordering');
				$direction = $sorting == 'ordering' ? 'ASC' : 'DESC';
				break;
			case 'featured_ordering' :
			case 'featured_ordering.reverse' :
				$ordering = 'item.featured_ordering';
				$direction = $sorting == 'featured_ordering' ? 'ASC' : 'DESC';
				break;
			case 'state' :
			case 'state.reverse' :
				$ordering = 'item.state';
				$direction = $sorting == 'state' ? 'ASC' : 'DESC';
				break;
			case 'featured' :
			case 'featured.reverse' :
				$ordering = 'item.featured';
				$direction = $sorting == 'featured' ? 'ASC' : 'DESC';
				break;
			case 'category' :
			case 'category.reverse' :
				$ordering = 'category.title';
				$direction = $sorting == 'category' ? 'ASC' : 'DESC';
				break;
			case 'author' :
			case 'author.reverse' :
				$ordering = 'authorName';
				$direction = $sorting == 'author' ? 'ASC' : 'DESC';
				break;
			case 'moderator' :
			case 'moderator.reverse' :
				$ordering = 'moderatorName';
				$direction = $sorting == 'moderator' ? 'ASC' : 'DESC';
				break;
			case 'access' :
			case 'access.reverse' :
				$ordering = 'item.access';
				$direction = $sorting == 'access' ? 'ASC' : 'DESC';
				break;
			case 'created' :
			case 'created.reverse' :
				$ordering = 'item.created';
				$direction = $sorting == 'created' ? 'ASC' : 'DESC';
				break;
			case 'modified' :
			case 'modified.reverse' :
				$ordering = 'item.modified';
				$direction = $sorting == 'modified' ? 'ASC' : 'DESC';
				break;
			case 'hits' :
			case 'hits.reverse' :
				$ordering = 'stats.hits';
				$direction = $sorting == 'hits' ? 'ASC' : 'DESC';
				break;
			case 'comments' :
			case 'comments.reverse' :
				$ordering = 'stats.comments';
				$direction = $sorting == 'comments' ? 'ASC' : 'DESC';
				break;
			case 'language' :
			case 'language.reverse' :
				$ordering = 'item.language';
				$direction = $sorting == 'language' ? 'ASC' : 'DESC';
				break;
			case 'publishUp' :
			case 'publishUp.reverse' :
				$ordering = 'item.publish_up';
				$direction = $sorting == 'publishUp' ? 'ASC' : 'DESC';
				break;
			case 'custom' :
				$ordering = $this->getState('sorting.custom.value');
				$direction = $this->getState('sorting.custom.direction');
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
			if ($sorting == 'random')
			{
				$query->order('RAND()');
			}
			else
			{
				$query->order($db->quoteName($ordering).' '.$direction);
			}

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
		// User
		$user = JFactory::getUser();

		// Params
		$params = JComponentHelper::getParams('com_k2');

		// Create action
		if (!$table->id)
		{
			// Set isNew flag
			$this->setState('isNew', true);

			// Permissions context for new items is the category
			$context = 'com_k2.category.'.$data['catid'];

			// If the user has not the permission to create item in this category stop the processs. Otherwise handle the item state
			if (!$user->authorise('k2.item.create', $context))
			{
				$this->setError(JText::_('K2_YOU_ARE_NOT_AUTHORIZED_TO_PERFORM_THIS_OPERATION'));
				return false;
			}
			else
			{
				// User can create the item but cannot edit it's state so we set the item state to 0
				if (!$user->authorise('k2.item.edit.state', $context))
				{
					$data['state'] = 0;
				}

				// User can create the item but cannot edit it's featured state so we set the item featured state to 0
				if (!$user->authorise('k2.item.edit.state.featured', $context))
				{
					$data['featured'] = 0;
				}
			}

		}
		// Edit action
		if ($table->id)
		{
			// Set isNew flag
			$this->setState('isNew', false);

			// Set owner change flag
			$this->setState('owner.changed', isset($data['created_by']) && $data['created_by'] != $table->created_by);
			$this->setState('owner', $table->created_by);

			$context = 'com_k2.item.'.$table->id;
			$canEdit = $user->authorise('k2.item.edit', $context) || ($user->authorise('k2.item.edit.own', $context) && $user->id == $table->created_by);
			$canEditState = $user->authorise('k2.item.edit.state', $context);
			$canEditFeaturedState = $user->authorise('k2.item.edit.state.featured', $context);

			// User cannot edit the item neither it's states. Stop the process
			if (!$canEdit && !$canEditState && !$canEditFeaturedState)
			{
				$this->setError(JText::_('K2_YOU_ARE_NOT_AUTHORIZED_TO_PERFORM_THIS_OPERATION'));
				return false;
			}
			else
			{
				// User cannot edit the item. Reset the input
				if (!$canEdit)
				{
					$data = array();
					$data['id'] = $table->id;
				}
				// Set the states values depending on permissions
				if (!$canEditState)
				{
					$data['state'] = $table->state;
				}
				if (!$canEditFeaturedState)
				{
					$data['featured'] = $table->featured;
				}
			}

		}

		// Before anything else store the revision data to the state
		if ($params->get('revisions') && $table->id)
		{
			$model = K2Model::getInstance('Revisions');
			$preSaveRevisionDataHash = $model->computeDataHash($model->buildRevisionData($table));
			$this->setState('preSaveRevisionDataHash', $preSaveRevisionDataHash);
		}

		$configuration = JFactory::getConfig();
		$userTimeZone = $user->getParam('timezone', $configuration->get('offset'));

		// Handle date data
		if ($data['id'] && isset($data['createdDate']))
		{
			// Convert date to UTC
			$createdDateTime = $data['createdDate'].' '.$data['createdTime'];
			$data['created'] = JFactory::getDate($createdDateTime, $userTimeZone)->toSql();
		}

		if (isset($data['publishUpDate']) && isset($data['publishUpTime']))
		{
			// Convert date to UTC
			$publishUpDateTime = $data['publishUpDate'].' '.$data['publishUpTime'];
			if ((int)$publishUpDateTime > 0)
			{
				$data['publish_up'] = JFactory::getDate($publishUpDateTime, $userTimeZone)->toSql();
			}
			else
			{
				$data['publish_up'] = $data['created'];
			}
		}

		if (isset($data['publishDownDate']) && isset($data['publishDownTime']))
		{
			// Convert date to UTC
			$publishDownDateTime = $data['publishDownDate'].' '.$data['publishDownTime'];
			if ((int)$publishDownDateTime > 0)
			{
				$data['publish_down'] = JFactory::getDate($publishDownDateTime, $userTimeZone)->toSql();
			}
			else
			{
				$data['publish_down'] = '';
			}
		}

		// Ordering
		if (!$table->id)
		{
			$data['ordering'] = $table->getNextOrder('catid = '.(int)$data['catid']);
		}

		// Featured ordering
		if (isset($data['featured']) && $data['featured'] && !isset($data['featured_ordering']) && !$table->featured_ordering)
		{
			$data['featured_ordering'] = $table->getNextFeaturedOrder();
		}

		// Image
		if (isset($data['image']))
		{
			// Detect if category has an image
			$data['image']['flag'] = (int)(!$data['image']['remove'] && ($data['image']['id'] || $data['image']['temp']));

			// Store the input of the image to state
			$this->setState('image', $data['image']);

			// Unset values we do not want to get stored to our database
			unset($data['image']['path']);
			unset($data['image']['id']);
			unset($data['image']['temp']);
			unset($data['image']['remove']);

			// Encode the value to JSON
			$data['image'] = json_encode($data['image']);
		}

		// Galleries
		if (isset($data['galleries']) && is_array($data['galleries']))
		{
			// Set the galleries input to state
			$this->setState('galleries', $data['galleries']);

			// Prepare the galleries input data for storing
			$data['galleries'] = array_values($data['galleries']);
			foreach ($data['galleries'] as $key => $gallery)
			{
				if ($gallery['remove'])
				{
					unset($data['galleries'][$key]);
				}
				else
				{
					unset($data['galleries'][$key]['remove']);
				}
			}
			$data['galleries'] = json_encode(array_values($data['galleries']));
		}

		// Media
		if (isset($data['media']) && is_array($data['media']))
		{
			// Set the media input to state
			$this->setState('media', $data['media']);

			// Prepare the media input data for storing
			$data['media'] = array_values($data['media']);
			foreach ($data['media'] as $key => $media)
			{
				if ($media['remove'])
				{
					unset($data['media'][$key]);
				}
				else
				{
					unset($data['media'][$key]['remove']);
				}
			}
			$data['media'] = json_encode(array_values($data['media']));
		}

		// Attachments
		if (isset($data['attachments']) && is_array($data['attachments']))
		{
			// Set the media input to state
			$this->setState('attachments', $data['attachments']);

			// Prepare the attachments input data for storing
			$data['attachments'] = array_values($data['attachments']);
			foreach ($data['attachments'] as $key => $attachment)
			{
				if ($attachment['remove'])
				{
					unset($data['attachments'][$key]);
				}
				else
				{
					unset($data['attachments'][$key]['remove']);
				}
			}
			$data['attachments'] = json_encode(array());
		}

		if (isset($data['tags'])&& !empty($data['tags']))
		{
			$model = K2Model::getInstance('Tags', 'K2Model');
			$tags = json_decode($data['tags']);
			$data['tags'] = array();
			foreach ($tags as $tag)
			{
				$tag->name = trim($tag->text);
				if ($tag->name)
				{
					if (!$tag->id)
					{
						$tag->id = $model->addTag($tag->name);
					}
				}
			}
			$data['tags'] = json_encode($tags);
		}

		// Extra fields
		if (isset($data['extra_fields']))
		{
			$data['extra_fields'] = json_encode($data['extra_fields']);
		}

		return true;

	}

	/**
	 * onAfterSave method.
	 *
	 * @return void
	 */

	protected function onAfterSave(&$data, $table)
	{

		// Image
		if ($image = $this->getState('image'))
		{
			K2HelperImages::update('item', $image, $table);
		}

		// Tags
		if (isset($data['tags']) && !empty($data['tags']))
		{
			$tags = json_decode($data['tags']);
			$model = K2Model::getInstance('Tags', 'K2Model');
			$itemId = $this->getState('id');
			$model->deleteItemTags($itemId);
			foreach ($tags as $tag)
			{
				$model->tagItem($tag->id, $itemId);
			}
		}

		// Galleries
		if ($galleries = $this->getState('galleries'))
		{
			K2HelperGalleries::update($galleries, $table);
		}

		// Media
		if ($media = $this->getState('media'))
		{
			K2HelperMedia::update($media, $table);
		}

		// Attachments
		if ($attachments = $this->getState('attachments'))
		{
			K2HelperAttachments::update($attachments, $table);
		}

		// Clean up temporary uploads
		K2HelperImages::purge('item');
		K2HelperGalleries::purge();
		K2HelperMedia::purge();
		K2HelperAttachments::purge();

		// Handle statistics
		$statistics = K2Model::getInstance('Statistics', 'K2Model');

		if ($this->getState('isNew'))
		{
			// Create item entry in statistics
			$statistics->createItemEntry($table->id);
			$statistics->increaseUserItemsCounter($table->created_by);
		}
		else
		{
			if ($this->getState('owner.changed'))
			{
				$statistics->decreaseUserItemsCounter($this->getState('owner'));
				$statistics->increaseUserItemsCounter($table->created_by);
			}
		}

		// Handle revisions
		if ($preSaveRevisionDataHash = $this->getState('preSaveRevisionDataHash'))
		{
			// Compute new version data hash
			$model = K2Model::getInstance('Revisions');
			$revisionData = $model->buildRevisionData($table);
			$afterSaveRevisionDataHash = $model->computeDataHash($revisionData);
			if ($preSaveRevisionDataHash != $afterSaveRevisionDataHash)
			{
				$input = array();
				$input['itemId'] = $table->id;
				$input['data'] = json_encode($revisionData);
				$input['hash'] = $afterSaveRevisionDataHash;
				$input['notes'] = $data['notes'];
				$model->setState('data', $input);
				$model->save($input);
			}
		}

		// Handle associations
		if (JLanguageAssociations::isEnabled() && isset($data['associations']))
		{
			// Get associations input
			$associations = $data['associations'];

			// Sanitize array with empty values
			foreach ($associations as $languageTag => $itemId)
			{
				if (empty($itemId))
				{
					unset($associations[$languageTag]);
				}
			}

			// Ensure that the item does not have associations when it's language is set to "All"
			if ($table->language == '*' && !empty($associations))
			{
				$associations = array();
			}

			// Add current item to associations
			$associations[$table->language] = $table->id;

			// Deleting old association for affected items
			$db = JFactory::getDbo();
			$query = $db->getQuery(true)->delete($db->quoteName('#__associations'))->where($db->quoteName('context').' = '.$db->quote('com_k2.item'))->where($db->quoteName('id').' IN ('.implode(',', $associations).')');
			$db->setQuery($query);
			$db->execute();

			if ($table->language != '*' && count($associations))
			{
				// Adding new association for these items
				$key = md5(json_encode($associations));
				$query->clear()->insert($db->quoteName('#__associations'));
				foreach ($associations as $id)
				{
					$query->values((int)$id.','.$db->quote('com_k2.item').','.$db->quote($key));
				}
				$db->setQuery($query);
				$db->execute();
			}
		}

		// K2 After Save plugin event . We trigger it here beacuse it is applied only to items ( like in v2 )
		$dispatcher = JDispatcher::getInstance();
		JPluginHelper::importPlugin('k2');
		if (!$this->getState('patch'))
		{
			$isNew = $this->getState('isNew');
			$dispatcher->trigger('onAfterK2Save', array(&$table, $isNew));
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
		if (!$user->authorise('k2.item.delete', 'com_k2.category.'.$table->catid))
		{
			$this->setError(JText::_('K2_YOU_ARE_NOT_AUTHORIZED_TO_PERFORM_THIS_OPERATION'));
			return false;
		}

		// Check that the item is trashed
		if ($table->state != -1)
		{
			$this->setError(JText::_('K2_YOU_CAN_ONLY_DELETE_TRASHED_ITEMS'));
			return false;
		}

		// Set some variables for later usage in the model
		$this->setState('galleries', $table->galleries);
		$this->setState('media', $table->media);
		$this->setState('categoryId', $table->catid);
		$this->setState('userId', $table->created_by);

		return true;
	}

	/**
	 * onAfterDelete method. Hook for chidlren model.
	 *
	 * @param   JTable  $table     	The table object.
	 *
	 * @return boolean
	 */

	protected function onAfterDelete($table)
	{

		// Delete item image
		K2HelperImages::remove('item', $table->id);

		// Delete item galleries
		$galleries = json_decode($this->getState('galleries'));
		K2HelperGalleries::remove($galleries, $table->id);

		// Delete item media
		$media = json_decode($this->getState('media'));
		K2HelperMedia::remove($media, $table->id);

		// Delete item tags reference
		$tagsModel = K2Model::getInstance('Tags');
		$tagsModel->deleteItemTags($table->id);

		// Delete item attachments
		$attachmentsModel = K2Model::getInstance('Attachments');
		$attachmentsModel->setState('itemId', $table->id);
		$attachments = $attachmentsModel->getRows();
		foreach ($attachments as $attachment)
		{
			$attachmentsModel->setState('id', $attachment->id);
			$attachmentsModel->delete();
		}

		// Handle statistics
		// First get statistics model
		$statistics = K2Model::getInstance('Statistics');

		// Delete the item entry
		$statistics->deleteItemEntry($table->id);

		// Decrease users statistics
		$statistics->decreaseUserItemsCounter($this->getState('userId'));

		// Delete revisions
		$model = K2Model::getInstance('Revisions');
		$model->deleteItemRevisions($table->id);

		// Return
		return true;

	}

	/**
	 * Close method.
	 *
	 * @return boolean	True on success false on failure.
	 */

	public function close()
	{
		// Clean up any temporary images and files
		K2HelperImages::purge('item');
		K2HelperGalleries::purge();
		K2HelperMedia::purge();
		K2HelperAttachments::purge();
		return true;
	}

	public function getCopyData($id)
	{
		// Get params
		$params = JComponentHelper::getParams('com_k2');

		// Get source item
		$source = K2Items::getInstance($id);

		// Get source item properties as data array. This array will be the inout to the model.
		$data = get_object_vars($source);

		// It's a new item so reset some properties
		$data['id'] = '';
		$data['tmpId'] = uniqid();
		$data['title'] = JText::_('K2_COPY_OF').' '.$data['title'];
		$data['alias'] = '';
		$data['tags'] = json_decode($data['_tags']);
		$data['media'] = json_decode($data['_media']);
		$data['galleries'] = json_decode($data['_galleries']);
		$data['extra_fields'] = json_decode($data['extra_fields']);
		$data['metadata'] = $data['metadata']->toString();
		$data['plugins'] = $data['plugins']->toString();
		$data['params'] = $data['params']->toString();
		unset($data['ordering']);
		unset($data['featured_ordering']);

		// Handle tags
		$tagNames = array();
		if (is_array($data['tags']))
		{
			foreach ($data['tags'] as $tag)
			{
				$tagNames[] = $tag->name;
			}
		}

		$data['tags'] = implode(',', $tagNames);

		// Handle image
		if (isset($data['images']) && is_array($data['images']) && isset($data['images']['src']))
		{
			// If filesystem is not local then path is the URL
			$filesystem = $params->get('filesystem');
			$path = ($filesystem == 'Local' || !$filesystem) ? 'media/k2/items/src/'.$data['images']['src']->id.'.jpg' : $data['images']['src']->url;
			$image = K2HelperImages::add('item', null, $path);
			$data['image'] = array('id' => '', 'temp' => $image->temp, 'path' => '', 'remove' => 0, 'caption' => $data['image']->caption, 'credits' => $data['image']->credits);
		}
		else
		{
			unset($data['image']);
		}

		// Handle media
		$media = array();
		if (is_array($data['media']))
		{
			foreach ($data['media'] as $key => $entry)
			{
				if ($entry->upload)
				{
					$filesystem = K2FileSystem::getInstance();
					if ($filesystem->has('media/k2/media/'.$id.'/'.$entry->upload))
					{
						$buffer = $filesystem->read('media/k2/media/'.$id.'/'.$entry->upload);
						JFile::write(JPATH_SITE.'/tmp/'.$entry->upload, $buffer);
					}
				}
				$newEntry = array();
				$newEntry['url'] = $entry->url;
				$newEntry['provider'] = $entry->provider;
				$newEntry['id'] = $entry->id;
				$newEntry['embed'] = $entry->embed;
				$newEntry['caption'] = $entry->caption;
				$newEntry['credits'] = $entry->credits;
				$newEntry['upload'] = $entry->upload;
				$newEntry['remove'] = 0;
				$media[$key] = $newEntry;
			}
		}
		$data['media'] = $media;

		// Handle galleries
		$galleries = array();
		if (is_array($data['galleries']))
		{
			foreach ($data['galleries'] as $key => $entry)
			{
				if ($entry->upload)
				{
					$filesystem = K2FileSystem::getInstance();
					if ($filesystem->has('media/k2/galleries/'.$id.'/'.$entry->upload))
					{
						JFolder::create(JPATH_SITE.'/tmp/'.$entry->upload);
						$files = $filesystem->listKeys('media/k2/galleries/'.$id.'/'.$entry->upload);
						foreach ($files['keys'] as $key)
						{
							if ($filesystem->has($key))
							{
								$buffer = $filesystem->read($key);
								JFile::write(JPATH_SITE.'/tmp/'.$entry->upload.'/'.basename($key), $buffer);
							}
						}
					}
				}
				$newEntry = array();
				$newEntry['url'] = $entry->url;
				$newEntry['upload'] = $entry->upload;
				$newEntry['remove'] = 0;
				$galleries[$key] = $newEntry;
			}
		}

		$data['galleries'] = $galleries;

		// Handle attachments
		$filesystem = K2FileSystem::getInstance();
		$attachmentsModel = K2Model::getInstance('Attachments');
		$attachments = array();
		$data['attachments'] = $source->getAttachments();
		foreach ($data['attachments'] as $key => $attachment)
		{

			// Prepare the data array
			$newEntry = array();
			$newEntry['id'] = '';
			$newEntry['name'] = $attachment->name;
			$newEntry['title'] = $attachment->title;
			if ($attachment->file)
			{
				$tmpId = uniqid();
				if ($filesystem->has('media/k2/attachments/'.$id.'/'.$attachment->file))
				{
					$buffer = $filesystem->read('media/k2/attachments/'.$id.'/'.$attachment->file);
					JFile::write(JPATH_SITE.'/tmp/'.$tmpId.'_'.$attachment->file, $buffer);
				}
				$newEntry['file'] = $tmpId.'_'.$attachment->file;
			}
			$newEntry['path'] = $attachment->path;
			$newEntry['remove'] = 0;
			$attachments[$key] = $newEntry;
		}
		$data['attachments'] = $attachments;

		// Return the input data
		return $data;
	}

	public function getArchive()
	{
		// Get database
		$db = $this->getDBO();

		// Get query
		$query = $db->getQuery(true);

		// Select rows
		$query->select('DISTINCT '.$query->month($db->quoteName('item.created')).' AS '.$db->quoteName('month'));
		$query->select($query->year($db->quoteName('item.created')).' AS '.$db->quoteName('year'));
		$query->from($db->quoteName('#__k2_items', 'item'));

		// Join over the categories
		$query->leftJoin($db->quoteName('#__k2_categories', 'category').' ON '.$db->quoteName('category.id').' = '.$db->quoteName('item.catid'));

		// Set state for site
		$this->setState('site', true);

		// Set query conditions
		$this->setQueryConditions($query);

		// Set query sorting
		$this->setQuerySorting($query);

		// Hook for plugins
		$this->onBeforeSetQuery($query, 'com_k2.archive');

		// Set the query
		$db->setQuery($query, 0, 12);

		// Get rows
		$rows = $db->loadObjectList();

		// Return rows
		return $rows;
	}

	public function getAuthors()
	{
		// Get database
		$db = $this->getDBO();

		// Get query
		$query = $db->getQuery(true);

		// Select rows
		$query->select($db->quoteName('item.created_by'));
		$query->from($db->quoteName('#__k2_items', 'item'));
		$query->group($db->quoteName('item.created_by'));

		// Set state for site
		$this->setState('site', true);

		// Set query conditions
		$this->setQueryConditions($query);

		// Set query sorting
		if ($this->getState('sorting'))
		{
			$this->setQuerySorting($query);
		}

		// Hook for plugins
		$this->onBeforeSetQuery($query, 'com_k2.authors');

		// Set the query
		$db->setQuery($query, 0, (int)$this->getState('limit'));

		// Get rows
		$rows = $db->loadObjectList();

		// Return rows
		return $rows;
	}

	public function getCalendar()
	{
		// Get database
		$db = $this->getDBO();

		// Get query
		$query = $db->getQuery(true);

		// Select statement
		$query->select('COUNT(*) AS '.$db->quoteName('counter'));
		$query->select('DAY('.$db->quoteName('item.created').') AS '.$db->quoteName('day'));
		$query->from($db->quoteName('#__k2_items', 'item'));

		// Set query conditions
		$this->setQueryConditions($query);

		$query->group($db->quoteName('day'));

		// Set the query
		$db->setQuery($query);

		// Get the result
		$result = $db->loadObjectList();

		return $result;


	}
}
