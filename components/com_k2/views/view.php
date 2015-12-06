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

/**
 * K2 base view class
 */

class K2View extends JViewLegacy
{
	protected $isActive = true;
	protected $feedLinkToHead = false;
	protected $type = 'html';

	public function __construct($config = array())
	{
		// Parent constructor
		parent::__construct($config);

		// Get application
		$application = JFactory::getApplication();

		// Set print variable
		$this->print = $application->input->getBool('print');

		// Load helpers
		$this->loadHelper('utilities');

		// Set the params
		$this->params = $application->getParams('com_k2');

		// Set the user
		$this->user = JFactory::getUser();

		// Get document
		$document = JFactory::getDocument();

		// Set view type
		$this->type = $document->getType();

		// Add template paths
		$template = $application->getTemplate();
		$this->addTemplatePath(JPATH_SITE.'/components/com_k2/templates/default');
		$this->addTemplatePath(JPATH_SITE.'/templates/'.$template.'/html/com_k2/templates/default');
		$this->addTemplatePath(JPATH_SITE.'/templates/'.$template.'/html/com_k2/default');
		if ($theme = $this->params->get('theme'))
		{
			$this->addTemplatePath(JPATH_SITE.'/components/com_k2/templates/'.$theme);
			$this->addTemplatePath(JPATH_SITE.'/templates/'.$template.'/html/com_k2/templates/'.$theme);
			$this->addTemplatePath(JPATH_SITE.'/templates/'.$template.'/html/com_k2/'.$theme);
		}

		// Set active (determine if the current menu Itemid is the same of the current view)
		$this->setActive();

		// Set page metadata and options correctly since Joomla! does not take care of it
		if ($this->isActive && $this->type == 'html')
		{
			// Get menu
			$menu = $application->getMenu();

			// Get active
			$active = $menu->getActive();

			// Set page heading
			$this->params->def('page_heading', $this->params->get('page_title', $active->title));

			// Set browser title
			$this->setTitle($this->params->get('page_title'));

			// Set meta description
			if ($this->params->get('menu-meta_description'))
			{
				$document->setDescription($this->params->get('menu-meta_description'));
			}

			// Set meta keywords
			if ($this->params->get('menu-meta_keywords'))
			{
				$document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
			}

			// Set robots
			if ($this->params->get('robots'))
			{
				$document->setMetadata('robots', $this->params->get('robots'));
			}
		}
	}

	public function display($tpl = null)
	{
		// Import plugins and trigger the onBeforeDisplayView event
		$dispatcher = JDispatcher::getInstance();
		JPluginHelper::importPlugin('k2');
		$application = JFactory::getApplication();
		$context = $application->input->get('option', '', 'cmd').'.'.$application->input->get('view', '', 'cmd');
		if ($task = $application->input->get('task', '', 'cmd'))
		{
			$context .= '.'.$task;
		}
		$dispatcher->trigger('onBeforeDisplayView', array($context, &$this));

		// Fix pathway. Remove any Advanced SEF links
		$params = JComponentHelper::getParams('com_k2');
		if ($params->get('k2Sef') && $params->get('k2SefMode', 'legacy') != 'legacy')
		{
			$sefItemIds = array($params->get('k2SefPrefixItem'), $params->get('k2SefPrefixCat'), $params->get('k2SefPrefixTag'), $params->get('k2SefPrefixUser'), $params->get('k2SefPrefixDate'));
			$pathway = $application->getPathWay();
			$pathwayItems = $pathway->getPathway();
			foreach ($pathwayItems as $key => $pathwayItem)
			{
				$Itemid = null;
				$link = parse_url($pathwayItem->link);
				if (isset($link['query']))
				{
					parse_str($link['query']);
					if ($Itemid && in_array($Itemid, $sefItemIds))
					{
						unset($pathwayItems[$key]);
					}
				}
			}
			$pathway->setPathway($pathwayItems);
		}

		$document = JFactory::getDocument();
		if ($document->getType() == 'html')
		{
			echo "\n<!-- JoomlaWorks \"K2\" (v3.0.0) | Learn more about K2 at http://getk2.org -->\n\n";
		}

		// Display
		parent::display($tpl);
	}

	private function setActive()
	{
		$application = JFactory::getApplication();
		$menu = $application->getMenu();
		$active = $menu->getActive();
		if ($active)
		{
			foreach ($active->query as $key => $value)
			{
				if ($application->input->get($key) != $value)
				{
					$this->isActive = false;
					break;
				}
			}
		}
		else
		{
			$this->isActive = false;
		}
	}

