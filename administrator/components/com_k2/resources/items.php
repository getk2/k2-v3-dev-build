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

require_once JPATH_ADMINISTRATOR.'/components/com_k2/models/items.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/resource.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/categories.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/users.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/tags.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/attachments.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/helpers/extrafields.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/helpers/images.php';
require_once JPATH_SITE.'/components/com_k2/helpers/route.php';

/**
 * K2 item resource class.
 */

class K2Items extends K2Resource
{

	/**
	 * @var array	Items instances container.
	 */
	protected static $instances = array();

	/**
	 * @var integer	Hits.
	 */
	public $hits = 0;

	/**
	 * Constructor.
	 *
	 * @param object $data
	 *
	 * @return void
	 */

	public function __construct($data)
	{
		parent::__construct($data);
		self::$instances[$this->id] = $this;
	}

	/**
	 * Gets an item instance.
	 *
	 * @param integer $id	The id of the item to get.
	 *
	 * @return K2Item The item object.
	 */
	public static function getInstance($id)
	{
		if (empty(self::$instances[$id]))
		{
			K2Model::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_k2/models');
			$model = K2Model::getInstance('Items', 'K2Model');
			if (is_numeric($id))
			{
				$model->setState('id', $id);
			}
			else
			{
				$model->setState('alias', $id);
			}
			$item = $model->getRow();
			if ($item->id)
			{
				self::$instances[$item->id] = $item;
				self::$instances[$item->alias] = $item;
			}
			else
			{
				self::$instances[$id] = $item;
			}

		}
		return self::$instances[$id];
	}

	/**
	 * Prepares the row for output
	 *
	 * @param string $mode	The mode for preparing data. 'site' for front-end data, 'admin' for administrator operations.
	 *
	 * @return void
	 */
	public function prepare($mode = null)
	{

		// Prepare generic properties like dates and authors
		parent::prepare($mode);

		// Category
		$this->category = $this->getCategory();

		// Edit link
		$this->editLink = JURI::base(true).'/index.php?option=com_k2#items/edit/'.$this->id;

		// Permisisons
		$user = JFactory::getUser();
		if ($user->guest)
		{
			$this->canEdit = false;
			$this->canEditState = false;
			$this->canEditFeaturedState = false;
			$this->canDelete = false;
			$this->canSort = false;
		}
		else
		{
			$this->canEdit = $user->authorise('k2.item.edit', 'com_k2.item.'.$this->id) || ($user->id == $this->created_by && $user->authorise('k2.item.edit.own', 'com_k2.item.'.$this->id));
			$this->canEditState = $user->authorise('k2.item.edit.state', 'com_k2.item.'.$this->id);
			$this->canEditFeaturedState = $user->authorise('k2.item.edit.state.featured', 'com_k2.item.'.$this->id);
			$this->canDelete = $user->authorise('k2.item.delete', 'com_k2.item.'.$this->id);
			$this->canSort = $user->authorise('k2.item.edit', 'com_k2');
		}

		// Hits
		$this->hits = (int)$this->hits;

		// Images
		$this->images = $this->getImages();
		$this->image = $this->getImage();

		// Author
		$this->author = $this->getAuthor();

		// Unset some properties to allow lazy loading
		$this->_attachments = $this->attachments;
		unset($this->attachments);
		$this->_tags = $this->tags;
		unset($this->tags);
		$this->_media = $this->media;
		unset($this->media);
		$this->_galleries = $this->galleries;
		unset($this->galleries);

	}

	public function getCategory()
	{
		$category = null;
		if ($this->id)
		{
			$category = K2Categories::getInstance($this->catid);
		}
		return $category;
	}

	public function getExtraFields()
	{
		$extraFields = new stdClass;
		foreach ($this->extraFieldsGroups as $extraFieldsGroup)
		{
			foreach ($extraFieldsGroup->fields as $extraField)
			{
				$field = clone($extraField);
				$field->value = $extraField->output;
				$property = $field->alias;
				$extraFields->$property = $field;
			}
		}
		return $extraFields;
	}

	public function getExtraFieldsGroups()
	{
		$groups = array();
		if ($this->id)
		{
			$groups = K2HelperExtraFields::getItemExtraFieldsGroups($this->catid, $this->extra_fields);
		}
		return $groups;
	}

