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
 * K2 System plugin
 */

class PlgSystemK2 extends JPlugin
{

	public function onAfterInitialise()
	{
		// Get application
		$application = JFactory::getApplication();

		// Get user
		$user = JFactory::getUser();

		// Load Joomla! classes
		jimport('joomla.filesystem.file');

		// Load the K2 classes
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/classes/plugin.php';
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/tables/table.php';
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/models/model.php';
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/attachments.php';
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/categories.php';
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/comments.php';
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/extrafields.php';
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/extrafieldsgroups.php';
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/items.php';
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/tags.php';
		require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/users.php';
		K2Model::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_k2/models');

		// Load K2 language
		$language = JFactory::getLanguage();
		$language->load('com_k2', JPATH_ADMINISTRATOR);

		// Use K2 to make Joomla! Varnish-friendly. For more checkout: https://snipt.net/fevangelou/the-perfect-varnish-configuration-for-joomla-websites/
		if (!$user->guest)
		{
			$application->setHeader('X-Logged-In', 'True', true);
		}
		else
		{
			$application->setHeader('X-Logged-In', 'False', true);
		}

	}

	public function onAfterRoute()
	{
		// Get application
		$application = JFactory::getApplication();

		// Get user
		$user = JFactory::getUser();

		// Get params
		$params = JComponentHelper::getParams('com_k2');

		// Get document
		$document = JFactory::getDocument();

		// Get input
		$option = $application->input->get('option');
		$view = $application->input->get('view');
		$task = $application->input->get('task');
		$format = $application->input->get('format');

		// Detect if we are in edit mode
		if (($application->isAdmin() && $option == 'com_k2') || ($application->isSite() && $option == 'com_k2' && ($view == 'admin' || $view == '')))
		{
			define('K2_EDIT_MODE', true);
		}
		else
		{
			define('K2_EDIT_MODE', false);
		}

		// Redirect settings editing from com_config to K2
		if ($application->isAdmin() && $option == 'com_config' && $view == 'component' && $application->input->get('component') == 'com_k2')
		{
			$application->redirect('index.php?option=com_k2#settings');
		}

		// Throw an error in JSON format when the session has expired to catch the Joomla! invalid redirect to com_login in JSON format
		$isCommentsRequest = $application->isSite() && K2_EDIT_MODE && $task == 'comments.sync';
		if ($user->get('guest') && K2_EDIT_MODE && $format == 'json' && !$isCommentsRequest)
		{
			K2Response::throwError(JText::_('K2_SESSION_EXPIRED'), 500);
		}

		// Front-end only check
		if ($application->isSite())
		{
			// Enforce system template for editing
			if (K2_EDIT_MODE)
			{
				$application->setTemplate('system');
			}

			// Load head data if document type is HTML
			if ($document->getType() == 'html')
			{
				// Javascript files
				JHtml::_('jquery.framework');
				$document->addScript(JURI::root(true).'/media/k2app/assets/js/k2.js?v3.0.0&amp;sitepath='.JURI::root(true).'/');

				// CSS files. Check first that K2 CSS is enabled in component settings
				if ($params->get('enable_css'))
				{
					// Load k2.css. Check for overrides in template's css directory
					if (JFile::exists(JPATH_SITE.'/templates/'.$application->getTemplate().'/css/k2.css'))
					{
						$document->addStyleSheet(JURI::root(true).'/templates/'.$application->getTemplate().'/css/k2.css?v=3.0.0');
					}
					else
					{
						$document->addStyleSheet(JURI::root(true).'/components/com_k2/css/k2.css?v=3.0.0');
					}

					// Load k2.print.css if we are in print mode. Check for overrides in template's css directory
					if ($application->input->get('print', false, 'bool'))
					{
						if (JFile::exists(JPATH_SITE.'/templates/'.$application->getTemplate().'/css/k2.print.css'))
						{
							$document->addStyleSheet(JURI::root(true).'/templates/'.$application->getTemplate().'/css/k2.print.css?v=3.0.0', 'text/css', 'print');
						}
						else
						{
							$document->addStyleSheet(JURI::root(true).'/components/com_k2/css/k2.print.css?v=3.0.0', 'text/css', 'print');
						}
					}
				}
			}
		}
	}

