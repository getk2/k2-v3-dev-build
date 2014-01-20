<?php
/**
 * @version		3.0.0
 * @package		K2
 * @author		JoomlaWorks http://www.joomlaworks.net
 * @copyright	Copyright (c) 2006 - 2013 JoomlaWorks Ltd. All rights reserved.
 * @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 */

// no direct access
defined('_JEXEC') or die ;

jimport('joomla.archive.archive');
jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');

require_once JPATH_ADMINISTRATOR.'/components/com_k2/classes/filesystem.php';

/**
 * K2 galleries helper class.
 */

class K2HelperGalleries
{
	public static function add($archive, $replace = null)
	{
		// Application
		$application = JFactory::getApplication();

		// Session
		$session = JFactory::getSession();

		// Generate gallery name
		$gallery = uniqid();

		// Random temporary path
		$newTempGalleryPath = $application->getCfg('tmp_path').'/'.$gallery;

		// Create the folder
		JFolder::create($newTempGalleryPath);

		// Upload the file to the temporary folder
		JFile::upload($archive['tmp_name'], $newTempGalleryPath.'/'.$archive['name']);

		// Extract the archive
		JArchive::extract($newTempGalleryPath.'/'.$archive['name'], $newTempGalleryPath);

		// Delete the archive
		JFile::delete($newTempGalleryPath.'/'.$archive['name']);

		// Add the temporary folder to session so we can perform clean up when needed
		$galleries = $session->get('k2.galleries', array());
		$galleries[] = $gallery;
		$session->set('k2.galleries', $galleries);

		// Handle previous temporary folder
		if ($replace && JFolder::exists($application->getCfg('tmp_path').'/'.$replace))
		{
			// Remove from file system
			JFolder::delete($application->getCfg('tmp_path').'/'.$replace);

			// Remove from session
			if (($key = array_search($replace, $galleries)) !== false)
			{
				unset($galleries[$key]);
				$session->set('k2.galleries', $galleries);
			}

		}

		// return
		return $gallery;

	}

	public static function update($galleries, $itemId)
	{
		// Application
		$application = JFactory::getApplication();

		// Session
		$session = JFactory::getSession();

		// File system
		$filesystem = K2FileSystem::getInstance();

		// Valid galleries
		$validGalleries = array();

		// Iterate over the galleries and transfer the new galleries from /tmp to /media/k2/galleries
		foreach ($galleries as $gallery)
		{
			if ($gallery->upload)
			{
				$source = $application->getCfg('tmp_path').'/'.$gallery->upload;

				$target = 'media/k2/galleries/'.$itemId.'/'.$gallery->upload;

				// Push the gallery to valid array
				$validGalleries[] = $target;

				// Check if the gallery is new
				if (JFolder::exists($source))
				{
					// Transfer the files from the temporary folder to the current file system
					$files = JFolder::files($source);
					foreach ($files as $file)
					{
						$buffer = JFile::read($file);
						$filesystem->write($target.'/'.$file, $buffer, true);
					}

					// Delete the temporary folder
					JFolder::delete($source);

					// Remove temporary folder from session
					$temp = $session->get('k2.galleries', array());
					if (($key = array_search($gallery->upload, $temp)) !== false)
					{
						unset($temp[$key]);
						$session->set('k2.galleries', $temp);
					}
				}

			}
		}

		// Iterate over the galleries in /media/k2/galleries and delete those who have been removed by the user
		$keys = $filesystem->listKeys('media/k2/galleries/'.$itemId.'/');
		$folders = $keys['dirs'];

		foreach ($folders as $galleryKey)
		{
			if (!in_array($galleryKey, $validGalleries) && $filesystem->has($galleryKey))
			{
				$files = $filesystem->listKeys($galleryKey.'/');

				foreach ($files['keys'] as $key)
				{
					if ($filesystem->has($key))
					{
						$filesystem->delete($key);
					}
				}
				$filesystem->delete($galleryKey);
			}
		}

		// Check if the item folder contains more galleries. If not delete it.
		$keys = $filesystem->listKeys('media/k2/galleries/'.$itemId.'/');
		if (empty($keys['dirs']))
		{
			$filesystem->delete('media/k2/galleries/'.$itemId);
		}

	}

	public static function remove($gallery, $itemId)
	{
		// File system
		$filesystem = K2FileSystem::getInstance();

		// Key
		$galleryKey = 'media/k2/galleries/'.$itemId.'/'.$gallery;

		if ($filesystem->has($galleryKey))
		{
			$files = $filesystem->listKeys($galleryKey);

			foreach ($files['keys'] as $key)
			{
				if ($filesystem->has($key))
				{
					$filesystem->delete($key);
				}
			}
			$filesystem->delete($galleryKey);

			// Check if the item folder contains more galleries. If not delete it.
			$keys = $filesystem->listKeys('media/k2/galleries/'.$itemId.'/');
			if (empty($keys['dirs']))
			{
				$filesystem->delete('media/k2/galleries/'.$itemId);
			}
		}

		return true;
	}

	public static function purge()
	{
		// Application
		$application = JFactory::getApplication();

		// Session
		$session = JFactory::getSession();

		// Temporary folders
		$galleries = $session->get('k2.galleries', array());

		foreach ($galleries as $gallery)
		{
			if ($gallery && JFolder::exists($application->getCfg('tmp_path').'/'.$gallery))
			{
				// Remove from tmp folder
				JFolder::delete($application->getCfg('tmp_path').'/'.$gallery);
			}
		}
		$session->set('k2.galleries', array());

	}

}
