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

jimport('joomla.html.editor');

/**
 * K2 Editor class.
 * Overrides the default editor class in order to get the required scripts for the editor.
 */

class K2Editor extends JEditor
{
	private $js;
	private $start;
		
	public static function getInstance($editor = 'none')
	{
		$signature = serialize($editor);

		if (empty(self::$instances[$signature]))
		{
			self::$instances[$signature] = new K2Editor($editor);
		}

		return self::$instances[$signature];
	}

	public function __construct($editor = 'none')
	{
		parent::__construct($editor);
		$this->init();
	}
	
	public function init() {
		if (is_null ( $this->start )) {
			$this->_loadEditor ();
			
			$this->start = 'started';
			
			$args ['event'] = 'onInit';
			$results [] = $this->_editor->update ( $args );
			
			$doc = new DOMDocument ();
			$doc->loadHTML ( implode ( $results ) );
			$scripts = $doc->getElementsByTagName ( 'script' );
			foreach ( $scripts as $key => $script ) {
				$this->js .= $scripts->item ( $key )->nodeValue;
			}
		}
		return $this->js;
	}

	public function save($editor)
	{
		return parent::save($editor)." jQuery('#' + ".$editor.").val(K2Editor.getContent('".$editor."'));";
	}	
}
