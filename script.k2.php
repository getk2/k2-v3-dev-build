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

class Com_K2InstallerScript
{
	public function preflight($type, $parent)
	{
		$application = JFactory::getApplication();
		$configuration = JFactory::getConfig();
		$installer = $parent->getParent();
		$db = JFactory::getDbo();

		// Init the upgrade flag
		$this->upgrade = false;

		// Proceed only if we are updating
		if ($type != 'install')
		{
			// Ensure that we are under Joomla! 3.2 or later
			if (version_compare(JVERSION, '3.3.6', 'lt'))
			{
				$parent->getParent()->abort('K2 requires Joomla! 3.3.6 or later.');
				return false;
			}

			// Get installled version
			$query = $db->getQuery(true);
			$query->select($db->quoteName('manifest_cache'))->from($db->quoteName('#__extensions'))->where($db->quoteName('name').' = '.$db->quote('com_k2'));
			$db->setQuery($query);
			$manifest = json_decode($db->loadResult());
			$installedVersion = $manifest->version;

			// Detect if we need to perform an upgrade
			if (version_compare($installedVersion, '3.0.0', 'lt'))
			{
				// Ensure that the installed K2 version is not very old. Otherwise the update will fail.
				if (version_compare($installedVersion, '2.6.9', 'lt'))
				{
					$parent->getParent()->abort('You cannot update from this version of K2. Please update first your current K2 installation to the latest 2.x series and try again.');
					return false;
				}

				// User is required to put the site offline while upgrading
				if (!$configuration->get('offline'))
				{
					$parent->getParent()->abort('Site is not offline. Please put your site offline and try again.');
					return false;
				}

				// Since this is an upgrade rename all K2 2.x tables so the new ones will be created.
				$oldTables = array('#__k2_attachments', '#__k2_categories', '#__k2_comments', '#__k2_extra_fields', '#__k2_extra_fields_groups', '#__k2_items', '#__k2_rating', '#__k2_tags', '#__k2_tags_xref', '#__k2_users', '#__k2_user_groups');
				$existingTables = $db->getTableList();
				foreach ($oldTables as $oldTable)
				{	
					$count = 1;
					$newTable = str_replace('#__k2_', '#__k2_v2_', $oldTable);
					$needle = str_replace('#__', $db->getPrefix(), $newTable, $count);
					if (!in_array($needle, $existingTables))
					{
						$db->setQuery('RENAME TABLE '.$db->quoteName($oldTable).' TO '.$db->quoteName($newTable));
						if (!$db->execute())
						{
							$parent->getParent()->abort(JText::sprintf('JLIB_INSTALLER_ABORT_COMP_INSTALL_SQL_ERROR', $db->stderr(true)));
							return false;
						}
					}

				}

				// Force parsing of SQL file since Joomla! does that only in install mode, not in updates
				$sql = $installer->getPath('source').'/administrator/components/com_k2/install.sql';
				$queries = JDatabaseDriver::splitSql(file_get_contents($sql));
				foreach ($queries as $query)
				{
					$query = trim($query);
					if ($query != '' && $query{0} != '#')
					{
						$db->setQuery($query);
						if (!$db->execute())
						{
							$parent->getParent()->abort(JText::sprintf('JLIB_INSTALLER_ERROR_SQL_ERROR', $db->stderr(true)));
							return false;
						}
					}
				}

				// Rename component files to get rid of files we don't need
				if (JFolder::exists(JPATH_SITE.'/components/com_k2'))
				{
					if (JFolder::exists(JPATH_SITE.'/components/com_k2_v2'))
					{
						if (!JFolder::delete(JPATH_SITE.'/components/com_k2_v2'))
						{
							$parent->getParent()->abort('Could not delete folder '.JPATH_SITE.'/components/com_k2_v2. Check permissions.');
							return false;
						}
					}
					if (!JFolder::move(JPATH_SITE.'/components/com_k2', JPATH_SITE.'/components/com_k2_v2'))
					{
						$parent->getParent()->abort('Could not move folder '.JPATH_SITE.'/components/com_k2. Check permissions.');
						return false;
					}
					if (!JFolder::create(JPATH_SITE.'/components/com_k2'))
					{
						$parent->getParent()->abort('Could not create folder '.JPATH_SITE.'/components/com_k2. Check permissions.');
						return false;
					}else{
						// Create dummy folders so that Joomla's update routine can delete this without issueing warnings.
						JFolder::create(JPATH_SITE.'/components/com_k2/controllers');
						JFolder::create(JPATH_SITE.'/components/com_k2/js');
						JFolder::create(JPATH_SITE.'/components/com_k2/models');
						JFolder::create(JPATH_SITE.'/components/com_k2/sef_ext');
					}
					if (!JFolder::copy(JPATH_SITE.'/components/com_k2_v2/templates', JPATH_SITE.'/components/com_k2/templates'))
					{
						$parent->getParent()->abort('Could not copy folder '.JPATH_SITE.'/components/com_k2_v2/templates. Check permissions.');
						return false;
					}
				}
				if (JFolder::exists(JPATH_ADMINISTRATOR.'/components/com_k2'))
				{

					if (JFolder::exists(JPATH_ADMINISTRATOR.'/components/com_k2_v2'))
					{
						if (!JFolder::delete(JPATH_ADMINISTRATOR.'/components/com_k2_v2'))
						{
							$parent->getParent()->abort('Could not delete folder '.JPATH_ADMINISTRATOR.'/components/com_k2_v2. Check permissions.');
							return false;
						}
					}

					if (!JFolder::move(JPATH_ADMINISTRATOR.'/components/com_k2', JPATH_ADMINISTRATOR.'/components/com_k2_v2'))
					{
						$parent->getParent()->abort('Could not move folder '.JPATH_ADMINISTRATOR.'/components/com_k2. Check permissions.');
						return false;
					}
					
					if (!JFolder::create(JPATH_ADMINISTRATOR.'/components/com_k2'))
					{
						$parent->getParent()->abort('Could not create folder '.JPATH_ADMINISTRATOR.'/components/com_k2. Check permissions.');
						return false;
					}else{
						// Create dummy folders so that Joomla's update routine can delete this without issueing warnings.
						JFolder::create(JPATH_ADMINISTRATOR.'/components/com_k2/elements');
						JFolder::create(JPATH_ADMINISTRATOR.'/components/com_k2/jupgrade');
						JFolder::create(JPATH_ADMINISTRATOR.'/components/com_k2/lib');
					}

				}

				// Set a flag that this is an upgrade
				$this->upgrade = true;
			}
		}

	}