	public function getTags()
	{
		$result = array();
		$instances = array();
		$tags = json_decode($this->_tags);
		if ($this->id && is_array($tags) && count($tags))
		{
			$tagIds = array();
			foreach ($tags as $tag)
			{
				if (K2Tags::loaded($tag->id))
				{
					$instance = K2Tags::getInstance($tag->id);
					if ($instance->state)
					{
						$instances[] = $instance;
					}
				}
				else
				{
					$tagIds[] = (int)$tag->id;
				}
			}
			if (count($tagIds))
			{
				$application = JFactory::getApplication();
				$model = K2Model::getInstance('Tags');
				if ($application->isSite())
				{
					$model->setState('state', 1);
				}
				$model->setState('id', $tagIds);
				$rows = $model->getRows();
				$result = array_merge($instances, $rows);
			}
			else
			{
				$result = $instances;
			}

		}
		return $result;
	}

	public function getAuthor()
	{
		$id = $this->created_by ? $this->created_by : JFactory::getUser()->id;
		$author = K2Users::getInstance($id);
		$this->authorLink = $author->link;
		return $author;
	}

	public function getImages()
	{
		$images = array();
		if ($this->id)
		{
			$images = K2HelperImages::getItemImages($this);
		}
		return $images;
	}

	public function getImage($size = null)
	{
		$image = null;
		if (!isset($this->images))
		{
			$this->images = $this->getImages();
		}
		if (count($this->images))
		{
			if (is_null($size))
			{
				$image = end($this->images);
			}
			else if (array_key_exists($size, $this->images))
			{
				$image = $this->images[$size];
			}
		}
		return $image;
	}

	public function getGalleries()
	{
		// Initialize value
		$galleries = array();

		// Process only if value is set
		if ($this->_galleries)
		{
			// Decode the value
			$galleries = json_decode($this->_galleries);

			// Get params
			$params = JComponentHelper::getParams('com_k2');
			$params->set('galleries_rootfolder', 'media/k2/galleries');

			// Get dispatcher
			$dispatcher = JDispatcher::getInstance();

			// Import content plugins
			JPluginHelper::importPlugin('content');

			if (is_array($galleries))
			{
				foreach ($galleries as $gallery)
				{
					$tag = ($gallery->upload) ? $this->id.'/'.$gallery->upload : $gallery->url;
					$gallery->text = '{gallery}'.$tag.'{/gallery}';

					// Render the gallery
					$dispatcher->trigger('onContentPrepare', array('com_k2', &$gallery, &$params, 0));

					// Restore
					$gallery->output = $gallery->text;
					unset($gallery->text);
				}
			}

		}

		return $galleries;
	}

	public function getMedia()
	{
		// Initialize value
		$media = array();

		// Process only if value is set
		if ($this->_media)
		{
			// Decode value
			$media = json_decode($this->_media);

			// If it is not an array return now
			if (!is_array($media))
			{
				return array();
			}

			// Get params
			$params = JComponentHelper::getParams('com_k2');

			// Get dispatcher
			$dispatcher = JDispatcher::getInstance();

			// Import content plugins
			JPluginHelper::importPlugin('content');

			foreach ($media as $entry)
			{
				if ($entry->embed)
				{
					$entry->output = $entry->embed;
				}
				else
				{
					if ($entry->upload)
					{
						$parts = explode('.', $entry->upload);
						$name = reset($parts);
						$extension = strtolower(end($parts));
						$tag = '{'.$extension.'}'.$this->id.'/'.$name.'{/'.$extension.'}';
					}
					else if ($entry->provider)
					{
						$tag = '{'.$entry->provider.'}'.$entry->id.'{/'.$entry->provider.'}';
					}
					else if ($entry->url)
					{
						$parts = explode('.', $entry->url);
						$extension = strtolower(end($parts));
						$tag = '{'.$extension.'remote}'.$entry->url.'{/'.$extension.'remote}';
					}

					$entry->text = $tag;

					// Render media
					$params->set('afolder', 'media/k2/media');
					$params->set('vfolder', 'media/k2/media');
					$dispatcher->trigger('onContentPrepare', array('com_k2', &$entry, &$params, 0));

					// Restore
					$entry->output = $entry->text;
					unset($entry->text);
				}
			}
		}

		return $media;
	}

	public function getAttachments()
	{
		$attachments = array();
		$attachmentsIds = json_decode($this->_attachments);
		if (is_array($attachmentsIds) && count($attachmentsIds))
		{
			$model = K2Model::getInstance('Attachments');
			$model->setState('id', $attachmentsIds);
			$model->setState('sorting', 'ordering');
			$attachments = $model->getRows();
		}
		return $attachments;
	}

