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
	private $editor;
		
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
		$this->editor = $editor;
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
	public function getContent($editor){
		if($this->editor == 'tinymce'){
			// override default method for Tiny MCE, as default getContent cannot handle more than one editor window.
			$js = "tinyMCE.get('".$editor."').getContent();";
			return $js;
		}
		return parent::getContent($editor);
	}
	
	public function setContent($editor, $html){
		if($this->editor == 'tinymce'){
			// override default method for Tiny MCE, as default getContent cannot handle more than one editor window.
			$js = "tinyMCE.get('".$editor."').setContent('".$html."');";
			return $js;
		}
		return parent::getContent($editor, $html);
	}
	
	public function save($editor){
		if($this->editor == 'tinymce'){
			// override default method for Tiny MCE, as default getContent cannot handle more than one editor window.
			$js = "if (tinyMCE.get('".$editor."').isHidden()) {tinyMCE.get('".$editor."').show();};document.getElementById(".$editor.").value = tinyMCE.get('".$editor."').getContent();";
			return $js;
		}
		return parent::save($editor);
	}	
}