	// Extend user forms with K2 fields
	public function onAfterDispatch()
	{
		// Get application
		$application = JFactory::getApplication();

		// Get params
		$params = JComponentHelper::getParams('com_k2');

		// Process only in front-end and only if K2 user profiles are enabled
		if ($application->isSite() && $params->get('K2UserProfile') == 'legacy')
		{

			// Get user
			$user = JFactory::getUser();

			// Get layout from menu
			$active = JFactory::getApplication()->getMenu()->getActive();
			$default = isset($active->query['layout']) ? $active->query['layout'] : '';

			// Get input
			$option = $application->input->get('option', '', 'cmd');
			$view = $application->input->get('view', '', 'cmd');
			$task = $application->input->get('task', '', 'cmd');
			$layout = $application->input->get('layout', $default, 'cmd');

			// Registration page override
			if ($option == 'com_users' && $view == 'registration')
			{

				// Add reCapctha if it is enabled
				if ($params->get('recaptchaOnRegistration') && $params->get('recaptcha_public_key'))
				{
					$document = JFactory::getDocument();
					$document->addScript('https://www.google.com/recaptcha/api/js/recaptcha_ajax.js');
					$js = '
					function showRecaptcha(){
						Recaptcha.create("'.$params->get('recaptcha_public_key').'", "recaptcha", {
							theme: "'.$params->get('recaptcha_theme', 'clean').'"
						});
					}
					jQuery(document).ready(function() {
						showRecaptcha();
					});
					';
					$document->addScriptDeclaration($js);
				}

				// Ensure that user is not logged in
				if (!$user->guest)
				{
					$application->enqueueMessage(JText::_('K2_YOU_ARE_ALREADY_REGISTERED_AS_A_MEMBER'), 'notice');
					$application->redirect(JURI::root());
					$application->close();
				}

				// Get controller
				$controller = JControllerLegacy::getInstance('Users');

				// Get view
				$view = $controller->getView('registration', 'html');

				// Add K2 layout paths to the core users view
				$view->addTemplatePath(JPATH_SITE.'/components/com_k2/templates/default');
				$view->addTemplatePath(JPATH_SITE.'/templates/'.$application->getTemplate().'/html/com_k2/templates/default');
				$view->addTemplatePath(JPATH_SITE.'/templates/'.$application->getTemplate().'/html/com_k2/templates');
				$view->addTemplatePath(JPATH_SITE.'/templates/'.$application->getTemplate().'/html/com_k2');

				// Set the layout
				$view->setLayout('register');

				// Get K2 user profile
				$model = K2Model::getInstance('Users');
				$K2User = $model->getRow();

				// Editor for user text field
				$wysiwyg = JFactory::getEditor();
				$editor = $wysiwyg->display('jform[k2Profile][description]', $K2User->description, '100%', '250px', '', '', false);

				// Gender field
				$lists = array();
				$genderOptions[] = JHTML::_('select.option', 'm', JText::_('K2_MALE'));
				$genderOptions[] = JHTML::_('select.option', 'f', JText::_('K2_FEMALE'));
				$lists['gender'] = JHTML::_('select.radiolist', $genderOptions, 'jform[k2Profile][gender]', '', 'value', 'text');

				// Assign variables to view
				$view->assignRef('editor', $editor);
				$view->assignRef('lists', $lists);
				$view->assignRef('K2Params', $params);
				$view->assignRef('user', $user);

				// Trigger K2 plugins
				JPluginHelper::importPlugin('k2');
				$dispatcher = JDispatcher::getInstance();
				$K2Plugins = $dispatcher->trigger('onRenderAdminForm', array(&$K2User, 'user'));
				$view->assignRef('K2Plugins', $K2Plugins);
				$view->assignRef('K2User', $K2User);

				// More variables for the view
				$nameFieldName = 'jform[name]';
				$view->assignRef('nameFieldName', $nameFieldName);
				$usernameFieldName = 'jform[username]';
				$view->assignRef('usernameFieldName', $usernameFieldName);
				$emailFieldName = 'jform[email1]';
				$view->assignRef('emailFieldName', $emailFieldName);
				$passwordFieldName = 'jform[password1]';
				$view->assignRef('passwordFieldName', $passwordFieldName);
				$passwordVerifyFieldName = 'jform[password2]';
				$view->assignRef('passwordVerifyFieldName', $passwordVerifyFieldName);
				$optionValue = 'com_users';
				$view->assignRef('optionValue', $optionValue);
				$taskValue = 'registration.register';
				$view->assignRef('taskValue', $taskValue);

				// Get buffer
				ob_start();
				$view->display();
				$contents = ob_get_clean();

				// Override the component output
				$document = JFactory::getDocument();
				$document->setBuffer($contents, 'component');

			}
			// Profile page override
			else if ($option == 'com_users' && $view == 'profile' && ($layout == 'edit' || $task == 'profile.edit'))
			{

				// Process only if user is not guest
				if (!$user->guest)
				{
					// Get controller
					$controller = JControllerLegacy::getInstance('Users');

					// Get view
					$view = $controller->getView('profile', 'html');

					// Add K2 layout paths to the core users view
					$view->addTemplatePath(JPATH_SITE.'/components/com_k2/templates/default');
					$view->addTemplatePath(JPATH_SITE.'/templates/'.$application->getTemplate().'/html/com_k2/templates/default');
					$view->addTemplatePath(JPATH_SITE.'/templates/'.$application->getTemplate().'/html/com_k2/templates');
					$view->addTemplatePath(JPATH_SITE.'/templates/'.$application->getTemplate().'/html/com_k2');

					// Set the layout
					$view->setLayout('profile');

					// Get K2 user profile
					require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/users.php';
					$K2User = K2Users::getInstance($user->id);

					// Editor for user text field
					$wysiwyg = JFactory::getEditor();
					$editor = $wysiwyg->display('jform[k2Profile][description]', $K2User->description, '100%', '250px', '', '', false);

					// Gender field
					$lists = array();
					$genderOptions[] = JHTML::_('select.option', 'm', JText::_('K2_MALE'));
					$genderOptions[] = JHTML::_('select.option', 'f', JText::_('K2_FEMALE'));
					$lists['gender'] = JHTML::_('select.radiolist', $genderOptions, 'jform[k2Profile][gender]', '', 'value', 'text', $K2User->gender);

					// Assign variables to view
					$view->assignRef('editor', $editor);
					$view->assignRef('lists', $lists);
					$view->assignRef('K2Params', $params);
					$view->assignRef('user', $user);

					// Trigger K2 plugins
					JPluginHelper::importPlugin('k2');
					$dispatcher = JDispatcher::getInstance();
					$K2Plugins = $dispatcher->trigger('onRenderAdminForm', array(&$K2User, 'user'));
					$view->assignRef('K2Plugins', $K2Plugins);
					$view->assignRef('K2User', $K2User);

					// More variables for the view
					$nameFieldName = 'jform[name]';
					$view->assignRef('nameFieldName', $nameFieldName);
					$emailFieldName = 'jform[email1]';
					$view->assignRef('emailFieldName', $emailFieldName);
					$passwordFieldName = 'jform[password1]';
					$view->assignRef('passwordFieldName', $passwordFieldName);
					$passwordVerifyFieldName = 'jform[password2]';
					$view->assignRef('passwordVerifyFieldName', $passwordVerifyFieldName);
					$usernameFieldName = 'jform[username]';
					$view->assignRef('usernameFieldName', $usernameFieldName);
					$idFieldName = 'jform[id]';
					$view->assignRef('idFieldName', $idFieldName);
					$optionValue = 'com_users';
					$view->assignRef('optionValue', $optionValue);
					$taskValue = 'profile.save';
					$view->assignRef('taskValue', $taskValue);

					// Get buffer
					ob_start();
					$view->display();
					$contents = ob_get_clean();

					// Override the component output
					$document = JFactory::getDocument();
					$document->setBuffer($contents, 'component');
				}

			}

		}

	}