	protected function setTitle($title)
	{
		$application = JFactory::getApplication();
		$document = JFactory::getDocument();
		if (empty($title))
		{
			$title = $application->getCfg('sitename');
		}
		if ($document->getType() == 'html')
		{
			if ($application->getCfg('sitename_pagetitles', 0) == 1)
			{
				$title = JText::sprintf('JPAGETITLE', $application->getCfg('sitename'), $title);
			}
			elseif ($application->getCfg('sitename_pagetitles', 0) == 2)
			{
				$title = JText::sprintf('JPAGETITLE', $title, $application->getCfg('sitename'));
			}
		}

		$document->setTitle($title);
	}

	protected function setMetadata($resource)
	{
		$params = JComponentHelper::getParams('com_k2');

		if (!$this->isActive)
		{
			// Detect title
			$title = isset($resource->title) ? $resource->title : $resource->name;

			// Set the browser title according to the settings
			$this->setTitle($title);

			// Hide page heading since the current menu item is inherited
			$this->params->set('show_page_heading', false);

			// Update pathway
			$application = JFactory::getApplication();
			$pathway = $application->getPathWay();
			$pathway->addItem($title, '');

		}

		// Detect and set metadata
		if (isset($resource->metadata) && $metadata = $resource->metadata)
		{
			if ($metadata->get('description'))
			{
				$this->document->setDescription($metadata->get('description'));
			}
			if ($metadata->get('kewords'))
			{
				$this->document->setMetadata('keywords', $metadata->get('kewords'));
			}
			if ($metadata->get('robots'))
			{
				$this->document->setMetadata('robots', $metadata->get('robots'));
			}
			if ($metadata->get('author'))
			{
				$this->document->setMetadata('author', $metadata->get('author'));
			}
		}

		// If meta description is empty ( this means it was not set in the menu or item/category form ) then use the content of the resource
		if (!$this->document->getDescription())
		{
			$resourceType = get_class($resource);
			if ($resourceType == 'K2Items')
			{
				$description = $resource->introtext.' '.$resource->fulltext;
			}
			else if ($resourceType == 'K2Categories')
			{
				$description = $resource->description;
			}
			else if ($resourceType == 'K2Users')
			{
				$description = $resource->description;
			}
			if (isset($description))
			{
				$description = strip_tags($description);
				$description = K2HelperUtilities::characterLimit($description, $params->get('metaDescLimit', 150));
				$this->document->setDescription($description);
			}
		}
	}

	protected function getJsonItem($item)
	{
		$row = new stdClass;
		$row->id = $item->id;
		$row->title = $item->title;
		$row->alias = $item->alias;
		$row->link = $item->link;
		$row->url = $item->url;
		$row->catid = $item->catid;
		$row->introtext = $item->introtext;
		$row->fulltext = $item->fulltext;
		$row->extra_fields = $item->extra_fields;
		$row->created = $item->created;
		$row->created_by_alias = $item->created_by_alias;
		$row->modified = $item->modified;
		$row->featured = $item->featured;
		$row->image = $item->image;
		$row->images = $item->images;
		$row->media = $item->media;
		$row->galleries = $item->galleries;
		$row->hits = $item->hits;
		$row->category = new stdClass;
		$row->category->id = $item->category->id;
		$row->category->title = $item->category->title;
		$row->category->alias = $item->category->alias;
		$row->category->link = $item->category->link;
		$row->category->url = $item->category->url;
		$row->category->description = $item->category->description;
		$row->category->extra_fields = $item->category->extra_fields;
		$row->category->image = $item->category->image;
		$row->tags = $item->tags;
		$row->attachments = $item->attachments;
		$row->author = new stdClass;
		$row->author->name = $item->author->name;
		$row->author->link = $item->author->link;
		$row->author->url = $item->author->url;
		$row->author->image = $item->author->image;
		$row->author->description = $item->author->description;
		$row->author->site = $item->author->site;
		$row->author->gender = $item->author->gender;
		$row->author->extra_fields = $item->author->extra_fields;
		$row->numOfComments = $item->numOfComments;
		$row->events = $item->events;
		$row->language = $item->language;
		return $row;
	}

