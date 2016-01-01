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
?>
<div class="jw--block--field">
	<div class="ov-hidden">
		<label title="<?php echo JText::_('K2_DATE_FORMAT_DESC'); ?>"><?php echo JText::_('K2_DATE_FORMAT_LBL'); ?></label>
	</div>
	<div class="ov-hidden">
		<input type="text" name="<?php echo $field->get('prefix'); ?>[format]" value="<?php echo htmlspecialchars($field->get('format'), ENT_QUOTES, 'UTF-8');?>" /> 
	</div>
</div>