	public function getLink()
	{
		if (!isset($this->link))
		{
			$this->link = JRoute::_(K2HelperRoute::getItemRoute($this->id.':'.$this->alias, $this->catid));
		}
		return $this->link;
	}

	public function getUrl()
	{
		if (!isset($this->url))
		{
			$this->url = JRoute::_(K2HelperRoute::getItemRoute($this->id.':'.$this->alias, $this->catid), true, -1);
		}
		return $this->url;
	}

	public function getPrintLink()
	{
		if (!isset($this->printLink))
		{
			$this->printLink = JRoute::_(K2HelperRoute::getItemRoute($this->id.':'.$this->alias, $this->catid).'&tmpl=component&print=1');
		}
		return $this->printLink;
	}

	public function getEmailLink()
	{
		if (!isset($this->emailLink))
		{
			require_once JPATH_SITE.'/components/com_mailto/helpers/mailto.php';
			$application = JFactory::getApplication();
			$template = $application->getTemplate();
			$this->emailLink = JRoute::_('index.php?option=com_mailto&tmpl=component&template='.$template.'&link='.MailToHelper::addLink($this->url));
		}
		return $this->emailLink;
	}

	public function getComments($offset = 0)
	{
		$user = JFactory::getUser();
		$params = JComponentHelper::getParams('com_k2');
		$comments = array();
		if ($this->id)
		{
			// Get comments model
			$model = K2Model::getInstance('Comments');
			$model->setState('itemId', $this->id);
			$model->setState('limit', (int)$params->get('commentsLimit', 10));
			$model->setState('limitstart', $offset);
			$model->setState('sorting', 'id.reverse');
			$model->setState('state', 1);
			if ($params->get('commentsOrdering') == 'ASC')
			{
				$model->setState('sorting', 'id');
			}
			// Load comments
			$comments = $model->getRows();
		}
		return $comments;
	}

	public function getNumOfComments()
	{
		$result = 0;
		if ($this->id)
		{
			$model = K2Model::getInstance('Comments');
			$model->setState('itemId', $this->id);
			$model->setState('state', 1);
			$model->setState('sorting', 'id.reverse');
			$result = $model->countRows();
		}
		return $result;
	}

	public static function countComments(&$items)
	{
		$itemIds = array();
		foreach ($items as $item)
		{
			$itemIds[] = $item->id;
		}
		if (count($itemIds))
		{
			$model = K2Model::getInstance('Comments');
			$model->setState('itemId', $itemIds);
			$model->setState('state', 1);
			$rows = $model->batchCountRows();
		}
		foreach ($items as $item)
		{
			foreach ($rows as $row)
			{
				if ($item->id == $row->itemId)
				{
					$item->numOfComments = $row->numOfComments;
				}
			}
			if (!isset($item->numOfComments))
			{
				$item->numOfComments = 0;
			}
		}
	}

	public function getPrevious()
	{
		$model = K2Model::getInstance('Items');
		$model->setState('site', 1);
		$model->setState('category', $this->catid);
		$model->setState('exclude', $this->id);
		$model->setState('sorting', 'custom');
		$model->setState('sorting.custom.value', 'item.ordering');
		$model->setState('sorting.custom.direction', 'DESC');
		$model->setState('ordering.value', $this->ordering);
		$model->setState('ordering.operator', '<');
		$model->setState('limit', 1);
		$rows = $model->getRows();
		return ($rows && isset($rows[0])) ? $rows[0] : false;
	}

	public function getNext()
	{
		$model = K2Model::getInstance('Items');
		$model->setState('site', 1);
		$model->setState('category', $this->catid);
		$model->setState('exclude', $this->id);
		$model->setState('sorting', 'custom');
		$model->setState('sorting.custom.value', 'item.ordering');
		$model->setState('sorting.custom.direction', 'ASC');
		$model->setState('ordering.value', $this->ordering);
		$model->setState('ordering.operator', '>');
		$model->setState('limit', 1);
		$rows = $model->getRows();
		return ($rows && isset($rows[0])) ? $rows[0] : false;
	}

	public function getRelated($limit = 10)
	{
		$rows = array();
		if (count($this->tags))
		{
			$model = K2Model::getInstance('Items');
			$model->setState('site', 1);
			$model->setState('limit', 10);
			$tagIds = array();
			$this->tags = $this->getTags();
			foreach ($this->tags as $tag)
			{
				$tagIds[] = $tag->id;
			}
			$model->setState('tag', $tagIds);
			$model->setState('tag.exclude.item', $this->id);
			$model->setState('limit', $limit);
			$model->setState('limitstart', 0);
			$rows = $model->getRows();
		}
		return $rows;
	}