	public function onAfterRender()
	{
		// Get variables
		$application = JFactory::getApplication();
		$document = JFactory::getDocument();
		$user = JFactory::getUser();
		$params = JComponentHelper::getParams('com_k2');
		$view = $application->input->get('view');
		$print = $application->input->getBool('print');
		$tmpl = $application->input->get('tmpl');

		// Add user toolbar
		if ($application->isSite() && $document->getType() == 'html' && $user->authorise('core.manage', 'com_k2') && $view != 'admin' && $view != 'k2' && trim($view) != '' && !$print && $tmpl != 'component')
		{
			$author = K2Users::getInstance($user->id);
			$file = JPATH_SITE.'/components/com_k2/templates/default/toolbar.php';
			$template = $application->getTemplate();
			$override = JPATH_SITE.'/templates/'.$template.'/html/com_k2/default/toolbar.php';
			if (JFile::exists($override))
			{
				$file = $override;
			}
			ob_start();
			include $file;
			$toolbar = ob_get_contents();
			ob_end_clean();

			$response = $application->getBody();
			$searches = array('</body>');
			$replacements = array($toolbar, );
			$response = str_ireplace($searches, $replacements, $response);
			$application->setBody($response);
		}

		// Fix Facebook meta tags
		if ($params->get('facebookMetadata'))
		{
			$application = JFactory::getApplication();
			$response = $application->getBody();

			// Make the following more generic?
			$searches = array('<meta name="og:url"', '<meta name="og:title"', '<meta name="og:type"', '<meta name="og:image"', '<meta name="og:description"');
			$replacements = array('<meta property="og:url"', '<meta property="og:title"', '<meta property="og:type"', '<meta property="og:image"', '<meta property="og:description"');

			if (strpos($response, 'prefix="og: http://ogp.me/ns#"') === false)// make it less strict?
			{
				$searches[] = '<html ';
				$searches[] = '<html>';
				$replacements[] = '<html prefix="og: http://ogp.me/ns#" ';
				$replacements[] = '<html prefix="og: http://ogp.me/ns#">';
			}
			$response = str_ireplace($searches, $replacements, $response);
			$application->setBody($response);
		}

	}
	