	protected function getFeedItem($item)
	{
		// Get configuration
		$configuration = JFactory::getConfig();

		// Get params
		$params = JComponentHelper::getParams('com_k2');

		// Create the entry
		$entry = new JFeedItem;

		// Title
		$entry->title = $this->escape($item->title);

		// Link
		$entry->link = $item->url;

		// Build description
		$entry->description = '';

		// Image
		if ($params->get('feedItemImage'))
		{
			$image = $item->getImage($params->get('feedImgSize'));
			if ($image)
			{
				$entry->description .= '<div class="K2FeedImage"><img src="'.$image->url.'" alt="'.$image->alt.'" /></div>';
			}
		}

		// Introtext
		if ($params->get('feedItemIntroText'))
		{
			//Introtext word limit
			if ($params->get('feedTextWordLimit') && $item->introtext)
			{
				$item->introtext = K2HelperUtilities::wordLimit($item->introtext, $params->get('feedTextWordLimit'));
			}
			$entry->description .= '<div class="K2FeedIntroText">'.$item->introtext.'</div>';
		}

		// Fulltext
		if ($params->get('feedItemFullText') && $item->fulltext)
		{
			$entry->description .= '<div class="K2FeedFullText">'.$item->fulltext.'</div>';
		}

		// Tags
		if ($params->get('feedItemTags') && count($item->tags))
		{
			$entry->description .= '<div class="K2FeedTags"><ul>';
			foreach ($item->tags as $tag)
			{
				$entry->description .= '<li><a href="'.$tag->url.'">'.$tag->name.'</a></li>';
			}
			$entry->description .= '</ul></div>';
		}

		// Media
		if ($params->get('feedItemVideo') && count($item->media))
		{
			$entry->description .= '<div class="K2FeedMedia"><ul>';
			foreach ($item->media as $video)
			{
				$entry->description .= '<li>'.$video->output.'</li>';
			}
			$entry->description .= '</ul></div>';
		}

		// Galleries
		if ($params->get('feedItemGallery') && count($item->galleries))
		{
			$entry->description .= '<div class="K2FeedGalleries"><ul>';
			foreach ($item->galleries as $gallery)
			{
				$entry->description .= '<li>'.$gallery->output.'</li>';
			}
			$entry->description .= '</ul></div>';
		}

		// Attachments
		if ($params->get('feedItemAttachments') && count($item->attachments))
		{
			$entry->description .= '<div class="K2FeedAttachments"><ul>';
			foreach ($item->attachments as $attachment)
			{
				$entry->description .= '<li><a title="'.htmlspecialchars($attachment->title).'" href="'.$attachment->url.'">'.$attachment->name.'</a></li>';
			}
			$entry->description .= '</ul></div>';
		}

		// Creation date
		$entry->date = $item->created;

		// Category
		$entry->category = $item->category->name;

		// Author
		$entry->author = $item->author->name;
		if ($params->get('feedBogusEmail'))
		{
			$entry->authorEmail = $params->get('feedBogusEmail');
		}
		else
		{
			if ($configuration->get('feed_email') == 'author')
			{
				$entry->authorEmail = $item->author->email;
			}
			else
			{
				$entry->authorEmail = $configuration->get('mailfrom');
			}
		}

		// Return feed item
		return $entry;
	}

	protected function getCategoryItems($count = false)
	{
		// Get application
		$application = JFactory::getApplication();

		// Get input
		$id = $application->input->get('id', 0, 'int');
		$categories = $this->params->get('categories');

		// Get model
		$model = K2Model::getInstance('Items');
		$model->setState('site', true);

		// Single category
		if ($id)
		{
			// Get category
			$this->category = K2Categories::getInstance($id);

			// Check access
			$this->category->checkSiteAccess();

			// Merge menu params with category params
			$effectiveParams = $this->category->getEffectiveParams();
			$this->params->merge($effectiveParams);

			// Set model state
			$model->setState('category', $id);

			if (!$this->params->get('catCatalogMode'))
			{
				$model->setState('recursive', 1);
			}

		}
		// Multiple categories from menu item parameters
		else if ($categories)
		{
			$model->setState('category.filter', $categories);
		}

		// Determine offset and limit based on document type
		if ($this->type == 'html' || $this->type == 'raw')
		{
			$this->limit = (int)($this->params->get('num_leading_items') + $this->params->get('num_primary_items') + $this->params->get('num_secondary_items') + $this->params->get('num_links'));
		}
		else
		{
			$this->limit = $this->params->get('feedLimit', 10, 'int');
		}

		// Select the handling of featured items
		// 0 -> don't display featured items
		// 1 -> display featured and non-featured items
		// 2 -> display only fetaured items
		$catFeaturedItems = $this->params->get('catFeaturedItems', 1, 'int');
		if($catFeaturedItems == 0) {
			$model->setState('featured', 0);
		}elseif ($catFeaturedItems == 2) {
			$model->setState('featured', 1);
		}
		
		// Set ordering state
		if($id) {
			$model->setState('sorting', $this->params->get('singleCatOrdering', 'id'));
		}else{
			$model->setState('sorting', $this->params->get('catOrdering', 'id'));
		}
		
		// Get items
		$model->setState('limit', $this->limit);
		$model->setState('limitstart', $this->offset);
		$this->items = $model->getRows();

		// Word limit
		if ($this->params->get('catItemIntroTextWordLimit'))
		{
			foreach ($this->items as $item)
			{
				$item->introtext = K2HelperUtilities::wordLimit($item->introtext, $this->params->get('catItemIntroTextWordLimit'));
			}

		}

		// Count items
		if ($count)
		{
			$this->total = $model->countRows();
		}

	}