	public function getLatestByAuthor($limit = 10)
	{
		$rows = array();
		if (trim($this->created_by_alias) == '')
		{
			$rows = $this->author->getLatest($limit, $this->id);
		}
		return $rows;
	}

	public function getEvents($context = 'com_k2.item', &$params = null, $offset = 0, $k2Plugins = true, $jPlugins = true)
	{
		// Params
		if (is_null($params))
		{
			$params = JComponentHelper::getParams('com_k2');
		}

		// Get dispatcher
		$dispatcher = JDispatcher::getInstance();

		// Create the text variable
		$this->text = $this->introtext.'{K2Splitter}'.$this->fulltext;

		// Create the event object with null values
		$events = new stdClass;
		$events->AfterDisplayTitle = '';
		$events->BeforeDisplayContent = '';
		$events->AfterDisplayContent = '';
		$events->K2BeforeDisplay = '';
		$events->K2AfterDisplay = '';
		$events->K2AfterDisplayTitle = '';
		$events->K2BeforeDisplayContent = '';
		$events->K2AfterDisplayContent = '';

		// Content plugins
		if ($jPlugins)
		{
			// Import content plugins
			JPluginHelper::importPlugin('content');

			$dispatcher->trigger('onContentPrepare', array($context, &$this, &$params, $offset));
			$results = $dispatcher->trigger('onContentAfterTitle', array($context, &$this, &$params, $offset));
			$events->AfterDisplayTitle = trim(implode("\n", $results));
			$results = $dispatcher->trigger('onContentBeforeDisplay', array($context, &$this, &$params, $offset));
			$events->BeforeDisplayContent = trim(implode("\n", $results));
			$results = $dispatcher->trigger('onContentAfterDisplay', array($context, &$this, &$params, $offset));
			$events->AfterDisplayContent = trim(implode("\n", $results));

		}

		// K2 plugins
		if ($k2Plugins)
		{
			// Import K2 plugins
			JPluginHelper::importPlugin('k2');

			$dispatcher->trigger('onK2PluginInit', array($this));

			$results = $dispatcher->trigger('onK2BeforeDisplay', array(&$this, &$params, $offset));
			$events->K2BeforeDisplay = trim(implode("\n", $results));

			$results = $dispatcher->trigger('onK2AfterDisplay', array(&$this, &$params, $offset));
			$events->K2AfterDisplay = trim(implode("\n", $results));

			$results = $dispatcher->trigger('onK2AfterDisplayTitle', array(&$this, &$params, $offset));
			$events->K2AfterDisplayTitle = trim(implode("\n", $results));

			$results = $dispatcher->trigger('onK2BeforeDisplayContent', array(&$this, &$params, $offset));
			$events->K2BeforeDisplayContent = trim(implode("\n", $results));

			$results = $dispatcher->trigger('onK2AfterDisplayContent', array(&$this, &$params, $offset));
			$events->K2AfterDisplayContent = trim(implode("\n", $results));

			$dispatcher->trigger('onK2PrepareContent', array(&$this, &$params, $offset));

			if ($params->get('comments'))
			{
				$results = $dispatcher->trigger('onK2CommentsCounter', array(&$this, &$params, $offset));
				$events->K2CommentsCounter = trim(implode("\n", $results));

				$results = $dispatcher->trigger('onK2CommentsBlock', array(&$this, &$params, $offset));
				$events->K2CommentsBlock = trim(implode("\n", $results));
			}
		}

		// Restore introtext and fulltext
		list($this->introtext, $this->fulltext) = explode('{K2Splitter}', $this->text);

		// Unset the text
		unset($this->text);

		// return
		return $events;
	}

	public function getRevisions()
	{
		$revisions = array();
		$params = JComponentHelper::getParams('com_k2');
		if ($this->id && $params->get('revisions'))
		{
			$model = K2Model::getInstance('Revisions');
			$model->setState('itemId', $this->id);
			$revisions = $model->getRows();
		}
		return $revisions;
	}

	public function hit()
	{
		$model = K2Model::getInstance('Statistics');
		$model->increaseItemHitsCounter($this->id);
		$this->hits++;
	}