	// Legacy
	public function onBeforeDisplayView($context, $view)
	{
		// Get application
		$application = JFactory::getApplication();

		// Switch context
		switch($context)
		{
			// Item view
			case 'com_k2.item' :
				$params = clone($view->params);
				$itemParams = $view->item->params;
				$categoryParams = $view->item->category->getEffectiveParams();
				$view->item->params = clone($view->params);
				$view->item->params->merge($categoryParams);
				$view->item->params->merge($itemParams);
				$view->item->params->set('itemRating', false);
				$offset = $application->input->get('limitstart');
				$view->item->comments = $view->item->getComments($offset);
				jimport('joomla.html.pagination');
				$view->pagination = new JPagination($view->item->numOfComments, $offset, $view->params->get('commentsLimit'));
				$view->inlineCommentsModeration = false;
				$view->authorLatestItems = $view->item->author->latest;
				$view->item->video = $view->item->getVideo();
				$view->item->gallery = $view->item->getGallery();
				if ($view->item->author->image)
				{
					$view->item->author->avatar = $view->item->author->image->src;
				}
				else
				{
					$view->item->author->avatar = null;
				}

				if (is_string($view->item->extra_fields))
				{
					$view->item->extraFields = $view->item->getExtraFields();
					$view->item->extra_fields = $view->item->getextra_fields();
					if(!$view->item->params->get('itemEmptyExtraFields')){
						$this->removeEmptyExtraFields($view->item);
					}
				}

				if ($params->get('comments'))
				{
					$user = JFactory::getUser();
					if ($user->guest && $user->authorise('k2.comment.add', 'com_k2'))
					{
						$view->item->params->set('comments', 1);
					}
					else
					{
						$view->item->params->set('comments', 2);
					}
				}

				if (!$view->item->canEdit)
				{
					unset($view->item->editLink);
				}
				break;

			// User view
			case 'com_k2.itemlist.user' :
				$view->user = $view->author;
				foreach ($view->items as &$item)
				{
					$item->params = $view->params;
					if (!$item->canEdit)
					{
						unset($item->editLink);
					}
				}
				$db = JFactory::getDbo();
				$view->nullDate = $db->getNullDate();
				$view->now = JFactory::getDate()->toSql();
				$view->user->avatar = $view->user->image->src;
				$view->feed = $view->user->feedLink;
				if (!$view->user->canAdd)
				{
					unset($view->user->addLink);
				}
				break;

			// Tag view
			case 'com_k2.itemlist.tag' :
				$view->feed = $view->tag->feedLink;
				break;

			// Category view
			case 'com_k2.itemlist.category' :
				if (isset($view->category))
				{
					$view->feed = $view->category->feedLink;
					$view->subCategories = $view->category->children;
					if (!$view->category->canAdd)
					{
						unset($view->category->addLink);
					}
				}
				else
				{
					$view->feed = JRoute::_('&format=feed&limitstart=');
				}
				foreach ($view->items as &$item)
				{
					if (isset($view->category))
					{
						$item->params = $view->params;
					}
					else
					{
						$params = clone($view->params);
						$itemParams = $item->params;
						$categoryParams = $item->category->getEffectiveParams();
						$item->params = clone($view->params);
						$item->params->merge($categoryParams);
						$item->params->merge($itemParams);
					}
					
					if (is_string($item->extra_fields))
					{
						$item->extraFields = $item->getExtraFields();
						$item->extra_fields = $item->getextra_fields();
						if(!$item->params->get('catItemEmptyExtraFields')){
							$this->removeEmptyExtraFields($item);
						}
					}
						
					if (!$item->canEdit)
					{
						unset($item->editLink);
					}

				}
				break;

			// Category view
			case 'com_k2.latest' :
				$view->source = $view->params->get('source');
				foreach ($view->blocks as $block)
				{
					foreach ($block->items as &$item)
					{
						$item->params = $view->params;
					}
				}
				break;
		}

	}
	
	function removeEmptyExtraFields(&$item) {
		foreach ($item->extra_fields as $key => $field){
			if($field->value == '<div></div>'){
				unset($item->extra_fields[$key]);
			}
		}
	}

}

// Legacy
class K2HelperPermissions
{
	public static function canAddComment($categoryId)
	{
		$user = JFactory::getUser();
		return $user->authorise('k2.comment.create', 'com_k2.category.'.$categoryId);

	}

}
