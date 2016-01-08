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
 * K2 utilities helper class.
 */

class K2HelperUtilities
{
	const STATE_WORD = 1;
	const STATE_SPACE = 2;
	const STATE_TAGSTART = 3;
	const STATE_TAG = 4;
	const STATE_COMMENT = 5;

	public static function writtenBy($gender)
	{
		if ($gender == 'm')
		{
			return JText::_('K2_WRITTEN_BY_MALE');
		}
		else if ($gender == 'f')
		{
			return JText::_('K2_WRITTEN_BY_FEMALE');
		}
		else
		{
			return JText::_('K2_WRITTEN_BY');
		}
	}

	public static function wordLimit($string, $length = 100, $endCharacter = '&#8230;')
	{
		// If the string is empty return
		if (trim($string) == '')
		{
			return $string;
		}

		$buffer = new TextBuffer();
		$buffer->setEndCharacter($endCharacter);
		$state = STATE_WORD;
		$wordCount = 0;
		$p = 0;
		$len = strlen($string);
		
		while ($p < $len) {
			$c = $string[$p];
			
			switch ($state){
				case STATE_WORD :
					if ($c == '<'){
						$state = STATE_TAGSTART;
						break;
					}
					if (ctype_space($c)){
						$state = STATE_SPACE;
					}
					// FALL THROUGH
					$buffer->buffer($c);
					$p++;
					break;
					
				case STATE_SPACE :
					if ($c == '<'){
						$state = STATE_TAGSTART;
						break;
					}
					if (!ctype_space($c)){
						$state = STATE_WORD;
						if($wordCount++ == $length){
							$buffer->setBufferState(0);
						}
					}
					// FALL THROUGH
					$buffer->buffer($c);
					$p++;
					break;
					
				case STATE_TAGSTART :
					if (substr($string, $p, 4) == '<!--'){
						$stack[] = $state;
						$state = STATE_COMMENT;
					}else{
						$state = STATE_TAG;
						if($wordCount++ == $length){
							$buffer->setBufferState(0);
						}
						$buffer->bufferAnyway($c);
						$p++;
					}
					break;
				case STATE_TAG :
					if($c == '<'){
						if (substr($string, $p, 4) == '<!--'){
							$state = STATE_COMMENT;
							break;
						}
					}elseif ($c == '>'){
						$state = STATE_WORD;
					}
					// FALL THROUGH
					$buffer->bufferAnyway($c);
					$p++;
					break;
					
				case STATE_COMMENT :
					$p = strpos ($string, '-->', $p ) + 3;
					$state = array_pop($stack);
					break;
			}
		}
	
		return $buffer->getBuffer();
	}

	public static function characterLimit($string, $length = 150, $endCharacter = '...')
	{
		if (!$string = trim($string))
		{
			return $string;
		}
		$string = strip_tags($string);
		$string = preg_replace('/\s+/', ' ', $string);
		if (strlen($string) > $length)
		{
			$string = substr($string, 0, $length);
			$string = rtrim($string);
			$string .= $endCharacter;
		}
		return $string;
	}

	public static function getModule($id)
	{
		// Get module
		$module = JTable::getInstance('Module');
		$module->load($id);

		// Check access and state
		$date = JFactory::getDate()->toSql();
		$viewlevels = JFactory::getUser()->getAuthorisedViewLevels();

		if ($module->published && in_array($module->access, $viewlevels) && ((int)$module->publish_up == 0 || $module->publish_up >= $date) && ((int)$module->publish_down == 0 || $module->publish_down < $date))
		{
			$module->params = new JRegistry($module->params);
			return $module;
		}
		else
		{
			return false;
		}

	}

	public static function cleanHtml($text)
	{
		return htmlspecialchars(strip_tags($text), ENT_QUOTES, 'UTF-8');
	}

	// Legacy
	public static function setDefaultImage(&$item, $view, $params = NULL)
	{
		return;
	}
	public static function getAvatar($id, $email)
	{
		$user = K2Users::getInstance($id);
		return $user->image->src;
	}
}

class TextBuffer {
	private $str = null;
	private $state = 1;
	private $endCharacter;
	
	public function buffer($c){
		if($this->state){
			$this->str .= $c;
		}
	}
	public function bufferAnyway($c){
		$this->str .= $c;
	}
	public function setEndCharacter($c){
		$this->endCharacter = $c;
	}
	public function setBufferState($state){
		if ($this->state != 0 && $state == 0){
			$this->str .= ' '.$this->endCharacter;
		}
		$this->state = $state;
	}
	public function getBuffer() {
		return $this->str;
	}
}