	public function checkSiteAccess()
	{
		// Get date
		$date = JFactory::getDate();
		$now = $date->toSql();

		// State check
		if ($this->state < 1 || $this->category->state < 1 || (int)$this->id < 1)
		{
			throw new Exception(JText::_('K2_NOT_FOUND'), 404);
		}
		if ((int)$this->publish_up > 0 && $this->publish_up > $now)
		{
			throw new Exception(JText::_('K2_NOT_FOUND'), 404);
		}
		if ((int)$this->publish_down > 0 && $this->publish_down < $now)
		{
			throw new Exception(JText::_('K2_NOT_FOUND'), 404);
		}

		// Get user
		$user = JFactory::getUser();
		$viewLevels = $user->getAuthorisedViewLevels();

		// Access check
		if (!in_array($this->access, $viewLevels) || !in_array($this->category->access, $viewLevels))
		{
			if ($user->guest)
			{
				// Get application
				$application = JFactory::getApplication();

				// Get document
				$document = JFactory::getDocument();

				// In front end HTML requests redirect the user to the login page
				if ($application->isSite() && $document->getType() == 'html')
				{
					require_once JPATH_SITE.'/components/com_users/helpers/route.php';
					$uri = JUri::getInstance();
					$url = 'index.php?option=com_users&view=login&return='.base64_encode($uri->toString()).'&Itemid='.UsersHelperRoute::getLoginRoute();
					$application->redirect(JRoute::_($url, false), JText::_('K2_YOU_NEED_TO_LOGIN_FIRST'));
				}

				// Return false
				return false;
			}
			else
			{
				throw new Exception(JText::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
			}
		}

		return true;

	}

	// Legacy
	public function getEvent()
	{
		$events = $this->events;
		$events->BeforeDisplay = '';
		$events->AfterDisplay = '';
		$events->K2UserDisplay = $this->user->events->K2UserDisplay;
		return $events;
	}

	public function getCategoryId()
	{
		return $this->catid;
	}

	public function getCategoryName()
	{
		return $this->category->title;
	}

	public function getCategoryLink()
	{
		return $this->category->link;
	}

	public function getImageXSmall()
	{
		return isset($this->images['XS']) ? $this->images['XS']->src : '';
	}

	public function getImageSmall()
	{
		return isset($this->images['L']) ? $this->images['S']->src : '';
	}

	public function getImageMedium()
	{
		return isset($this->images['M']) ? $this->images['M']->src : '';
	}

	public function getImageLarge()
	{
		return isset($this->images['L']) ? $this->images['L']->src : '';
	}

	public function getImageXLarge()
	{
		return isset($this->images['XL']) ? $this->images['XL']->src : '';
	}

	public function getImage_caption()
	{
		return is_object($this->image) ? $this->image->caption : '';
	}

	public function getImage_credits()
	{
		return is_object($this->image) ? $this->image->credits : '';
	}

	public function getVideo()
	{
		return count($this->media) ? $this->media[0]->output : '';
	}

	public function getVideo_caption()
	{
		return count($this->media) ? $this->media[0]->caption : '';
	}

	public function getVideo_credits()
	{
		return count($this->media) ? $this->media[0]->credits : '';
	}

	public function getVideoType()
	{
		return '';
	}

	public function getGallery()
	{
		return count($this->galleries) ? $this->galleries[0]->output : '';
	}

	public function getAuthorLink()
	{
		return $this->author->link;
	}

	public function getAuthorAvatar()
	{
		return $this->author->image->src;
	}

	public function getAuthorGender()
	{
		return $this->author->gender;
	}

	public function getAuthorDescription()
	{
		return $this->author->description;
	}
	
	// Clone an array, including referenced objects
	private function array_clone($array) {
		return array_map(function($element) {
			return ((is_array($element))
					? call_user_func(__FUNCTION__, $element)
					: ((is_object($element))
							? clone $element
							: $element
							)
					);
		}, $array);
	}
	
	public function getextra_fields()
	{
		$extraFields = array();
		foreach ($this->extraFieldsGroups as $extraFieldGroup)
		{
			foreach ($extraFieldGroup->fields as $key => $extraField)
			{
				$extraField->value = $extraField->output;
				$extraFields[] = $extraField;
			}
		}
		return $this->array_clone($extraFields);
	}

	public function getNumOfVotes()
	{
		return 0;
	}

	public function getUser()
	{
		return $this->author;
	}

	public function getPublished()
	{
		return $this->state;
	}

	public function getImageWidth()
	{
		return isset($this->image) ? $this->image->width : null;
	}

}