	public function postflight($type, $parent)
	{
		// Get database
		$db = JFactory::getDbo();

		// Get manifest
		$src = $parent->getParent()->getPath('source');
		$manifest = $parent->getParent()->manifest;

		// Install media/k2app
		if (JFolder::exists(JPATH_SITE.'/media/k2app'))
		{
			JFolder::delete(JPATH_SITE.'/media/k2app');
		}
		JFolder::copy($src.'/media/k2app', JPATH_SITE.'/media/k2app');

		// Install plugins
		$plugins = $manifest->xpath('plugins/plugin');

		foreach ($plugins as $plugin)
		{
			// Get plugin variables
			$name = (string)$plugin->attributes()->plugin;
			$group = (string)$plugin->attributes()->group;
			$path = $src.'/plugins/'.$group.'/'.$name;

			// Install
			$installer = new JInstaller;
			$result = $installer->install($path);

			// Enable the plugin. Only for fresh installations, not updates!
			if ($type == 'install' && $group != 'finder')
			{
				$query = $db->getQuery(true);
				$query->update($db->quoteName('#__extensions'))->set($db->quoteName('enabled').' = 1')->where($db->quoteName('type').' = '.$db->quote('plugin'))->where($db->quoteName('element').' = '.$db->quote($name))->where($db->quoteName('folder').' = '.$db->quote($group));
				$db->setQuery($query);
				$db->execute();
			}
		}

		// Install modules
		$modules = $manifest->xpath('modules/module');
		foreach ($modules as $module)
		{
			// Get module variables
			$name = (string)$module->attributes()->module;
			$client = (string)$module->attributes()->client;

			// Set client if it's null
			if (is_null($client))
			{
				$client = 'site';
			}

			// Detect path
			$path = ($client == 'administrator') ? $src.'/administrator/modules/'.$name : $src.'/modules/'.$name;

			// Install
			$installer = new JInstaller;
			$result = $installer->install($path);

			// Publish the administrator modules. Only for fresh installations, not updates!
			if ($type == 'install' && $client == 'administrator')
			{
				// Detect target position
				$position = 'cpanel';

				// Publish the module
				$query = $db->getQuery(true);
				$query->update($db->quoteName('#__modules'))->set($db->quoteName('position').' = '.$db->quote($position))->set($db->quoteName('published').' = 1')->where($db->quoteName('module').' = '.$db->quote($name));
				$db->setQuery($query);
				$db->execute();

				$query = $db->getQuery(true);
				$query->select($db->quoteName('id'))->from($db->quoteName('#__modules'))->where($db->quoteName('module').' = '.$db->quote($name));
				$db->setQuery($query);
				$id = (int)$db->loadResult();
				if ($id)
				{
					$query = $db->getQuery(true);
					$query->select('COUNT(*)')->from($db->quoteName('#__modules_menu'))->where($db->quote('moduleid').' = '.$id);
					$db->setQuery($query);
					$exists = (int)$db->loadResult();
					if (!$exists)
					{
						$query = $db->getQuery(true);
						$query->insert($db->quoteName('#__modules_menu'))->columns('moduleid, menuid')->values($id.',0');
						$db->setQuery($query);
						$db->execute();
					}
				}
			}
		}

		// Update params
		$params = JComponentHelper::getParams('com_k2');

		// Add default params directly from the config file. Used both in new installs and updates.
		$xmlForm = simplexml_load_file($src.'/administrator/components/com_k2/config.xml');
		foreach ($xmlForm->xpath('fieldset') as $fieldset)
		{
			$this->updateParams($params, $fieldset);
		}

		// Set the default image sizes for new installs
		if ($type == 'install')
		{
			$imageSizes = array();

			$size = new stdClass;
			$size->id = 'XS';
			$size->name = 'Extra Small';
			$size->width = 100;
			$size->quality = 100;
			$imageSizes[] = $size;

			$size = new stdClass;
			$size->id = 'S';
			$size->name = 'Small';
			$size->width = 200;
			$size->quality = 100;
			$imageSizes[] = $size;

			$size = new stdClass;
			$size->id = 'M';
			$size->name = 'Medium';
			$size->width = 400;
			$size->quality = 100;
			$imageSizes[] = $size;

			$size = new stdClass;
			$size->id = 'L';
			$size->name = 'Large';
			$size->width = 600;
			$size->quality = 100;
			$imageSizes[] = $size;

			$size = new stdClass;
			$size->id = 'XL';
			$size->name = 'Extra Large';
			$size->width = 900;
			$size->quality = 100;
			$imageSizes[] = $size;

			$params->set('imageSizes', $imageSizes);

		}

		// Execute the update params query
		$query = $db->getQuery(true);
		$query->update($db->quoteName('#__extensions'));
		$query->set($db->quoteName('params').' = '.$db->quote($params->toString()));
		$query->where($db->quoteName('element').' = '.$db->quote('com_k2'));
		$db->setQuery($query);
		$db->execute();

		// Add upgrade flag to session
		$session = JFactory::getSession();
		$session->set('k2.upgrade', $this->upgrade);

		// Redirect to custom installation page
		$parent->getParent()->set('redirect_url', 'index.php?option=com_k2&view=installation');
	}