	protected function getUserItems($count = false)
	{
		// Get application
		$application = JFactory::getApplication();

		// Get input
		$id = $application->input->get('id', 0, 'int');

		// Determine offset and limit based on document type
		if ($this->type == 'html' || $this->type == 'raw')
		{
			$this->limit = $application->input->get('limit', 10, 'int');
		}
		else
		{
			$this->limit = $this->params->get('feedLimit', 10, 'int');
		}

		// Get user
		$this->author = K2Users::getInstance($id);

		// Check access
		$this->author->checkSiteAccess();

		// @TODO Apply menu settings. Since they will be common all tasks we need to wait

		// Get items
		$model = K2Model::getInstance('Items');
		$model->setState('site', true);
		$model->setState('author', $id);
		$model->setState('limit', $this->limit);
		$model->setState('limitstart', $this->offset);
		$this->items = $model->getRows();

		// Count items
		if ($count)
		{
			$this->total = $model->countRows();
		}

	}

	protected function getTagItems($count = false)
	{
		// Get application
		$application = JFactory::getApplication();

		// Get input
		$id = $application->input->get('id', 0, 'int');

		// Determine offset and limit based on document type
		if ($this->type == 'html' || $this->type == 'raw')
		{
			$this->limit = $application->input->get('limit', 10, 'int');
		}
		else
		{
			$this->limit = $this->params->get('feedLimit', 10, 'int');
		}

		// Get tag
		$this->tag = K2Tags::getInstance($id);

		// Check access and publishing state
		$this->tag->checkSiteAccess();

		// Get items
		$model = K2Model::getInstance('Items');
		$model->setState('site', true);
		$model->setState('tag', $id);
		$model->setState('limit', $this->limit);
		$model->setState('limitstart', $this->offset);

		// @TODO Apply menu settings. Since they will be common all tasks we need to wait
		//$model->setState('sorting', 'created.reverse');

		$this->items = $model->getRows();

		// Count items
		if ($count)
		{
			$this->total = $model->countRows();
		}
	}

	protected function getDateItems($count = false)
	{
		// Get application
		$application = JFactory::getApplication();

		// Get input
		$year = $application->input->get('year', 0, 'int');
		$month = $application->input->get('month', 0, 'int');
		$day = $application->input->get('day', 0, 'int');
		$category = $application->input->get('category', 0, 'int');

		// Build the resource dynamicaly
		$dateValue = $year.'-'.$month;
		$dateFormat = JText::_('K2_ITEMLIST_DATE_MONTH_FORMAT');
		if ($day)
		{
			$dateValue .= '-'.$day;
			$dateFormat = JText::_('K2_ITEMLIST_DATE_DAY_FORMAT');
		}
		$this->date = JFactory::getDate($dateValue);
		$this->date->title = JText::_('K2_ITEMS_FILTERED_BY_DATE').' '.$this->date->format($dateFormat);

		// Determine offset and limit based on document type
		if ($this->type == 'html' || $this->type == 'raw')
		{
			$this->limit = $application->input->get('limit', 10, 'int');
		}
		else
		{
			$this->limit = $this->params->get('feedLimit', 10, 'int');
		}

		// Get items
		$model = K2Model::getInstance('Items');
		$model->setState('site', true);
		$model->setState('year', $year);
		$model->setState('month', $month);
		$model->setState('day', $day);
		$model->setState('category', $category);
		$model->setState('limit', $this->limit);
		$model->setState('limitstart', $this->offset);
		$this->items = $model->getRows();

		// Count items
		if ($count)
		{
			$this->total = $model->countRows();
		}

	}

