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

require_once JPATH_ADMINISTRATOR.'/components/com_k2/controller.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/classes/filesystem.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/items.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/resources/attachments.php';
require_once JPATH_ADMINISTRATOR.'/components/com_k2/helpers/attachments.php';

/**
 * Attachments JSON controller.
 */

class K2ControllerAttachments extends K2Controller
{

	/**
	 * onBeforeRead function.
	 * Hook for chidlren controllers to check for access
	 *
	 * @param string $mode		The mode of the read function. Pass 'row' for retrieving a single row or 'list' to retrieve a collection of rows.
	 * @param mixed $id			The id of the row to load when we are retrieving a single row.
	 *
	 * @return void
	 */
	protected function onBeforeRead($mode, $id)
	{
		return false;
	}

	public function upload()
	{
		// Check for token
		JSession::checkToken() or K2Response::throwError(JText::_('K2_INVALID_TOKEN_DURING_FILE_UPLOAD'));

		// Get user
		$user = JFactory::getUser();

		// Permissions check
		if (!$user->authorise('k2.item.create', 'com_k2') && !$user->authorise('k2.item.edit', 'com_k2') && !$user->authorise('k2.item.edit.own', 'com_k2'))
		{
			K2Response::throwError(JText::_('K2_YOU_ARE_NOT_AUTHORIZED_TO_PERFORM_THIS_OPERATION'), 403);
		}

		// Get input
		$input = JFactory::getApplication()->input;
		$upload = $input->get('file', '', 'cmd');
		$url = $input->get('url', '', 'string');
		$file = $input->files->get('file');

		// Create the gallery and delete the previous one if it is set
		$attachment = K2HelperAttachments::add($file, $url, $upload);

		// Response
		echo json_encode($attachment);

		// Return
		return $this;
	}

}