	public function uninstall($parent)
	{
		// Get database
		$db = JFactory::getDBO();

		// Get manifest
		$manifest = $parent->getParent()->manifest;

		// Install media/k2app
		if (JFolder::exists(JPATH_SITE.'/media/k2app'))
		{
			JFolder::delete(JPATH_SITE.'/media/k2app');
		}

		// Uninstall plugins
		$plugins = $manifest->xpath('plugins/plugin');
		foreach ($plugins as $plugin)
		{
			// Get plugin variables
			$name = (string)$plugin->attributes()->plugin;
			$group = (string)$plugin->attributes()->group;

			// Get extension id
			$query = $db->getQuery(true);
			$query->select($db->quoteName('extension_id'))->from($db->quoteName('#__extensions'))->where($db->quoteName('type').' = '.$db->quote('plugin'))->where($db->quoteName('element').' = '.$db->quote($name))->where($db->quoteName('folder').' = '.$db->quote($group));
			$db->setQuery($query);
			$id = $db->loadResult();
			if ($id)
			{
				// Uninstall
				$installer = new JInstaller;
				$result = $installer->uninstall('plugin', $id);
			}

		}

		// Uninstall modules
		$modules = $manifest->xpath('modules/module');
		foreach ($modules as $module)
		{
			// Get module variables
			$name = (string)$module->attributes()->module;
			$client = (string)$module->attributes()->client;

			// Get all module instances ids
			$query = $db->getQuery(true);
			$query->select($db->quoteName('extension_id'))->from($db->quoteName('#__extensions'))->where($db->quoteName('type').' = '.$db->quote('module'))->where($db->quoteName('element').' = '.$db->quote($name));
			$db->setQuery($query);
			$instances = $db->loadColumn();

			// Uninstall all module instances
			if (count($instances))
			{
				foreach ($instances as $id)
				{
					// Unistall module instance
					$installer = new JInstaller;
					$result = $installer->uninstall('module', $id);
				}
			}
		}
	}

	private function updateParams(&$params, $xml)
	{
		// set default value if we've one
		$name = (string)$xml['name'];
		$default = (string)$xml['default'];
		if (!empty($name) && !empty($default))
		{
			$params->def($name, $default);
		}
		// recurse further
		foreach ($xml->children() as $child)
		{
			$this->updateParams($params, $child);
		}
	}

}