	protected function getSearchItems($count = false)
	{
		// Get application
		$application = JFactory::getApplication();

		// Get input
		$search = trim($application->input->get('searchword', '', 'string'));
		$categories = $application->input->get('categories', '', 'string');

		if (strpos($categories, ','))
		{
			$categories = explode(',', $categories);
			JArrayHelper::toInteger($categories);
		}
		else
		{
			$categories = array((int)$categories);
		}

		// Determine offset and limit based on document type
		if ($this->type == 'html' || $this->type == 'raw')
		{
			$this->limit = $application->input->get('limit', 10, 'int');
		}
		else
		{
			$this->limit = $this->params->get('feedLimit', 10, 'int');
		}

		// Get items
		if ($search)
		{
			$model = K2Model::getInstance('Items');
			$model->setState('site', true);
			$model->setState('search', $search);
			if (is_array($categories) && count($categories))
			{
				$model->setState('category', $categories);
			}
			$model->setState('limit', $this->limit);
			$model->setState('limitstart', $this->offset);
			$this->items = $model->getRows();

			// Count items
			if ($count)
			{
				$this->total = $model->countRows();
			}

		}
		else
		{
			$this->items = array();
			$this->total = 0;
		}

		// Form action
		$this->action = JRoute::_(K2HelperRoute::getSearchRoute());

		// Search word
		$this->searchword = $search;

	}

	protected function getModuleItems()
	{
		// Import module helper
		jimport('joomla.application.module.helper');

		// Get application
		$application = JFactory::getApplication();

		// Get input
		$id = $application->input->get('id', 0, 'int');

		if ($id)
		{
			$module = K2HelperUtilities::getModule($id);
			if ($module)
			{
				require_once JPATH_SITE.'/modules/mod_k2_content/helper.php';
				$this->items = ModK2ContentHelper::getItems($module->params);
			}
		}
	}

	protected function generateItemlistParams($task)
	{
		$prefix = $task == 'category' ? 'cat' : $task;
		$prefix .= 'Item';
		$prefixLength = strlen($prefix);
		foreach ($this->params->toObject() as $key => $value)
		{
			if (strpos($key, $prefix) === 0)
			{
				$newKey = substr_replace($key, 'listItem', 0, $prefixLength);
				$this->params->set($newKey, $value);
			}
		}
	}

	protected function loadItemlistLayout()
	{
		// Clear output
		$this->_output = null;

		// Get current template
		$template = JFactory::getApplication()->getTemplate();

		// Load language file
		$language = JFactory::getLanguage();
		$language->load('tpl_'.$template, JPATH_BASE, null, false, true) || $language->load('tpl_'.$template, JPATH_THEMES.'/'.$template, null, false, true);

		// Get layout
		$layout = $this->getLayout();

		// Generate the file name
		$file = $layout.'_item';

		// Load the template script
		jimport('joomla.filesystem.path');
		$filetofind = $this->_createFileName('template', array('name' => $file));
		$this->_template = JPath::find($this->_path['template'], $filetofind);

		// If the task specific layout can't be found, fall back to common layout
		if ($this->_template == false)
		{
			$filetofind = $this->_createFileName('', array('name' => 'itemlist_item'));
			$this->_template = JPath::find($this->_path['template'], $filetofind);
		}

		// We have a file, let's render
		if ($this->_template != false)
		{
			// Unset so as not to introduce into template scope
			unset($tpl);
			unset($file);

			// Never allow a 'this' property
			if (isset($this->this))
			{
				unset($this->this);
			}

			// Start capturing output into a buffer
			ob_start();

			// Include the requested template filename in the local scope
			// (this will execute the view logic).
			include $this->_template;

			// Done with the requested template; get the buffer and
			// clear it.
			$this->_output = ob_get_contents();
			ob_end_clean();

			return $this->_output;
		}
		else
		{
			throw new Exception(JText::sprintf('JLIB_APPLICATION_ERROR_LAYOUTFILE_NOT_FOUND', $file), 500);
		}
	}

}
